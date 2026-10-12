<?php
include("../includes/config.php");
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
    $params[] = (int) ltrim($q, '#0');
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$orders = db_rows($conn, 'SELECT o.*, c.fname, c.lname,
                                 (SELECT COALESCE(SUM(quantity), 0) FROM orderline WHERE orderinfo_id = o.orderinfo_id) AS items
                          FROM orderinfo o LEFT JOIN customer c ON c.customer_id = o.customer_id
                          WHERE ' . implode(' AND ', $where) . '
                          ORDER BY o.orderinfo_id DESC LIMIT 200', $params);

$pageTitle = 'Orders';
$adminActive = 'orders';
include("../includes/admin_header.php");
?>
<h1>Orders</h1>
<p><a href="<?= e(url('admin/pos.php')) ?>">New walk-in sale</a></p>

<form method="get">
    <p>
        <label for="q">Order number or customer</label><br>
        <input id="q" name="q" value="<?= e($q) ?>">
    </p>

    <p>
        <label for="status">Status</label><br>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>To handle</option>
            <?php foreach (['Pending', 'Processing', 'Completed', 'Canceled'] as $s) { ?>
                <option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php } ?>
        </select>
    </p>

    <p>
        <label for="type">Type</label><br>
        <select id="type" name="type">
            <option value="">All</option>
            <option value="storefront" <?= $type === 'storefront' ? 'selected' : '' ?>>Online order</option>
            <option value="walk_in" <?= $type === 'walk_in' ? 'selected' : '' ?>>Walk-in</option>
        </select>
    </p>

    <p>
        <label for="method">Payment</label><br>
        <select id="method" name="method">
            <option value="">All</option>
            <option value="cash" <?= $method === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="lista" <?= $method === 'lista' ? 'selected' : '' ?>>Suki list</option>
        </select>
    </p>

    <p>
        <label for="from">From</label><br>
        <input type="date" id="from" name="from" value="<?= e($from) ?>">
    </p>

    <p>
        <label for="to">To</label><br>
        <input type="date" id="to" name="to" value="<?= e($to) ?>">
    </p>

    <p><button type="submit">Filter</button></p>
</form>

<table border="1">
    <tr>
        <th>Order</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Type</th>
        <th>Items</th>
        <th>Total</th>
        <th>Status</th>
        <th>Payment</th>
        <th></th>
    </tr>
    <?php foreach ($orders as $o) { ?>
        <tr>
            <td><a href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>"><?= order_label((int) $o['orderinfo_id']) ?></a></td>
            <td><?= e(format_dt($o['date_placed'], 'M j, g:i A')) ?></td>
            <td><?= e(customer_name($o)) ?></td>
            <td><?= $o['order_type'] === 'walk_in' ? 'Walk-in' : 'Online' ?></td>
            <td><?= (int) $o['items'] ?></td>
            <td><?= money($o['total_amount']) ?></td>
            <td><?= status_badge($o['status']) ?></td>
            <td><?= payment_badge($o['payment_method'], $o['payment_status']) ?></td>
            <td><a href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>">Open</a></td>
        </tr>
    <?php } ?>
    <?php if (!$orders) { ?>
        <tr><td colspan="9">No orders match these filters.</td></tr>
    <?php } ?>
</table>

<?php if (count($orders) === 200) { ?>
    <p>Showing the latest 200. Narrow the dates to see older orders.</p>
<?php } ?>

<?php

include("../includes/admin_footer.php"); ?>