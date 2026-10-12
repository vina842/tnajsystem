<?php
include("../includes/config.php");
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['item_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        $item = db_row($conn, 'SELECT * FROM item WHERE item_id = ?', [$id]);
        if (!$item) {
            throw new RuntimeException('Item not found.');
        }

        if ($action === 'delete') {
            $sold = (int) db_val($conn, 'SELECT COUNT(*) FROM orderline WHERE item_id = ?', [$id]);
            if ($sold > 0) {
                db_run($conn, 'UPDATE item SET is_active = 0 WHERE item_id = ?', [$id]);
                log_activity($conn, 'item hidden', $item['description']);
                flash('warning', $item['description'] . ' has sales history, so it was hidden from the shop instead of deleted. Your reports stay correct.');
            } else {
                db_run($conn, 'DELETE FROM item WHERE item_id = ?', [$id]);
                delete_image($item['img_path']);
                log_activity($conn, 'item deleted', $item['description']);
                flash('success', $item['description'] . ' was deleted.');
            }
        } elseif ($action === 'toggle') {
            db_run($conn, 'UPDATE item SET is_active = 1 - is_active WHERE item_id = ?', [$id]);
            if ($item['is_active']) {
                flash('success', $item['description'] . ' is now hidden from the shop.');
            } else {
                flash('success', $item['description'] . ' is now visible in the shop.');
            }
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/items.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['category'] ?? 0);
$view = $_GET['view'] ?? '';

$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(i.description LIKE ? OR b.name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($cat > 0) {
    $where[] = 'i.category_id = ?';
    $params[] = $cat;
}
if ($view === 'low') {
    $where[] = 's.quantity <= s.reorder_threshold';
} elseif ($view === 'hidden') {
    $where[] = 'i.is_active = 0';
}

$items = db_rows($conn, 'SELECT i.*, c.name AS category, b.name AS brand, s.quantity, s.reorder_threshold
                         FROM item i JOIN stock s ON s.item_id = i.item_id
                         LEFT JOIN category c ON c.category_id = i.category_id
                         LEFT JOIN brand b ON b.brand_id = i.brand_id
                         WHERE ' . implode(' AND ', $where) . '
                         ORDER BY i.description', $params);
$categories = db_rows($conn, 'SELECT * FROM category ORDER BY name');

$pageTitle = 'Items';
$adminActive = 'items';
include("../includes/admin_header.php");
?>
<h1>Items</h1>
<p><a href="<?= e(url('admin/item_form.php')) ?>">Add item</a></p>

<form method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or brand" aria-label="Search items">

    <select name="category" aria-label="Category">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c) { ?>
            <option value="<?= (int) $c['category_id'] ?>" <?= $cat === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php } ?>
    </select>

    <select name="view" aria-label="Show">
        <option value="">All items</option>
        <option value="low" <?= $view === 'low' ? 'selected' : '' ?>>Low or out of stock</option>
        <option value="hidden" <?= $view === 'hidden' ? 'selected' : '' ?>>Hidden from the shop</option>
    </select>

    <button type="submit">Filter</button>
</form>

<table border="1">
    <tr>
        <th>Item</th>
        <th>Category</th>
        <th>Cost</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Shop</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($items as $it) { ?>
        <tr>
            <td><?= e($it['description']) ?><br><?= e($it['brand'] ?? 'No brand') ?></td>
            <td><?= e($it['category'] ?? 'None') ?></td>
            <td><?= money($it['cost_price']) ?></td>
            <td><?= money($it['sell_price']) ?></td>
            <td><?= e(stock_note((int) $it['quantity'], (int) $it['reorder_threshold'])) ?></td>
            <td><?= $it['is_active'] ? 'Visible' : 'Hidden' ?></td>
            <td>
                <a href="<?= e(url('admin/restock.php?item=' . $it['item_id'])) ?>">Restock</a> |
                <a href="<?= e(url('admin/item_form.php?id=' . $it['item_id'])) ?>">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this item? Items with sales history are hidden instead.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" value="<?= (int) $it['item_id'] ?>">
                    <button type="submit" name="action" value="delete">Delete</button>
                </form>
            </td>
        </tr>
    <?php } ?>
    <?php if (!$items) { ?>
        <tr>
            <td colspan="7">No items match. <a href="<?= e(url('admin/item_form.php')) ?>">Add an item</a> or clear the filters.</td>
        </tr>
    <?php } ?>
</table>

<?php

include("../includes/admin_footer.php"); ?>