<?php
include("../includes/config.php");
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$item = null;
if ($id) {
    $item = db_row($conn, 'SELECT i.*, s.quantity, s.reorder_threshold
                           FROM item i JOIN stock s ON s.item_id = i.item_id
                           WHERE i.item_id = ?', [$id]);
    if (!$item) {
        flash('danger', 'Item not found.');
        redirect('admin/items.php');
    }
}

if ($item) {
    $form = $item;
} else {
    $form = [
        'description' => '',
        'category_id' => '',
        'brand_id' => '',
        'supplier_id' => '',
        'cost_price' => '',
        'sell_price' => '',
        'quantity' => 0,
        'reorder_threshold' => 10,
        'is_active' => 1,
    ];
}

if (is_post()) {
    csrf_check();

    try {
        $desc = post_str('description', 100);
        if ($desc === '') {
            throw new RuntimeException('Enter the item name.');
        }
        $cost = post_money('cost_price');
        $sell = post_money('sell_price');
        $qty = post_int('quantity', 0);
        $threshold = post_int('reorder_threshold', 0);
        $catId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $brandId = (int) ($_POST['brand_id'] ?? 0) ?: null;
        $supId = (int) ($_POST['supplier_id'] ?? 0) ?: null;
        $active = isset($_POST['is_active']) ? 1 : 0;

        $form['description'] = $desc;
        $form['category_id'] = $catId;
        $form['brand_id'] = $brandId;
        $form['supplier_id'] = $supId;
        $form['cost_price'] = $cost;
        $form['sell_price'] = $sell;
        $form['quantity'] = $qty;
        $form['reorder_threshold'] = $threshold;
        $form['is_active'] = $active;

        $newImage = upload_image($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);

        $conn->begin_transaction();
        try {
            if ($item) {
                $imgPath = $newImage ?: $item['img_path'];
                if (isset($_POST['remove_image']) && !$newImage) {
                    $imgPath = null;
                }
                db_run($conn, 'UPDATE item SET description = ?, category_id = ?, brand_id = ?, supplier_id = ?, cost_price = ?, sell_price = ?, img_path = ?, is_active = ? WHERE item_id = ?',
                    [$desc, $catId, $brandId, $supId, $cost, $sell, $imgPath, $active, $id]);
                db_run($conn, 'UPDATE stock SET quantity = ?, reorder_threshold = ? WHERE item_id = ?', [$qty, $threshold, $id]);
                $conn->commit();

                if (($newImage || isset($_POST['remove_image'])) && $item['img_path'] && $item['img_path'] !== $imgPath) {
                    delete_image($item['img_path']);
                }
                log_activity($conn, 'item edited', $desc);
                flash('success', $desc . ' was saved.');
            } else {
                db_run($conn, 'INSERT INTO item (description, category_id, brand_id, supplier_id, cost_price, sell_price, img_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$desc, $catId, $brandId, $supId, $cost, $sell, $newImage, $active]);
                $newId = (int) $conn->insert_id;
                db_run($conn, 'INSERT INTO stock (item_id, quantity, reorder_threshold) VALUES (?, ?, ?)', [$newId, $qty, $threshold]);
                $conn->commit();
                log_activity($conn, 'item added', $desc);
                flash('success', $desc . ' was added.');
            }
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        redirect('admin/items.php');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$categories = db_rows($conn, 'SELECT * FROM category ORDER BY name');
$brands = db_rows($conn, 'SELECT * FROM brand ORDER BY name');
$suppliers = db_rows($conn, 'SELECT * FROM supplier ORDER BY name');

$pageTitle = $item ? 'Edit item' : 'Add item';
$adminActive = 'items';
include("../includes/admin_header.php");
?>
<h1><?= $item ? 'Edit item' : 'Add item' ?></h1>
<p><a href="<?= e(url('admin/items.php')) ?>">Back to items</a></p>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <p>
        <label for="description">Item name</label><br>
        <input id="description" name="description" value="<?= e($form['description']) ?>" maxlength="100" required>
    </p>

    <p>
        <label for="category_id">Category</label><br>
        <select id="category_id" name="category_id">
            <option value="0">No category</option>
            <?php foreach ($categories as $r) { ?>
                <option value="<?= (int) $r['category_id'] ?>" <?= (int) $form['category_id'] === (int) $r['category_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php } ?>
        </select>
    </p>

    <p>
        <label for="brand_id">Brand</label><br>
        <select id="brand_id" name="brand_id">
            <option value="0">No brand</option>
            <?php foreach ($brands as $r) { ?>
                <option value="<?= (int) $r['brand_id'] ?>" <?= (int) $form['brand_id'] === (int) $r['brand_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php } ?>
        </select>
    </p>

    <p>
        <label for="supplier_id">Supplier</label><br>
        <select id="supplier_id" name="supplier_id">
            <option value="0">No supplier</option>
            <?php foreach ($suppliers as $r) { ?>
                <option value="<?= (int) $r['supplier_id'] ?>" <?= (int) $form['supplier_id'] === (int) $r['supplier_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php } ?>
        </select>
    </p>

    <p>
        <label for="cost_price">Cost price (what you pay)</label><br>
        <input id="cost_price" name="cost_price" inputmode="decimal" value="<?= e($form['cost_price']) ?>" required>
    </p>

    <p>
        <label for="sell_price">Selling price</label><br>
        <input id="sell_price" name="sell_price" inputmode="decimal" value="<?= e($form['sell_price']) ?>" required>
    </p>

    <p>
        <label for="quantity"><?= $item ? 'Stock on hand' : 'Starting stock' ?></label><br>
        <input type="number" min="0" id="quantity" name="quantity" value="<?= (int) $form['quantity'] ?>" required>
        <?php if ($item) { ?>
            <br>To add new deliveries, use <a href="<?= e(url('admin/restock.php?item=' . $id)) ?>">Restock</a> so the history is clear.
        <?php } ?>
    </p>

    <p>
        <label for="reorder_threshold">Reorder level</label><br>
        <input type="number" min="0" id="reorder_threshold" name="reorder_threshold" value="<?= (int) $form['reorder_threshold'] ?>" required><br>
        The item shows as low when stock is at or below this number.
    </p>

    <p>
        <label for="image">Photo (JPG, PNG, or WEBP, up to 3 MB)</label><br>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    </p>
    <?php if ($item && $item['img_path']) { ?>
        <p>
            <label><input type="checkbox" name="remove_image"> Remove current photo</label>
        </p>
    <?php } ?>

    <p>
        <label><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Show in the shop</label><br>
        Turn off to hide the item without deleting it.
    </p>

    <p><button type="submit"><?= $item ? 'Save changes' : 'Add item' ?></button></p>
</form>

<?php

include("../includes/admin_footer.php"); ?>