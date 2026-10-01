<?php
require_once __DIR__ . '/includes/config.php';

if (is_post()) {
    csrf_check();
    $back = safe_next($_POST['back'] ?? '') ?: url('cart.php');

    if (is_admin()) {
        flash('info', 'Admins record sales on the Walk-in sale page.');
        redirect(url('admin/pos.php'));
    }

    if (($_POST['action'] ?? '') === 'add') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $it = db_row($conn, 'SELECT i.description, s.quantity FROM item i JOIN stock s ON s.item_id = i.item_id WHERE i.item_id = ? AND i.is_active = 1', [$itemId]);
        if (!$it || (int) $it['quantity'] <= 0) {
            flash('danger', 'That item is out of stock.');
        } else {
            $have = cart_get()[$itemId] ?? 0;
            $new = min($have + $qty, (int) $it['quantity']);
            cart_set($itemId, $new);
            if ($have + $qty > (int) $it['quantity']) {
                flash('warning', 'Only ' . (int) $it['quantity'] . ' of ' . $it['description'] . ' in stock. Your cart has the most we can give.');
            } else {
                flash('success', $it['description'] . ' added to your cart.');
            }
        }
        redirect($back);
    }

    if (isset($_POST['remove'])) {
        cart_set((int) $_POST['remove'], 0);
        flash('success', 'Item removed.');
        redirect('cart.php');
    }

    if (($_POST['action'] ?? '') === 'clear') {
        cart_clear();
        redirect('cart.php');
    }

    if (($_POST['action'] ?? '') === 'update') {
        foreach ((array) ($_POST['qty'] ?? []) as $id => $qty) {
            $stock = (int) db_val($conn, 'SELECT quantity FROM stock WHERE item_id = ?', [(int) $id]);
            cart_set((int) $id, min(max(0, (int) $qty), $stock));
        }
        flash('success', 'Cart updated.');
        redirect('cart.php');
    }
    redirect('cart.php');
}

$lines = [];
$total = 0.0;
if (cart_get()) {
    $ids = array_keys(cart_get());
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_rows(
        $conn,
        "SELECT i.item_id, i.description, i.sell_price, i.img_path, c.name AS category, s.quantity, s.reorder_threshold
         FROM item i JOIN stock s ON s.item_id = i.item_id
         LEFT JOIN category c ON c.category_id = i.category_id
         WHERE i.is_active = 1 AND i.item_id IN ($marks)",
        array_map('intval', $ids)
    );
    foreach ($rows as $r) {
        $qty = min(cart_get()[(int) $r['item_id']], max(0, (int) $r['quantity']));
        if ($qty <= 0) {
            cart_set((int) $r['item_id'], 0);
            continue;
        }
        $r['qty'] = $qty;
        $r['subtotal'] = $qty * (float) $r['sell_price'];
        $total += $r['subtotal'];
        $lines[] = $r;
    }
    $present = array_map(fn($r) => (int) $r['item_id'], $lines);
    foreach ($ids as $id) {
        if (!in_array((int) $id, $present, true)) {
            cart_set((int) $id, 0);
        }
    }
}

$customer = is_logged_in() && !is_admin() ? current_customer($conn) : null;
$lista = $customer ? db_row($conn, 'SELECT * FROM lista_account WHERE customer_id = ?', [(int) $customer['customer_id']]) : null;
$available = $lista && $lista['status'] === 'active' ? (float) $lista['credit_limit'] - (float) $lista['balance'] : 0.0;

$pageTitle = 'Your cart';
$active = 'cart';
include __DIR__ . '/includes/header.php';
?>
<h1 class="page-title mb-3">Your cart</h1>

<?php if (!$lines): ?>
  <div class="panel panel-body text-center py-5">
    <h2 class="h5">Your cart is empty</h2>
    <p class="muted mb-3">Add a few things from the shop and they will show up here.</p>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Browse products</a>
  </div>
<?php else: ?>
<div class="row g-4">
  <div class="col-lg-8">
    <form method="post" class="panel">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Item</th><th class="text-end">Price</th><th>Quantity</th><th class="text-end">Subtotal</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lines as $l): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <div class="thumb"><?= item_visual($l) ?></div>
                  <div>
                    <div class="fw-bold"><?= e($l['description']) ?></div>
                    <?= stock_note((int) $l['quantity'], (int) $l['reorder_threshold']) ?>
                  </div>
                </div>
              </td>
              <td class="text-end num"><?= money($l['sell_price']) ?></td>
              <td>
                <div class="qty">
                  <button type="button" data-step="-1" aria-label="Less">-</button>
                  <input type="number" name="qty[<?= (int) $l['item_id'] ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= (int) $l['quantity'] ?>" aria-label="Quantity for <?= e($l['description']) ?>">
                  <button type="button" data-step="1" aria-label="More">+</button>
                </div>
              </td>
              <td class="text-end num fw-bold"><?= money($l['subtotal']) ?></td>
              <td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="submit" name="remove" value="<?= (int) $l['item_id'] ?>" aria-label="Remove <?= e($l['description']) ?>"><i class="bi bi-x-lg"></i></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="panel-body d-flex justify-content-between flex-wrap gap-2">
        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-primary">Keep shopping</a>
        <button class="btn btn-primary" type="submit">Update cart</button>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="panel summary-sticky">
      <div class="panel-head"><h2>Order summary</h2></div>
      <div class="panel-body">
        <div class="total-row mb-3"><span>Total</span><span class="num"><?= money($total) ?></span></div>

        <?php if (is_admin()): ?>
          <p class="muted mb-0">You are logged in as admin. Record sales on the <a href="<?= e(url('admin/pos.php')) ?>">Walk-in sale</a> page.</p>
        <?php elseif (!is_logged_in()): ?>
          <p class="muted">Log in to place your order. Your cart will be waiting.</p>
          <a class="btn btn-primary w-100 mb-2" href="<?= e(url('login.php?next=' . urlencode(url('cart.php')))) ?>">Log in to check out</a>
          <a class="btn btn-outline-primary w-100" href="<?= e(url('register.php')) ?>">Create an account</a>
        <?php elseif (!$customer): ?>
          <p class="muted mb-0">This account has no customer profile yet. Ask the shop to set one up.</p>
        <?php else: ?>
          <form action="<?= e(url('checkout.php')) ?>" method="post">
            <?= csrf_field() ?>
            <label class="pay-option" id="pay-cash">
              <input type="radio" name="payment_method" value="cash" checked>
              <strong>Cash at the counter</strong>
              <div class="muted small">Pay when you pick up your order.</div>
            </label>
            <?php $canLista = $lista && $lista['status'] === 'active' && $available + 0.001 >= $total; ?>
            <label class="pay-option <?= $canLista ? '' : 'is-disabled' ?>">
              <input type="radio" name="payment_method" value="lista" <?= $canLista ? '' : 'disabled' ?>>
              <strong>Put it sa lista</strong>
              <div class="muted small">
                <?php if (!$lista): ?>
                  You do not have a suki list yet. Ask the shop to open one.
                <?php elseif ($lista['status'] !== 'active'): ?>
                  Your suki list is suspended right now.
                <?php elseif (!$canLista): ?>
                  You have <?= money(max(0, $available)) ?> left, which is not enough for this order.
                <?php else: ?>
                  <?= money($available) ?> available. Balance after this order: <?= money((float) $lista['balance'] + $total) ?>.
                <?php endif; ?>
              </div>
            </label>
            <button class="btn btn-yellow w-100 mt-2" type="submit">Place order</button>
            <p class="muted small mt-2 mb-0">Nothing is charged online. You settle payment at the counter.</p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
