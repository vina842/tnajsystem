<?php
include("includes/config.php");
require_login();

// Admins view orders on the admin page
if (is_admin()) {
    redirect('admin/orders.php');
}

$customer = current_customer($conn);
$orders = [];
if ($customer) {
    $orders = db_rows($conn, 'SELECT o.*, (SELECT SUM(quantity) FROM orderline WHERE orderinfo_id = o.orderinfo_id) AS items
                              FROM orderinfo o
                              WHERE o.customer_id = ?
                              ORDER BY o.date_placed DESC, o.orderinfo_id DESC', [(int) $customer['customer_id']]);
}

$pageTitle = 'My orders';
$active = 'orders';
include("includes/header.php");
?>
<h1>My orders</h1>

<?php if (!$orders) { ?>
    <p>No orders yet. When you place an order, it will show up here.</p>
    <p><a href="<?= e(url('index.php')) ?>">Start shopping</a></p>
<?php } else { ?>
    <table border="1">
        <tr>
            <th>Order</th>
            <th>Date</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th></th>
        </tr>
        <?php foreach ($orders as $o) { ?>
            <tr>
                <td><?= order_label((int) $o['orderinfo_id']) ?></td>
                <td><?= e(format_dt($o['date_placed'])) ?></td>
                <td><?= (int) $o['items'] ?></td>
                <td><?= money($o['total_amount']) ?></td>
                <td><?= e($o['status']) ?></td>
                <td><?= e($o['payment_method']) ?> (<?= e($o['payment_status']) ?>)</td>
                <td><a href="<?= e(url('order.php?id=' . $o['orderinfo_id'])) ?>">View</a></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<?php 

include("includes/footer.php"); ?>