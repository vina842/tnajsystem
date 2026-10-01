<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$filter = $_GET['filter'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = ['is_active = 1'];
$params = [];
if ($filter === 'low') {
    $where[] = "stock_status <> 'OK'";
} elseif ($filter === 'out') {
    $where[] = "stock_status = 'Out of stock'";
}
if ($q !== '') {
    $where[] = '(description LIKE ? OR brand LIKE ? OR category LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$rows = db_rows($conn, 'SELECT * FROM inventory_report WHERE ' . implode(' AND ', $where) . ' ORDER BY category, description', $params);
$all = db_row(
    $conn,
    "SELECT COUNT(*) AS items, COALESCE(SUM(quantity), 0) AS units,
            COALESCE(SUM(quantity * cost_price), 0) AS at_cost, COALESCE(SUM(quantity * sell_price), 0) AS at_retail,
            COALESCE(SUM(stock_status = 'Reorder'), 0) AS low, COALESCE(SUM(stock_status = 'Out of stock'), 0) AS out_count
     FROM inventory_report WHERE is_active = 1"
);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Item', 'Category', 'Brand', 'In stock', 'Reorder level', 'Status', 'Cost price', 'Selling price', 'Value at cost'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, [$r['description'], $r['category'], $r['brand'], $r['quantity'], $r['reorder_threshold'], $r['stock_status'], $r['cost_price'], $r['sell_price'], $r['quantity'] * $r['cost_price']], ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Inventory report';
$adminActive = 'rinv';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div><h1 class="page-title">Inventory report</h1><div class="muted">As of <?= e(date('M j, Y g:i A')) ?></div></div>
  <div class="d-flex gap-2 no-print">
    <a class="btn btn-outline-primary" href="?<?= e(http_build_query(['filter' => $filter, 'q' => $q, 'export' => 'csv'])) ?>"><i class="bi bi-download"></i> Download CSV</a>
    <button class="btn btn-outline-primary" type="button" data-print><i class="bi bi-printer"></i> Print</button>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Products</div><div class="value"><?= (int) $all['items'] ?></div><div class="muted small"><?= (int) $all['units'] ?> pieces on hand</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Stock value at cost</div><div class="value"><?= money($all['at_cost']) ?></div><div class="muted small">At selling price <?= money($all['at_retail']) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi <?= $all['low'] ? 'is-alert' : '' ?>"><div class="label">Running low</div><div class="value"><?= (int) $all['low'] ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi <?= $all['out_count'] ? 'is-alert' : '' ?>"><div class="label">Out of stock</div><div class="value"><?= (int) $all['out_count'] ?></div></div></div>
</div>

<div class="filter-row no-print">
  <a href="?" class="<?= $filter === '' ? 'active' : '' ?>">All items</a>
  <a href="?filter=low" class="<?= $filter === 'low' ? 'active' : '' ?>">Low or out of stock</a>
  <a href="?filter=out" class="<?= $filter === 'out' ? 'active' : '' ?>">Out of stock</a>
</div>
<form class="row g-2 mb-3 no-print" method="get">
  <input type="hidden" name="filter" value="<?= e($filter) ?>">
  <div class="col-md-4"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search item, brand, or category" aria-label="Search"></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Search</button></div>
</form>

<div class="panel table-wrap">
  <table class="table mb-0">
    <thead><tr><th>Item</th><th>Category</th><th>Brand</th><th class="text-end">In stock</th><th class="text-end">Reorder level</th><th>Status</th><th class="text-end">Value at cost</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $cls = ['OK' => 'b-done', 'Reorder' => 'b-pending', 'Out of stock' => 'b-void'][$r['stock_status']]; ?>
      <tr>
        <td class="fw-bold"><?= e($r['description']) ?></td>
        <td><?= e($r['category'] ?? 'None') ?></td>
        <td><?= e($r['brand'] ?? 'None') ?></td>
        <td class="text-end num fw-bold"><?= (int) $r['quantity'] ?></td>
        <td class="text-end num"><?= (int) $r['reorder_threshold'] ?></td>
        <td><span class="pill <?= $cls ?>"><?= e($r['stock_status']) ?></span></td>
        <td class="text-end num"><?= money($r['quantity'] * $r['cost_price']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center muted py-5">Nothing matches. Clear the filters to see every item.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
