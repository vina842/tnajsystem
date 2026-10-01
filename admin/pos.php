<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

if (is_post()) {
    csrf_check();
    $qty = array_map('intval', (array) ($_POST['qty'] ?? []));
    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
    $method = ($_POST['payment_method'] ?? 'cash') === 'lista' ? 'lista' : 'cash';
    $walkin = $customerId ? null : (post_str('walkin_name', 100) ?: null);
    try {
        $orderId = place_order($conn, $qty, [
            'customer_id' => $customerId,
            'walkin_name' => $walkin,
            'order_type' => 'walk_in',
            'payment_method' => $method,
            'created_by' => (int) $_SESSION['user_id'],
        ]);
        $total = db_val($conn, 'SELECT total_amount FROM orderinfo WHERE orderinfo_id = ?', [$orderId]);
        log_activity($conn, 'walk-in sale', order_label($orderId) . ' ' . money($total) . ' ' . $method);
        flash('success', 'Sale ' . order_label($orderId) . ' recorded for ' . money($total) . '.');
        redirect('admin/order.php?id=' . $orderId);
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (mysqli_sql_exception $e) {
        flash('danger', 'The sale could not be saved, and nothing was changed. Please try again.');
    }
}

$items = db_rows(
    $conn,
    'SELECT i.item_id, i.description, i.sell_price, i.img_path, c.name AS category, b.name AS brand, s.quantity
     FROM item i JOIN stock s ON s.item_id = i.item_id
     LEFT JOIN category c ON c.category_id = i.category_id
     LEFT JOIN brand b ON b.brand_id = i.brand_id
     WHERE i.is_active = 1 ORDER BY c.name, i.description'
);
$customers = db_rows(
    $conn,
    "SELECT c.customer_id, c.fname, c.lname, c.phone, la.credit_limit - la.balance AS available, la.status
     FROM customer c LEFT JOIN lista_account la ON la.customer_id = c.customer_id ORDER BY c.lname, c.fname"
);

$pageTitle = 'Walk-in sale';
$adminActive = 'pos';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div><h1 class="page-title">Walk-in sale</h1><div class="muted">Record what the customer takes at the counter. Payment is collected in person.</div></div>
</div>

<form method="post" id="pos" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-head">
        <h2>Items</h2>
        <input class="form-control form-control-sm" style="max-width:260px" type="search" data-filter=".pos-row" placeholder="Filter items" aria-label="Filter items">
      </div>
      <div class="pos-items table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Item</th><th class="text-end">Price</th><th class="text-end">Stock</th><th>Quantity</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): $out = (int) $it['quantity'] <= 0; ?>
            <tr class="pos-row <?= $out ? 'is-out' : '' ?>" data-price="<?= e($it['sell_price']) ?>" data-name="<?= e($it['description'] . ' ' . $it['brand'] . ' ' . $it['category']) ?>">
              <td><div class="d-flex align-items-center gap-3"><div class="thumb"><?= item_visual($it) ?></div><div><div class="fw-bold"><?= e($it['description']) ?></div><div class="muted small"><?= e($it['category'] ?? '') ?></div></div></div></td>
              <td class="text-end num"><?= money($it['sell_price']) ?></td>
              <td class="text-end num"><?= (int) $it['quantity'] ?></td>
              <td>
                <div class="qty">
                  <button type="button" data-step="-1" aria-label="Less" <?= $out ? 'disabled' : '' ?>>-</button>
                  <input type="number" name="qty[<?= (int) $it['item_id'] ?>]" value="0" min="0" max="<?= max(0, (int) $it['quantity']) ?>" <?= $out ? 'disabled' : '' ?> aria-label="Quantity for <?= e($it['description']) ?>">
                  <button type="button" data-step="1" aria-label="More" <?= $out ? 'disabled' : '' ?>>+</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="col-lg-4">
    <section class="panel summary-sticky">
      <div class="panel-head"><h2>Sale</h2><span class="muted" id="pos-count">0 items</span></div>
      <div class="panel-body">
        <div class="mb-3">
          <label class="form-label" for="pos-customer">Customer</label>
          <select class="form-select" id="pos-customer" name="customer_id">
            <option value="">Walk-in (no account)</option>
            <?php foreach ($customers as $c): $hasList = $c['available'] !== null && $c['status'] === 'active'; ?>
              <option value="<?= (int) $c['customer_id'] ?>" <?= $hasList ? 'data-credit="' . e(max(0, (float) $c['available'])) . '"' : '' ?>><?= e($c['lname'] . ', ' . $c['fname']) ?><?= $hasList ? ' (suki)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="pos-walkin">Name for the receipt (optional)</label>
          <input class="form-control" id="pos-walkin" name="walkin_name" maxlength="100">
        </div>

        <div class="form-label">Payment</div>
        <label class="pay-option" id="pay-cash">
          <input type="radio" name="payment_method" value="cash" checked>
          <strong>Cash</strong>
          <div class="mt-2">
            <input class="form-control form-control-sm" type="number" step="0.01" min="0" id="pos-cash" placeholder="Cash received (optional)" aria-label="Cash received">
            <div class="small fw-bold mt-1" id="pos-change"></div>
          </div>
        </label>
        <label class="pay-option is-disabled" id="pay-lista">
          <input type="radio" name="payment_method" value="lista" disabled>
          <strong>Suki list (utang)</strong>
          <div class="muted small" id="pay-lista-note">Pick a registered customer to use the suki list.</div>
        </label>

        <div class="total-row my-3"><span>Total</span><span class="num" id="pos-total">₱0.00</span></div>
        <button class="btn btn-yellow w-100" type="submit" id="pos-submit" disabled>Record sale</button>
      </div>
    </section>
  </div>
</form>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
