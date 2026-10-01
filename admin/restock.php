<?php
require_once __DIR__ . '/../includes/config.php';
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
        log_activity($conn, 'restock', "$name +$qty");
        flash('success', "Added $qty to $name.");
        redirect('admin/restock.php');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$selected = (int) ($_GET['item'] ?? 0);
$items = db_rows($conn, 'SELECT i.item_id, i.description, s.quantity, s.reorder_threshold FROM item i JOIN stock s ON s.item_id = i.item_id ORDER BY i.description');
$moves = db_rows(
    $conn,
    'SELECT m.*, i.description FROM stock_movement m JOIN item i ON i.item_id = m.item_id ORDER BY m.movement_id DESC LIMIT 30'
);

$pageTitle = 'Restock';
$adminActive = 'restock';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar"><h1 class="page-title">Restock</h1></div>

<div class="row g-4">
  <div class="col-lg-4">
    <form method="post" class="panel panel-body">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="item_id">Item</label>
        <select class="form-select" id="item_id" name="item_id" required>
          <option value="">Choose an item</option>
          <?php foreach ($items as $i): ?>
            <option value="<?= (int) $i['item_id'] ?>" <?= $selected === (int) $i['item_id'] ? 'selected' : '' ?>><?= e($i['description']) ?> (<?= (int) $i['quantity'] ?> in stock)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="qty">Pieces received</label>
        <input class="form-control" type="number" id="qty" name="qty" min="1" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Add to stock</button>
    </form>
  </div>
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-head"><h2>Recent stock changes</h2></div>
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>When</th><th>Item</th><th>Reason</th><th class="text-end">Change</th><th class="text-end">Stock after</th></tr></thead>
          <tbody>
          <?php foreach ($moves as $m): ?>
            <tr>
              <td class="text-nowrap"><?= e(format_dt($m['created_at'], 'M j, g:i A')) ?></td>
              <td><?= e($m['description']) ?></td>
              <td><?= e(ucfirst($m['reason'])) ?><?= $m['ref_id'] ? ' ' . order_label((int) $m['ref_id']) : '' ?></td>
              <td class="text-end num fw-bold <?= $m['qty_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['qty_change'] > 0 ? '+' : '' ?><?= (int) $m['qty_change'] ?></td>
              <td class="text-end num"><?= (int) $m['qty_after'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$moves): ?><tr><td colspan="5" class="text-center muted py-4">No stock changes yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
