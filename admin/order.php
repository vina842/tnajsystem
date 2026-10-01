<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (is_post()) {
    csrf_check();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'status') {
            $new = (string) ($_POST['status'] ?? '');
            set_order_status($conn, $id, $new);
            log_activity($conn, 'order ' . strtolower($new), order_label($id));
            flash('success', 'Order ' . order_label($id) . ' is now ' . strtolower($new) . '.' . ($new === 'Canceled' ? ' Stock was returned' . ' and any suki charge was reversed.' : ''));
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

$order = db_row(
    $conn,
    'SELECT o.*, c.fname, c.lname, c.phone, c.addressline, c.town, c.customer_id AS cid, u.email AS staff
     FROM orderinfo o
     LEFT JOIN customer c ON c.customer_id = o.customer_id
     LEFT JOIN users u ON u.user_id = o.created_by
     WHERE o.orderinfo_id = ?',
    [$id]
);
if (!$order) {
    flash('danger', 'Order not found.');
    redirect('admin/orders.php');
}
$lines = db_rows(
    $conn,
    'SELECT ol.quantity, ol.unit_price, i.description, i.img_path, c.name AS category
     FROM orderline ol JOIN item i ON i.item_id = ol.item_id
     LEFT JOIN category c ON c.category_id = i.category_id
     WHERE ol.orderinfo_id = ? ORDER BY i.description',
    [$id]
);
$canceled = $order['status'] === 'Canceled';

$pageTitle = 'Order ' . order_label($id);
$adminActive = 'orders';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div>
    <h1 class="page-title">Order <?= order_label($id) ?></h1>
    <div class="muted"><?= $order['order_type'] === 'walk_in' ? 'Walk-in sale' : 'Online order' ?>, <?= e(format_dt($order['date_placed'])) ?></div>
  </div>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-primary" type="button" data-print><i class="bi bi-printer"></i> Print receipt</button>
    <a class="btn btn-outline-primary" href="<?= e(url('admin/orders.php')) ?>">All orders</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <section class="panel">
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Item</th><th class="text-end">Price</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($lines as $l): ?>
            <tr>
              <td><div class="d-flex align-items-center gap-3"><div class="thumb"><?= item_visual($l) ?></div><span class="fw-bold"><?= e($l['description']) ?></span></div></td>
              <td class="text-end num"><?= money($l['unit_price']) ?></td>
              <td class="text-end num"><?= (int) $l['quantity'] ?></td>
              <td class="text-end num"><?= money($l['unit_price'] * $l['quantity']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><td colspan="3" class="text-end fw-bold">Total</td><td class="text-end num fw-bold"><?= money($order['total_amount']) ?></td></tr></tfoot>
        </table>
      </div>
    </section>
  </div>

  <div class="col-lg-4">
    <section class="panel mb-3">
      <div class="panel-body">
        <div class="d-flex flex-wrap gap-2 mb-3"><?= status_badge($order['status']) ?> <?= payment_badge($order['payment_method'], $order['payment_status']) ?></div>
        <div class="fw-bold"><?= e(customer_name($order)) ?></div>
        <?php if ($order['cid']): ?>
          <div class="muted small"><?= e(trim($order['phone'] . ' ' . $order['addressline'] . ' ' . $order['town'])) ?></div>
          <a class="small no-print" href="<?= e(url('admin/customer.php?id=' . $order['cid'])) ?>">Open customer record</a>
        <?php else: ?>
          <div class="muted small">No account</div>
        <?php endif; ?>
        <?php if ($order['staff']): ?><div class="muted small mt-2">Recorded by <?= e($order['staff']) ?></div><?php endif; ?>
        <?php if ($order['date_completed']): ?><div class="muted small">Completed <?= e(format_dt($order['date_completed'])) ?></div><?php endif; ?>
      </div>
    </section>

    <?php if (!$canceled): ?>
    <section class="panel no-print">
      <div class="panel-head"><h2>Update order</h2></div>
      <div class="panel-body d-grid gap-2">
        <?php foreach (['Processing' => 'Mark as processing', 'Completed' => 'Mark as completed'] as $s => $label): ?>
          <?php if ($order['status'] !== $s): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $s ?>"><button class="btn btn-primary w-100" type="submit"><?= $label ?></button></form>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($order['payment_method'] === 'cash' && $order['payment_status'] === 'unpaid'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="paid"><button class="btn btn-yellow w-100" type="submit">Mark as paid (cash received)</button></form>
        <?php endif; ?>
        <form method="post" data-confirm="Cancel order <?= order_label($id) ?>? Stock goes back on the shelf<?= $order['payment_method'] === 'lista' ? ', and the amount comes off the suki list' : '' ?>.">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="Canceled">
          <button class="btn btn-outline-danger w-100" type="submit">Cancel this order</button>
        </form>
      </div>
    </section>
    <?php else: ?>
      <div class="alert alert-secondary no-print">This order was canceled. Stock and any suki charge were returned.</div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
