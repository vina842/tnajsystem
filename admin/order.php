<?php
include("../includes/config.php");
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'status') {
            $new = (string) ($_POST['status'] ?? '');
            set_order_status($conn, $id, $new);
            log_activity($conn, 'order ' . strtolower($new), order_label($id));

            $message = 'Order ' . order_label($id) . ' is now ' . strtolower($new) . '.';
            if ($new === 'Canceled') {
                $message .= ' Stock was returned and any suki charge was reversed.';
            }
            flash('success', $message);

        } elseif ($action === 'paid') {
            mark_order_paid($conn, $id);
            log_activity($conn, 'order paid', order_label($id));
            flash('success', 'Marked as paid.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (mysqli_sql_exception $e) {
        flash('danger', 'That did not go through, and nothing was changed. Please try again.');
    }
    redirect('admin/order.php?id=' . $id);
}

$order = db_row($conn, 'SELECT o.*, c.fname, c.lname, c.phone, c.addressline, c.town, c.customer_id AS cid, u.email AS staff
                        FROM orderinfo o
                        LEFT JOIN customer c ON c.customer_id = o.customer_id
                        LEFT JOIN users u ON u.user_id = o.created_by
                        WHERE o.orderinfo_id = ?', [$id]);
if (!$order) {
    flash('danger', 'Order not found.');
    redirect('admin/orders.php');
}

$lines = db_rows($conn, 'SELECT ol.quantity, ol.unit_price, i.description
                         FROM orderline ol JOIN item i ON i.item_id = ol.item_id
                         WHERE ol.orderinfo_id = ? ORDER BY i.description', [$id]);
$canceled = ($order['status'] === 'Canceled');

$pageTitle = 'Order ' . order_label($id);
$adminActive = 'orders';
include("../includes/admin_header.php");
?>
<h1>Order <?= order_label($id) ?></h1>
<p><?= $order['order_type'] === 'walk_in' ? 'Walk-in sale' : 'Online order' ?>, <?= e(format_dt($order['date_placed'])) ?></p>
<p>
    <button type="button" onclick="window.print()">Print receipt</button>
    <a href="<?= e(url('admin/orders.php')) ?>">All orders</a>
</p>

<h2>Items</h2>
<table border="1">
    <tr>
        <th>Item</th>
        <th>Price</th>
        <th>Qty</th>
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

<h2>Details</h2>
<p>Status: <?= status_badge($order['status']) ?> | Payment: <?= payment_badge($order['payment_method'], $order['payment_status']) ?></p>
<p>Customer: <strong><?= e(customer_name($order)) ?></strong></p>
<?php if ($order['cid']) { ?>
    <p><?= e(trim($order['phone'] . ' ' . $order['addressline'] . ' ' . $order['town'])) ?></p>
    <p><a href="<?= e(url('admin/customer.php?id=' . $order['cid'])) ?>">Open customer record</a></p>
<?php } else { ?>
    <p>No account</p>
<?php } ?>
<?php if ($order['staff']) { ?>
    <p>Recorded by <?= e($order['staff']) ?></p>
<?php } ?>
<?php if ($order['date_completed']) { ?>
    <p>Completed <?= e(format_dt($order['date_completed'])) ?></p>
<?php } ?>

<h2>Update order</h2>
<?php if ($canceled) { ?>
    <p>This order was canceled. Stock and any suki charge were returned.</p>
<?php } else { ?>
    <?php foreach (['Processing' => 'Mark as processing', 'Completed' => 'Mark as completed'] as $s => $label) { ?>
        <?php if ($order['status'] !== $s) { ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="status" value="<?= $s ?>">
                <p><button type="submit"><?= $label ?></button></p>
            </form>
        <?php } ?>
    <?php } ?>

    <?php if ($order['payment_method'] === 'cash' && $order['payment_status'] === 'unpaid') { ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="paid">
            <p><button type="submit">Mark as paid (cash received)</button></p>
        </form>
    <?php } ?>

    <form method="post" onsubmit="return confirm('Cancel this order? Stock goes back on the shelf.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="status" value="Canceled">
        <p><button type="submit">Cancel this order</button></p>
    </form>
<?php } ?>

<?php

include("../includes/admin_footer.php"); ?>