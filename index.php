<?php
include("includes/config.php");

$q = trim($_GET['q'] ?? '');

// Load active items, filtered by the search box if used
if ($q !== '') {
    $items = db_rows($conn, 'SELECT i.item_id, i.description, i.sell_price, s.quantity
                             FROM item i JOIN stock s ON s.item_id = i.item_id
                             WHERE i.is_active = 1 AND i.description LIKE ?
                             ORDER BY i.description', ['%' . $q . '%']);
} else {
    $items = db_rows($conn, 'SELECT i.item_id, i.description, i.sell_price, s.quantity
                             FROM item i JOIN stock s ON s.item_id = i.item_id
                             WHERE i.is_active = 1
                             ORDER BY i.description');
}

$pageTitle = 'Shop';
$active = 'shop';
include("includes/header.php");
?>
<h1>Products</h1>

<?php if ($q !== '') { ?>
    <p>Results for "<?= e($q) ?>" | <a href="<?= e(url('index.php')) ?>">Show all</a></p>
<?php } ?>

<?php if (!$items) { ?>
    <p>No products found.</p>
<?php } ?>

<?php foreach ($items as $row) { ?>
    <h3><?= e($row['description']) ?></h3>
    <p>Price: <?= money($row['sell_price']) ?></p>

    <?php if ((int) $row['quantity'] <= 0) { ?>
        <p>Out of stock</p>
    <?php } else { ?>
        <form action="<?= e(url('cart.php')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
            <input type="hidden" name="back" value="<?= e(url('index.php')) ?>">
            <label>Quantity
                <input type="number" name="qty" value="1" min="1" max="<?= (int) $row['quantity'] ?>">
            </label>
            <button type="submit">Add</button>
        </form>
    <?php } ?>
    <hr>
<?php } ?>

<?php

include("includes/footer.php"); ?>