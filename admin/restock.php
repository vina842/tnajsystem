<?php
include("../includes/config.php");
require_admin();

if (is_post()) {
    csrf_check();

    try {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $qty = post_int('qty', 1);

        $name = db_val($conn, 'SELECT description FROM item WHERE item_id = ?', [$itemId]);
        if (!$name) {
            throw new RuntimeException('Pick an item to restock.');
        }

        restock_item($conn, $itemId, $qty);
        log_activity($conn, 'restock', $name . ' +' . $qty);
        flash('success', 'Added ' . $qty . ' to ' . $name . '.');
        redirect('admin/restock.php');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$selected = (int) ($_GET['item'] ?? 0);

$items = db_rows($conn, 'SELECT i.item_id, i.description, s.quantity
                         FROM item i JOIN stock s ON s.item_id = i.item_id
                         ORDER BY i.description');

$moves = db_rows($conn, 'SELECT m.*, i.description
                         FROM stock_movement m JOIN item i ON i.item_id = m.item_id
                         ORDER BY m.movement_id DESC LIMIT 30');

$pageTitle = 'Restock';
$adminActive = 'restock';
include("../includes/admin_header.php");
?>
<h1>Restock</h1>

<form method="post">
    <?= csrf_field() ?>

    <p>
        <label for="item_id">Item</label><br>
        <select id="item_id" name="item_id" required>
            <option value="">Choose an item</option>
            <?php foreach ($items as $i) { ?>
                <option value="<?= (int) $i['item_id'] ?>" <?= $selected === (int) $i['item_id'] ? 'selected' : '' ?>>
                    <?= e($i['description']) ?> (<?= (int) $i['quantity'] ?> in stock)
                </option>
            <?php } ?>
        </select>
    </p>

    <p>
        <label for="qty">Pieces received</label><br>
        <input type="number" id="qty" name="qty" min="1" required>
    </p>

    <p><button type="submit">Add to stock</button></p>
</form>

<h2>Recent stock changes</h2>
<table border="1">
    <tr>
        <th>When</th>
        <th>Item</th>
        <th>Reason</th>
        <th>Change</th>
        <th>Stock after</th>
    </tr>
    <?php foreach ($moves as $m) { ?>
        <tr>
            <td><?= e(format_dt($m['created_at'], 'M j, g:i A')) ?></td>
            <td><?= e($m['description']) ?></td>
            <td><?= e(ucfirst($m['reason'])) ?><?= $m['ref_id'] ? ' ' . order_label((int) $m['ref_id']) : '' ?></td>
            <td><?= $m['qty_change'] > 0 ? '+' : '' ?><?= (int) $m['qty_change'] ?></td>
            <td><?= (int) $m['qty_after'] ?></td>
        </tr>
    <?php } ?>
    <?php if (!$moves) { ?>
        <tr><td colspan="5">No stock changes yet.</td></tr>
    <?php } ?>
</table>

<?php

include("../includes/admin_footer.php"); ?>