<?php
require_once __DIR__ . '/includes/config.php';
require_login();
if (is_admin()) {
    redirect('admin/orders.php');
}

$customer = current_customer($conn);
$orders = $customer ? db_rows(
    $conn,
    'SELECT o.*, (SELECT SUM(quantity) FROM orderline WHERE orderinfo_id = o.orderinfo_id) AS items
     FROM orderinfo o WHERE o.customer_id = ? ORDER BY o.date_placed DESC, o.orderinfo_id DESC',
    [(int) $customer['customer_id']]
) : [];

$pageTitle = 'My orders';
$active = 'orders';
include __DIR__ . '/includes/header.php';
?>
<h1 class="page-title mb-3">My orders</h1>
<?php if (!$orders): ?>
  <div class="panel panel-body text-center py-5">
    <h2 class="h5">No orders yet</h2>
    <p class="muted mb-3">When you place an order, it will show up here.</p>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Start shopping</a>
  </div>
<?php else: ?>
  <div class="panel table-wrap">
    <table class="table mb-0">
      <thead><tr><th>Order</th><th>Date</th><th class="text-end">Items</th><th class="text-end">Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="fw-bold"><?= order_label((int) $o['orderinfo_id']) ?></td>
          <td><?= e(format_dt($o['date_placed'])) ?></td>
          <td class="text-end num"><?= (int) $o['items'] ?></td>
          <td class="text-end num fw-bold"><?= money($o['total_amount']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td><?= payment_badge($o['payment_method'], $o['payment_status']) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('order.php?id=' . $o['orderinfo_id'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
