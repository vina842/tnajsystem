<?php
require_once __DIR__ . '/includes/config.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
if (is_admin()) {
    redirect('admin/order.php?id=' . $id);
}

$customer = current_customer($conn);
$order = $customer ? db_row($conn, 'SELECT * FROM orderinfo WHERE orderinfo_id = ? AND customer_id = ?', [$id, (int) $customer['customer_id']]) : null;
if (!$order) {
    http_response_code(404);
    flash('danger', 'We could not find that order.');
    redirect('orders.php');
}
$lines = db_rows(
    $conn,
    'SELECT ol.quantity, ol.unit_price, i.description, i.img_path, c.name AS category
     FROM orderline ol JOIN item i ON i.item_id = ol.item_id
     LEFT JOIN category c ON c.category_id = i.category_id
     WHERE ol.orderinfo_id = ? ORDER BY i.description',
    [$id]
);

$pageTitle = 'Order ' . order_label($id);
$active = 'orders';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h1 class="page-title">Order <?= order_label($id) ?></h1>
    <div class="muted">Placed <?= e(format_dt($order['date_placed'])) ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap"><?= status_badge($order['status']) ?> <?= payment_badge($order['payment_method'], $order['payment_status']) ?></div>
</div>

<?php if (in_array($order['status'], ['Pending', 'Processing'], true)): ?>
  <div class="alert alert-info">Show order number <strong><?= order_label($id) ?></strong> at the counter to pick up your items<?= $order['payment_method'] === 'cash' ? ' and pay' : '' ?>.</div>
<?php endif; ?>

<div class="panel">
  <div class="table-wrap">
    <table class="table mb-0">
      <thead><tr><th>Item</th><th class="text-end">Price</th><th class="text-end">Quantity</th><th class="text-end">Subtotal</th></tr></thead>
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
</div>
<a class="btn btn-outline-primary mt-3" href="<?= e(url('orders.php')) ?>">Back to my orders</a>
<?php include __DIR__ . '/includes/footer.php'; ?>
