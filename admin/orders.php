<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$method = $_GET['method'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));

$where = ['1 = 1'];
$params = [];
if ($status === 'open') {
    $where[] = "o.status IN ('Pending','Processing')";
} elseif (in_array($status, ['Pending', 'Processing', 'Completed', 'Canceled'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if (in_array($type, ['storefront', 'walk_in'], true)) {
    $where[] = 'o.order_type = ?';
    $params[] = $type;
}
if (in_array($method, ['cash', 'lista'], true)) {
    $where[] = 'o.payment_method = ?';
    $params[] = $method;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'DATE(o.date_placed) >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'DATE(o.date_placed) <= ?';
    $params[] = $to;
}
if ($q !== '') {
    $where[] = '(o.orderinfo_id = ? OR c.fname LIKE ? OR c.lname LIKE ? OR o.walkin_name LIKE ?)';
    array_push($params, (int) ltrim($q, '#0'), "%$q%", "%$q%", "%$q%");
}

$orders = db_rows(
    $conn,
    'SELECT o.*, c.fname, c.lname, (SELECT COALESCE(SUM(quantity), 0) FROM orderline WHERE orderinfo_id = o.orderinfo_id) AS items
     FROM orderinfo o LEFT JOIN customer c ON c.customer_id = o.customer_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY o.orderinfo_id DESC LIMIT 200',
    $params
);

$pageTitle = 'Orders';
$adminActive = 'orders';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <h1 class="page-title">Orders</h1>
  <a class="btn btn-yellow" href="<?= e(url('admin/pos.php')) ?>"><i class="bi bi-cash-coin"></i> New walk-in sale</a>
</div>

<form class="panel panel-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label" for="q">Order number or customer</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status">
        <option value="">All</option>
        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>To handle</option>
        <?php foreach (['Pending', 'Processing', 'Completed', 'Canceled'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="type">Type</label>
      <select class="form-select" id="type" name="type">
        <option value="">All</option>
        <option value="storefront" <?= $type === 'storefront' ? 'selected' : '' ?>>Online order</option>
        <option value="walk_in" <?= $type === 'walk_in' ? 'selected' : '' ?>>Walk-in</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="method">Payment</label>
      <select class="form-select" id="method" name="method">
        <option value="">All</option>
        <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="lista" <?= $method === 'lista' ? 'selected' : '' ?>>Suki list</option>
      </select>
    </div>
    <div class="w-100 d-none d-md-block"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-primary" type="submit">Filter</button></div>
  </div>
</form>

<div class="panel table-wrap">
  <table class="table mb-0">
    <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Type</th><th class="text-end">Items</th><th class="text-end">Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td class="fw-bold"><a href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>"><?= order_label((int) $o['orderinfo_id']) ?></a></td>
        <td class="text-nowrap"><?= e(format_dt($o['date_placed'], 'M j, g:i A')) ?></td>
        <td><?= e(customer_name($o)) ?></td>
        <td><?= $o['order_type'] === 'walk_in' ? 'Walk-in' : 'Online' ?></td>
        <td class="text-end num"><?= (int) $o['items'] ?></td>
        <td class="text-end num fw-bold"><?= money($o['total_amount']) ?></td>
        <td><?= status_badge($o['status']) ?></td>
        <td><?= payment_badge($o['payment_method'], $o['payment_status']) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="9" class="text-center muted py-5">No orders match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (count($orders) === 200): ?><p class="muted small mt-2">Showing the latest 200. Narrow the dates to see older orders.</p><?php endif; ?>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
