<?php
include("includes/config.php");
require_login();

$id = (int) ($_GET['id'] ?? 0);

if (is_admin()) {
    redirect('admin/order.php?id=' . $id);
}

$customer = current_customer($conn);
$order = null;
if ($customer) {
    $order = db_row($conn, 'SELECT * FROM orderinfo WHERE orderinfo_id = ? AND customer_id = ?', [$id, (int) $customer['customer_id']]);
}

if (!$order) {
    http_response_code(404);
    flash('danger', 'We could not find that order.');
    redirect('orders.php');
}

$lines = db_rows($conn, 'SELECT ol.quantity, ol.unit_price, i.description
                         FROM orderline ol JOIN item i ON i.item_id = ol.item_id
                         WHERE ol.orderinfo_id = ? ORDER BY i.description', [$id]);

$pageTitle = 'Order ' . order_label($id);
$active = 'orders';
include("includes/header.php");
?>
<h1>Order <?= order_label($id) ?></h1>
<p>Placed <?= e(format_dt($order['date_placed'])) ?></p>
<p>Status: <?= e($order['status']) ?> | Payment: <?= e($order['payment_method']) ?> (<?= e($order['payment_status']) ?>)</p>

<?php if ($order['status'] === 'Pending' || $order['status'] === 'Processing') { ?>
    <p>
        Show order number <strong><?= order_label($id) ?></strong> at the counter to pick up your items<?= $order['payment_method'] === 'cash' ? ' and pay' : '' ?>.
    </p>
<?php } ?>

<table border="1">
    <tr>
        <th>Item</th>
        <th>Price</th>
        <th>Quantity</th>
        <th>Subtotal</th>
    </tr>
    <?php foreach ($lines as $l) { ?>
        <tr>
            <td><?= e($l['description']) ?></td>
            <td><?= money($l['unit_price']) ?></td>
            <td><?= (int) $l['quantity'] ?></td>
            <td><?= money($l['unit_price'] * $l['quantity']) ?></td>
        </tr>
    <?php } ?>
    <tr>
        <td colspan="3"><strong>Total</strong></td>
        <td><strong><?= money($order['total_amount']) ?></strong></td>
    </tr>
</table>

<p><a href="<?= e(url('orders.php')) ?>">Back to my orders</a></p>

<?php 

include("includes/footer.php"); ?>