<?php
include("../includes/config.php");
require_admin();

$salesToday = (float) db_val($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM orderinfo
                                     WHERE status <> 'Canceled' AND DATE(date_placed) = CURDATE()");

$salesMonth = (float) db_val($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM orderinfo
                                     WHERE status <> 'Canceled'
                                     AND YEAR(date_placed) = YEAR(CURDATE())
                                     AND MONTH(date_placed) = MONTH(CURDATE())");

$ordersToday = (int) db_val($conn, "SELECT COUNT(*) FROM orderinfo
                                    WHERE status <> 'Canceled' AND DATE(date_placed) = CURDATE()");

$pending = (int) db_val($conn, "SELECT COUNT(*) FROM orderinfo WHERE status IN ('Pending','Processing')");

// Items at or below their reorder level (read from the stock tables, not the view)
$low = db_rows($conn, "SELECT i.item_id, i.description, s.quantity, s.reorder_threshold
                       FROM item i JOIN stock s ON s.item_id = i.item_id
                       WHERE i.is_active = 1 AND s.quantity <= s.reorder_threshold
                       ORDER BY s.quantity, i.description LIMIT 6");
$lowCount = (int) db_val($conn, "SELECT COUNT(*) FROM item i JOIN stock s ON s.item_id = i.item_id
                                 WHERE i.is_active = 1 AND s.quantity <= s.reorder_threshold");

$owed = (float) db_val($conn, 'SELECT COALESCE(SUM(balance), 0) FROM lista_account');
$customers = (int) db_val($conn, 'SELECT COUNT(*) FROM customer');

$recent = db_rows($conn, 'SELECT o.*, c.fname, c.lname
                          FROM orderinfo o LEFT JOIN customer c ON c.customer_id = o.customer_id
                          ORDER BY o.orderinfo_id DESC LIMIT 6');

$pageTitle = 'Dashboard';
$adminActive = 'dashboard';
include("../includes/admin_header.php");
?>
<h1>Dashboard</h1>
<p><?= e(date('l, F j, Y')) ?></p>
<p><a href="<?= e(url('admin/pos.php')) ?>">New walk-in sale</a></p>

<h2>Summary</h2>
<p>Sales today: <strong><?= money($salesToday) ?></strong> (<?= $ordersToday ?> <?= $ordersToday === 1 ? 'order' : 'orders' ?>)</p>
<p>Sales this month (<?= e(date('F Y')) ?>): <strong><?= money($salesMonth) ?></strong></p>
<p>Orders to handle: <strong><?= $pending ?></strong> (pending or processing) - <a href="<?= e(url('admin/orders.php?status=open')) ?>">View</a></p>
<p>Items to reorder: <strong><?= $lowCount ?></strong> (low or out of stock) - <a href="<?= e(url('admin/items.php?view=low')) ?>">View</a></p>
<p>Utang on the suki list: <strong><?= money($owed) ?></strong> - <a href="<?= e(url('admin/lista.php')) ?>">View</a></p>
<p>Customers: <strong><?= $customers ?></strong> - <a href="<?= e(url('admin/customers.php')) ?>">View</a></p>

<h2>Latest orders</h2>
<p><a href="<?= e(url('admin/orders.php')) ?>">All orders</a></p>
<table border="1">
    <tr>
        <th>Order</th>
        <th>Customer</th>
        <th>Total</th>
        <th>Status</th>
    </tr>
    <?php foreach ($recent as $o) { ?>
        <tr>
            <td><a href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>"><?= order_label((int) $o['orderinfo_id']) ?></a></td>
            <td><?= e(customer_name($o)) ?></td>
            <td><?= money($o['total_amount']) ?></td>
            <td><?= status_badge($o['status']) ?></td>
        </tr>
    <?php } ?>
    <?php if (!$recent) { ?>
        <tr><td colspan="4">No orders yet. Record a walk-in sale to get started.</td></tr>
    <?php } ?>
</table>

<h2>Running low</h2>
<p><a href="<?= e(url('admin/restock.php')) ?>">Restock</a></p>
<?php if (!$low) { ?>
    <p>Every item is above its reorder level.</p>
<?php } else { ?>
    <table border="1">
        <tr>
            <th>Item</th>
            <th>Stock</th>
            <th></th>
        </tr>
        <?php foreach ($low as $l) { ?>
            <tr>
                <td><?= e($l['description']) ?></td>
                <td><?= e(stock_note((int) $l['quantity'], (int) $l['reorder_threshold'])) ?></td>
                <td><a href="<?= e(url('admin/restock.php?item=' . $l['item_id'])) ?>">Restock</a></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<?php

include("../includes/admin_footer.php"); ?>