<?php
require_once __DIR__ . '/../includes/config.php';
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
            flash('success', $item['description'] . ($item['is_active'] ? ' is now hidden from the shop.' : ' is now visible in the shop.'));
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
    array_push($params, "%$q%", "%$q%");
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

$items = db_rows(
    $conn,
    'SELECT i.*, c.name AS category, b.name AS brand, s.quantity, s.reorder_threshold
     FROM item i JOIN stock s ON s.item_id = i.item_id
     LEFT JOIN category c ON c.category_id = i.category_id
     LEFT JOIN brand b ON b.brand_id = i.brand_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY i.description',
    $params
);
$categories = db_rows($conn, 'SELECT * FROM category ORDER BY name');

$pageTitle = 'Items';
$adminActive = 'items';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <h1 class="page-title">Items</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/item_form.php')) ?>"><i class="bi bi-plus-lg"></i> Add item</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or brand" aria-label="Search items"></div>
  <div class="col-md-3">
    <select class="form-select" name="category" aria-label="Category">
      <option value="0">All categories</option>
      <?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>" <?= $cat === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select class="form-select" name="view" aria-label="Show">
      <option value="">All items</option>
      <option value="low" <?= $view === 'low' ? 'selected' : '' ?>>Low or out of stock</option>
      <option value="hidden" <?= $view === 'hidden' ? 'selected' : '' ?>>Hidden from the shop</option>
    </select>
  </div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Filter</button></div>
</form>

<div class="panel table-wrap">
  <table class="table mb-0">
    <thead><tr><th>Item</th><th>Category</th><th class="text-end">Cost</th><th class="text-end">Price</th><th class="text-end">Stock</th><th>Shop</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-3">
            <div class="thumb"><?= item_visual($it) ?></div>
            <div><div class="fw-bold"><?= e($it['description']) ?></div><div class="muted small"><?= e($it['brand'] ?? 'No brand') ?></div></div>
          </div>
        </td>
        <td><?= e($it['category'] ?? 'None') ?></td>
        <td class="text-end num"><?= money($it['cost_price']) ?></td>
        <td class="text-end num fw-bold"><?= money($it['sell_price']) ?></td>
        <td class="text-end"><?= stock_note((int) $it['quantity'], (int) $it['reorder_threshold']) ?></td>
        <td><?= $it['is_active'] ? '<span class="pill b-done">Visible</span>' : '<span class="pill b-void">Hidden</span>' ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/restock.php?item=' . $it['item_id'])) ?>">Restock</a>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/item_form.php?id=' . $it['item_id'])) ?>">Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete <?= e($it['description']) ?>? Items with sales history are hidden instead.">
            <?= csrf_field() ?>
            <input type="hidden" name="item_id" value="<?= (int) $it['item_id'] ?>">
            <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit" aria-label="Delete <?= e($it['description']) ?>"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="7" class="text-center muted py-5">No items match. <a href="<?= e(url('admin/item_form.php')) ?>">Add an item</a> or clear the filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
