<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$salesToday = (float) db_val($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM orderinfo WHERE status <> 'Canceled' AND DATE(date_placed) = CURDATE()");
$salesMonth = (float) db_val($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM orderinfo WHERE status <> 'Canceled' AND YEAR(date_placed) = YEAR(CURDATE()) AND MONTH(date_placed) = MONTH(CURDATE())");
$ordersToday = (int) db_val($conn, "SELECT COUNT(*) FROM orderinfo WHERE status <> 'Canceled' AND DATE(date_placed) = CURDATE()");
$pending = (int) db_val($conn, "SELECT COUNT(*) FROM orderinfo WHERE status IN ('Pending','Processing')");
$lowCount = (int) db_val($conn, "SELECT COUNT(*) FROM inventory_report WHERE is_active = 1 AND stock_status <> 'OK'");
$owed = (float) db_val($conn, 'SELECT COALESCE(SUM(balance), 0) FROM lista_account');
$customers = (int) db_val($conn, 'SELECT COUNT(*) FROM customer');

$byDay = [];
foreach (db_rows($conn, 'SELECT sale_date, total_sales FROM daily_sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)') as $r) {
    $byDay[$r['sale_date']] = (float) $r['total_sales'];
}
$labels = [];
$values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('D j', strtotime($d));
    $values[] = $byDay[$d] ?? 0;
}

$recent = db_rows(
    $conn,
    'SELECT o.*, c.fname, c.lname FROM orderinfo o LEFT JOIN customer c ON c.customer_id = o.customer_id ORDER BY o.orderinfo_id DESC LIMIT 6'
);
$low = db_rows($conn, "SELECT item_id, description, quantity, reorder_threshold, stock_status FROM inventory_report WHERE is_active = 1 AND stock_status <> 'OK' ORDER BY quantity, description LIMIT 6");

$pageTitle = 'Dashboard';
$adminActive = 'dashboard';
$useChart = true;
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <div class="muted"><?= e(date('l, F j, Y')) ?></div>
  </div>
  <a class="btn btn-yellow" href="<?= e(url('admin/pos.php')) ?>"><i class="bi bi-cash-coin"></i> New walk-in sale</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-4"><div class="kpi"><div class="label">Sales today</div><div class="value"><?= money($salesToday) ?></div><div class="muted small"><?= $ordersToday ?> <?= $ordersToday === 1 ? 'order' : 'orders' ?></div></div></div>
  <div class="col-6 col-xl-4"><div class="kpi"><div class="label">Sales this month</div><div class="value"><?= money($salesMonth) ?></div><div class="muted small"><?= e(date('F Y')) ?></div></div></div>
  <div class="col-6 col-xl-4"><a class="text-decoration-none text-reset" href="<?= e(url('admin/orders.php?status=open')) ?>"><div class="kpi"><div class="label">Orders to handle</div><div class="value"><?= $pending ?></div><div class="muted small">Pending or processing</div></div></a></div>
  <div class="col-6 col-xl-4"><a class="text-decoration-none text-reset" href="<?= e(url('admin/report_inventory.php?filter=low')) ?>"><div class="kpi <?= $lowCount ? 'is-alert' : '' ?>"><div class="label">Items to reorder</div><div class="value"><?= $lowCount ?></div><div class="muted small">Low or out of stock</div></div></a></div>
  <div class="col-6 col-xl-4"><a class="text-decoration-none text-reset" href="<?= e(url('admin/lista.php')) ?>"><div class="kpi"><div class="label">Utang on the suki list</div><div class="value"><?= money($owed) ?></div><div class="muted small">Owed by all suki</div></div></a></div>
  <div class="col-6 col-xl-4"><a class="text-decoration-none text-reset" href="<?= e(url('admin/customers.php')) ?>"><div class="kpi"><div class="label">Customers</div><div class="value"><?= $customers ?></div><div class="muted small">Registered accounts</div></div></a></div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <section class="panel mb-4">
      <div class="panel-head"><h2>Sales, last 7 days</h2><a href="<?= e(url('admin/report_sales.php')) ?>">Full report</a></div>
      <div class="panel-body" style="height:280px"><canvas data-chart data-labels='<?= e(json_encode($labels)) ?>' data-values='<?= e(json_encode($values)) ?>' aria-label="Bar chart of sales for the last 7 days" role="img"></canvas></div>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Latest orders</h2><a href="<?= e(url('admin/orders.php')) ?>">All orders</a></div>
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Order</th><th>Customer</th><th class="text-end">Total</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $o): ?>
            <tr>
              <td><a class="fw-bold" href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>"><?= order_label((int) $o['orderinfo_id']) ?></a></td>
              <td><?= e(customer_name($o)) ?></td>
              <td class="text-end num"><?= money($o['total_amount']) ?></td>
              <td><?= status_badge($o['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="4" class="text-center muted py-4">No orders yet. Record a walk-in sale to get started.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <div class="col-xl-5">
    <section class="panel">
      <div class="panel-head"><h2>Running low</h2><a href="<?= e(url('admin/restock.php')) ?>">Restock</a></div>
      <?php if (!$low): ?>
        <div class="panel-body muted">Every item is above its reorder level.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($low as $l): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
              <div>
                <div class="fw-bold"><?= e($l['description']) ?></div>
                <?= stock_note((int) $l['quantity'], (int) $l['reorder_threshold']) ?>
              </div>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/restock.php?item=' . $l['item_id'])) ?>">Restock</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
