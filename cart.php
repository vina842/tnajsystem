<?php
include("includes/config.php");

if (is_post()) {
    csrf_check();
    $back = safe_next($_POST['back'] ?? '') ?: url('cart.php');
    $action = $_POST['action'] ?? '';

    if (is_admin()) {
        flash('info', 'Admins record sales on the Walk-in sale page.');
        redirect(url('admin/pos.php'));
    }

    if ($action === 'add') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));

        $it = db_row($conn, 'SELECT i.description, s.quantity FROM item i JOIN stock s ON s.item_id = i.item_id WHERE i.item_id = ? AND i.is_active = 1', [$itemId]);

        if (!$it || (int) $it['quantity'] <= 0) {
            flash('danger', 'That item is out of stock.');
        } else {
            $stock = (int) $it['quantity'];
            $have = cart_get()[$itemId] ?? 0;
            cart_set($itemId, min($have + $qty, $stock));

            if ($have + $qty > $stock) {
                flash('warning', 'Only ' . $stock . ' of ' . $it['description'] . ' in stock. Your cart has the most we can give.');
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

    if ($action === 'clear') {
        cart_clear();
        redirect('cart.php');
    }

    if ($action === 'update') {
        foreach ((array) ($_POST['qty'] ?? []) as $id => $qty) {
            $stock = (int) db_val($conn, 'SELECT quantity FROM stock WHERE item_id = ?', [(int) $id]);
            cart_set((int) $id, min(max(0, (int) $qty), $stock));
        }
        flash('success', 'Cart updated.');
        redirect('cart.php');
    }

    redirect('cart.php');
}

$cart = cart_get();
$lines = [];
$total = 0.0;

if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_rows($conn, "SELECT i.item_id, i.description, i.sell_price, s.quantity
                            FROM item i JOIN stock s ON s.item_id = i.item_id
                            WHERE i.is_active = 1 AND i.item_id IN ($marks)", $ids);

    foreach ($rows as $r) {
        $id = (int) $r['item_id'];
        $qty = min($cart[$id], max(0, (int) $r['quantity']));
        if ($qty <= 0) {
            continue;
        }
        $r['qty'] = $qty;
        $r['subtotal'] = $qty * (float) $r['sell_price'];
        $total += $r['subtotal'];
        $lines[$id] = $r;
    }

    // Drop anything that is no longer available
    foreach ($ids as $id) {
        if (!isset($lines[$id])) {
            cart_set($id, 0);
        }
    }
}

$customer = null;
$lista = null;
$available = 0.0;

if (is_logged_in() && !is_admin()) {
    $customer = current_customer($conn);
}
if ($customer) {
    $lista = db_row($conn, 'SELECT * FROM lista_account WHERE customer_id = ?', [(int) $customer['customer_id']]);
}
if ($lista && $lista['status'] === 'active') {
    $available = (float) $lista['credit_limit'] - (float) $lista['balance'];
}
$canLista = $lista && $lista['status'] === 'active' && $available + 0.001 >= $total;

$pageTitle = 'Your cart';
$active = 'cart';
include("includes/header.php");
?>
<h1>Your cart</h1>

<?php if (!$lines) { ?>
    <p>Your cart is empty.</p>
    <p><a href="<?= e(url('index.php')) ?>">Browse products</a></p>
<?php } else { ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <table border="1">
            <tr>
                <th>Item</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
                <th>Remove</th>
            </tr>
            <?php foreach ($lines as $l) { ?>
                <tr>
                    <td><?= e($l['description']) ?></td>
                    <td><?= money($l['sell_price']) ?></td>
                    <td><input type="number" name="qty[<?= (int) $l['item_id'] ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= (int) $l['quantity'] ?>"></td>
                    <td><?= money($l['subtotal']) ?></td>
                    <td><button type="submit" name="remove" value="<?= (int) $l['item_id'] ?>">Remove</button></td>
                </tr>
            <?php } ?>
        </table>
        <p>
            <a href="<?= e(url('index.php')) ?>">Keep shopping</a>
            <button type="submit">Update cart</button>
        </p>
    </form>

    <h2>Order summary</h2>
    <p>Total: <?= money($total) ?></p>

    <?php if (is_admin()) { ?>
        <p>You are logged in as admin. Record sales on the <a href="<?= e(url('admin/pos.php')) ?>">Walk-in sale</a> page.</p>

    <?php } elseif (!is_logged_in()) { ?>
        <p>Log in to place your order. Your cart will be waiting.</p>
        <p>
            <a href="<?= e(url('login.php?next=' . urlencode(url('cart.php')))) ?>">Log in to check out</a> |
            <a href="<?= e(url('register.php')) ?>">Create an account</a>
        </p>

    <?php } elseif (!$customer) { ?>
        <p>This account has no customer profile yet. Ask the shop to set one up.</p>

    <?php } else { ?>
        <form action="<?= e(url('checkout.php')) ?>" method="post">
            <?= csrf_field() ?>

            <p>
                <label>
                    <input type="radio" name="payment_method" value="cash" checked>
                    Cash at the counter
                </label><br>
                Pay when you pick up your order.
            </p>

            <p>
                <label>
                    <input type="radio" name="payment_method" value="lista" <?= $canLista ? '' : 'disabled' ?>>
                    Put it sa lista
                </label><br>
                <?php if (!$lista) { ?>
                    You do not have a suki list yet. Ask the shop to open one.
                <?php } elseif ($lista['status'] !== 'active') { ?>
                    Your suki list is suspended right now.
                <?php } elseif (!$canLista) { ?>
                    You have <?= money(max(0, $available)) ?> left, which is not enough for this order.
                <?php } else { ?>
                    <?= money($available) ?> available. Balance after this order: <?= money((float) $lista['balance'] + $total) ?>.
                <?php } ?>
            </p>

            <button type="submit">Place order</button>
            <p>Nothing is charged online. You settle payment at the counter.</p>
        </form>
    <?php } ?>

<?php } ?>

<?php
include("includes/footer.php"); ?>