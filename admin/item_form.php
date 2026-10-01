<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $id ? db_row($conn, 'SELECT i.*, s.quantity, s.reorder_threshold FROM item i JOIN stock s ON s.item_id = i.item_id WHERE i.item_id = ?', [$id]) : null;
if ($id && !$item) {
    flash('danger', 'Item not found.');
    redirect('admin/items.php');
}

$form = $item ?? ['description' => '', 'category_id' => '', 'brand_id' => '', 'supplier_id' => '', 'cost_price' => '', 'sell_price' => '', 'quantity' => 0, 'reorder_threshold' => 10, 'is_active' => 1, 'img_path' => null];

if (is_post()) {
    csrf_check();
    $newImage = null;
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

        $form = array_merge($form, ['description' => $desc, 'category_id' => $catId, 'brand_id' => $brandId, 'supplier_id' => $supId, 'cost_price' => $cost, 'sell_price' => $sell, 'quantity' => $qty, 'reorder_threshold' => $threshold, 'is_active' => $active]);

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
include __DIR__ . '/../includes/admin_header.php';

$select = function (string $name, array $rows, string $key, $current, string $none) {
    $out = '<select class="form-select" id="' . $name . '" name="' . $name . '"><option value="0">' . e($none) . '</option>';
    foreach ($rows as $r) {
        $out .= '<option value="' . (int) $r[$key] . '"' . ((int) $current === (int) $r[$key] ? ' selected' : '') . '>' . e($r['name']) . '</option>';
    }
    return $out . '</select>';
};
?>
<div class="admin-bar">
  <h1 class="page-title"><?= $item ? 'Edit item' : 'Add item' ?></h1>
  <a class="btn btn-outline-primary" href="<?= e(url('admin/items.php')) ?>">Back to items</a>
</div>

<form method="post" enctype="multipart/form-data" class="row g-4">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="col-lg-8">
    <div class="panel panel-body">
      <div class="row g-3">
        <div class="col-12"><label class="form-label" for="description">Item name</label><input class="form-control" id="description" name="description" value="<?= e($form['description']) ?>" maxlength="100" required></div>
        <div class="col-md-4"><label class="form-label" for="category_id">Category</label><?= $select('category_id', $categories, 'category_id', $form['category_id'], 'No category') ?></div>
        <div class="col-md-4"><label class="form-label" for="brand_id">Brand</label><?= $select('brand_id', $brands, 'brand_id', $form['brand_id'], 'No brand') ?></div>
        <div class="col-md-4"><label class="form-label" for="supplier_id">Supplier</label><?= $select('supplier_id', $suppliers, 'supplier_id', $form['supplier_id'], 'No supplier') ?></div>
        <div class="col-md-6"><label class="form-label" for="cost_price">Cost price (what you pay)</label><input class="form-control" id="cost_price" name="cost_price" inputmode="decimal" value="<?= e($form['cost_price']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="sell_price">Selling price</label><input class="form-control" id="sell_price" name="sell_price" inputmode="decimal" value="<?= e($form['sell_price']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="quantity"><?= $item ? 'Stock on hand' : 'Starting stock' ?></label><input class="form-control" type="number" min="0" id="quantity" name="quantity" value="<?= (int) $form['quantity'] ?>" required>
          <?php if ($item): ?><div class="form-text">To add new deliveries, use <a href="<?= e(url('admin/restock.php?item=' . $id)) ?>">Restock</a> so the history is clear.</div><?php endif; ?></div>
        <div class="col-md-6"><label class="form-label" for="reorder_threshold">Reorder level</label><input class="form-control" type="number" min="0" id="reorder_threshold" name="reorder_threshold" value="<?= (int) $form['reorder_threshold'] ?>" required><div class="form-text">The item shows as low when stock is at or below this number.</div></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="panel panel-body mb-3">
      <label class="form-label" for="image">Photo</label>
      <div class="thumb mb-3" style="width:120px;height:120px"><?= item_visual($form + ['category' => '']) ?></div>
      <input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
      <div class="form-text">JPG, PNG, or WEBP, up to 3 MB. Without a photo the shop shows a color swatch.</div>
      <?php if ($item && $item['img_path']): ?>
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_image" name="remove_image"><label class="form-check-label" for="remove_image">Remove current photo</label></div>
      <?php endif; ?>
    </div>
    <div class="panel panel-body mb-3">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-bold" for="is_active">Show in the shop</label>
      </div>
      <div class="form-text">Turn off to hide the item without deleting it.</div>
    </div>
    <button class="btn btn-primary w-100" type="submit"><?= $item ? 'Save changes' : 'Add item' ?></button>
  </div>
</form>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
