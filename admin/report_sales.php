<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$from = $valid($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
$to = $valid($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$group = in_array($_GET['group'] ?? '', ['day', 'week', 'month'], true) ? $_GET['group'] : 'day';
$expr = [
    'day' => 'DATE(o.date_placed)',
    'week' => 'DATE_SUB(DATE(o.date_placed), INTERVAL WEEKDAY(o.date_placed) DAY)',
    'month' => "DATE_FORMAT(o.date_placed, '%Y-%m-01')",
][$group];
$periodLabel = function (string $p) use ($group): string {
    return match ($group) {
        'week' => 'Week of ' . date('M j, Y', strtotime($p)),
        'month' => date('F Y', strtotime($p)),
        default => date('D, M j, Y', strtotime($p)),
    };
};

$base = "o.status <> 'Canceled' AND DATE(o.date_placed) BETWEEN ? AND ?";
$range = [$from, $to];

$sum = db_row(
    $conn,
    "SELECT COUNT(*) AS orders,
            COALESCE(SUM(o.total_amount), 0) AS sales,
            COALESCE(SUM(CASE WHEN o.payment_method = 'cash' THEN o.total_amount END), 0) AS cash,
            COALESCE(SUM(CASE WHEN o.payment_method = 'lista' THEN o.total_amount END), 0) AS lista,
            COALESCE(SUM(CASE WHEN o.order_type = 'walk_in' THEN o.total_amount END), 0) AS walkin,
            COALESCE(SUM((SELECT SUM(quantity) FROM orderline WHERE orderinfo_id = o.orderinfo_id)), 0) AS items
     FROM orderinfo o WHERE $base",
    $range
);
$periods = db_rows(
    $conn,
    "SELECT p.period, COUNT(*) AS orders, SUM(p.total_amount) AS sales, SUM(p.items) AS items,
            SUM(CASE WHEN p.payment_method = 'cash' THEN p.total_amount ELSE 0 END) AS cash,
            SUM(CASE WHEN p.payment_method = 'lista' THEN p.total_amount ELSE 0 END) AS lista
     FROM (SELECT o.total_amount, o.payment_method, $expr AS period,
                  (SELECT COALESCE(SUM(quantity), 0) FROM orderline WHERE orderinfo_id = o.orderinfo_id) AS items
           FROM orderinfo o WHERE $base) p
     GROUP BY p.period ORDER BY p.period",
    $range
);
$top = db_rows(
    $conn,
    "SELECT i.description, SUM(ol.quantity) AS qty, SUM(ol.quantity * ol.unit_price) AS revenue
     FROM orderline ol JOIN orderinfo o ON o.orderinfo_id = ol.orderinfo_id JOIN item i ON i.item_id = ol.item_id
     WHERE $base GROUP BY i.item_id, i.description ORDER BY revenue DESC LIMIT 10",
    $range
);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-' . $group . '-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Period', 'Orders', 'Items sold', 'Cash sales', 'Suki list sales', 'Total sales'], ',', '"', '');
    foreach ($periods as $p) {
        fputcsv($out, [$periodLabel($p['period']), $p['orders'], $p['items'], $p['cash'], $p['lista'], $p['sales']], ',', '"', '');
    }
    fputcsv($out, ['Total', $sum['orders'], $sum['items'], $sum['cash'], $sum['lista'], $sum['sales']], ',', '"', '');
    fclose($out);
    exit;
}

$avg = (int) $sum['orders'] > 0 ? (float) $sum['sales'] / (int) $sum['orders'] : 0;
$monday = date('Y-m-d', strtotime('monday this week'));
$quick = [
    'Today' => [date('Y-m-d'), date('Y-m-d'), 'day'],
    'This week' => [$monday, date('Y-m-d'), 'day'],
    'This month' => [date('Y-m-01'), date('Y-m-d'), 'day'],
    'Last 30 days' => [date('Y-m-d', strtotime('-29 day')), date('Y-m-d'), 'day'],
    'This year' => [date('Y-01-01'), date('Y-m-d'), 'month'],
];
$qs = fn($f, $t, $g) => http_build_query(['from' => $f, 'to' => $t, 'group' => $g]);

$pageTitle = 'Sales report';
$adminActive = 'rsales';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div><h1 class="page-title">Sales report</h1><div class="muted"><?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?>, canceled orders left out</div></div>
  <div class="d-flex gap-2 no-print">
    <a class="btn btn-outline-primary" href="?<?= e($qs($from, $to, $group)) ?>&export=csv"><i class="bi bi-download"></i> Download CSV</a>
    <button class="btn btn-outline-primary" type="button" data-print><i class="bi bi-printer"></i> Print</button>
  </div>
</div>

<div class="filter-row no-print">
  <?php $marked = false; foreach ($quick as $label => [$f, $t, $g]): $on = !$marked && $f === $from && $t === $to; $marked = $marked || $on; ?>
    <a href="?<?= e($qs($f, $t, $g)) ?>" class="<?= $on ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form class="panel panel-body mb-4 no-print" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="group">Group by</label>
      <select class="form-select" id="group" name="group">
        <option value="day" <?= $group === 'day' ? 'selected' : '' ?>>Day</option>
        <option value="week" <?= $group === 'week' ? 'selected' : '' ?>>Week</option>
        <option value="month" <?= $group === 'month' ? 'selected' : '' ?>>Month</option>
      </select>
    </div>
    <div class="col-6 col-md-3 d-grid"><button class="btn btn-primary" type="submit">Show report</button></div>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Total sales</div><div class="value"><?= money($sum['sales']) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Orders</div><div class="value"><?= (int) $sum['orders'] ?></div><div class="muted small">Average <?= money($avg) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Items sold</div><div class="value"><?= (int) $sum['items'] ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Paid sa lista</div><div class="value"><?= money($sum['lista']) ?></div><div class="muted small">Cash <?= money($sum['cash']) ?></div></div></div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <section class="panel">
      <div class="panel-head"><h2>Sales by <?= e($group) ?></h2></div>
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Period</th><th class="text-end">Orders</th><th class="text-end">Items</th><th class="text-end">Cash</th><th class="text-end">Suki list</th><th class="text-end">Total</th></tr></thead>
          <tbody>
          <?php foreach ($periods as $p): ?>
            <tr>
              <td><?= e($periodLabel($p['period'])) ?></td>
              <td class="text-end num"><?= (int) $p['orders'] ?></td>
              <td class="text-end num"><?= (int) $p['items'] ?></td>
              <td class="text-end num"><?= money($p['cash']) ?></td>
              <td class="text-end num"><?= money($p['lista']) ?></td>
              <td class="text-end num fw-bold"><?= money($p['sales']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$periods): ?><tr><td colspan="6" class="text-center muted py-5">No sales in this period. Try a wider date range.</td></tr><?php endif; ?>
          </tbody>
          <?php if ($periods): ?>
          <tfoot><tr><td class="fw-bold">Total</td><td class="text-end num fw-bold"><?= (int) $sum['orders'] ?></td><td class="text-end num fw-bold"><?= (int) $sum['items'] ?></td><td class="text-end num fw-bold"><?= money($sum['cash']) ?></td><td class="text-end num fw-bold"><?= money($sum['lista']) ?></td><td class="text-end num fw-bold"><?= money($sum['sales']) ?></td></tr></tfoot>
          <?php endif; ?>
        </table>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="panel">
      <div class="panel-head"><h2>Best sellers</h2></div>
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Item</th><th class="text-end">Sold</th><th class="text-end">Sales</th></tr></thead>
          <tbody>
          <?php foreach ($top as $t): ?>
            <tr><td><?= e($t['description']) ?></td><td class="text-end num"><?= (int) $t['qty'] ?></td><td class="text-end num"><?= money($t['revenue']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$top): ?><tr><td colspan="3" class="text-center muted py-4">Nothing sold yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
