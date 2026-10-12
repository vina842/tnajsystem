<?php
include("../includes/config.php");
require_admin();

if (is_post()) {
    csrf_check();

    $qty = array_map('intval', (array) ($_POST['qty'] ?? []));
    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;

    $method = 'cash';
    if (($_POST['payment_method'] ?? '') === 'lista') {
        $method = 'lista';
    }

    $walkin = null;
    if (!$customerId) {
        $walkin = post_str('walkin_name', 100) ?: null;
    }

    try {
        $orderId = place_order($conn, $qty, [
            'customer_id' => $customerId,
            'walkin_name' => $walkin,
            'order_type' => 'walk_in',
            'payment_method' => $method,
            'created_by' => (int) $_SESSION['user_id'],
        ]);

        $total = db_val($conn, 'SELECT total_amount FROM orderinfo WHERE orderinfo_id = ?', [$orderId]);
        log_activity($conn, 'walk-in sale', order_label($orderId) . ' ' . money($total) . ' ' . $method);
        flash('success', 'Sale ' . order_label($orderId) . ' recorded for ' . money($total) . '.');
        redirect('admin/order.php?id=' . $orderId);
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (mysqli_sql_exception $e) {
        flash('danger', 'The sale could not be saved, and nothing was changed. Please try again.');
    }
}

$items = db_rows($conn, 'SELECT i.item_id, i.description, i.sell_price, c.name AS category, s.quantity
                         FROM item i JOIN stock s ON s.item_id = i.item_id
                         LEFT JOIN category c ON c.category_id = i.category_id
                         WHERE i.is_active = 1
                         ORDER BY c.name, i.description');

$customers = db_rows($conn, 'SELECT c.customer_id, c.fname, c.lname, la.credit_limit - la.balance AS available, la.status
                             FROM customer c LEFT JOIN lista_account la ON la.customer_id = c.customer_id
                             ORDER BY c.lname, c.fname');

$pageTitle = 'Walk-in sale';
$adminActive = 'pos';
include("../includes/admin_header.php");
?>
<h1>Walk-in sale</h1>
<p>Record what the customer takes at the counter. Payment is collected in person.</p>

<form method="post">
    <?= csrf_field() ?>

    <h2>Items</h2>
    <table border="1">
        <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Quantity</th>
        </tr>
        <?php foreach ($items as $it) { ?>
            <?php $out = ((int) $it['quantity'] <= 0); ?>
            <tr>
                <td><?= e($it['description']) ?></td>
                <td><?= e($it['category'] ?? '') ?></td>
                <td><?= money($it['sell_price']) ?></td>
                <td><?= (int) $it['quantity'] ?></td>
                <td>
                    <input type="number" name="qty[<?= (int) $it['item_id'] ?>]" value="0" min="0"
                           max="<?= max(0, (int) $it['quantity']) ?>" <?= $out ? 'disabled' : '' ?>
                           aria-label="Quantity for <?= e($it['description']) ?>">
                </td>
            </tr>
        <?php } ?>
    </table>

    <h2>Sale</h2>
    <p>
        <label for="pos-customer">Customer</label><br>
        <select id="pos-customer" name="customer_id">
            <option value="">Walk-in (no account)</option>
            <?php foreach ($customers as $c) { ?>
                <?php $hasList = ($c['available'] !== null && $c['status'] === 'active'); ?>
                <option value="<?= (int) $c['customer_id'] ?>">
                    <?= e($c['lname'] . ', ' . $c['fname']) ?>
                    <?php if ($hasList) { ?>(suki, <?= money(max(0, (float) $c['available'])) ?> left)<?php } ?>
                </option>
            <?php } ?>
        </select>
    </p>

    <p>
        <label for="pos-walkin">Name for the receipt (optional)</label><br>
        <input id="pos-walkin" name="walkin_name" maxlength="100">
    </p>

    <p>Payment:</p>
    <p>
        <label><input type="radio" name="payment_method" value="cash" checked> Cash</label><br>
        <label><input type="radio" name="payment_method" value="lista"> Suki list (utang, registered customers only)</label>
    </p>

    <p><button type="submit">Record sale</button></p>
</form>

<?php

include("../includes/admin_footer.php"); ?>