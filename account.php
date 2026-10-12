<?php
include("includes/config.php");
require_login();

if (is_admin()) {
    redirect('admin/index.php');
}

$customer = current_customer($conn);
if (!$customer) {
    flash('danger', 'This account has no customer profile yet.');
    redirect('index.php');
}
$cid = (int) $customer['customer_id'];

if (is_post()) {
    csrf_check();

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $fname = post_str('fname', 50);
            $lname = post_str('lname', 50);
            if ($fname === '' || $lname === '') {
                throw new RuntimeException('Enter your first and last name.');
            }
            db_run($conn, 'UPDATE customer SET fname = ?, lname = ?, phone = ?, addressline = ?, town = ?, zipcode = ? WHERE customer_id = ?',
                [$fname, $lname, post_str('phone', 20), post_str('addressline', 100), post_str('town', 50), post_str('zipcode', 10), $cid]);
            flash('success', 'Profile saved.');
        }

        if ($action === 'password') {
            $userId = (int) $_SESSION['user_id'];
            $hash = db_val($conn, 'SELECT password FROM users WHERE user_id = ?', [$userId]);
            $new = (string) ($_POST['new'] ?? '');

            if (!password_verify((string) ($_POST['current'] ?? ''), (string) $hash)) {
                throw new RuntimeException('Your current password is not correct.');
            }
            if (strlen($new) < 6) {
                throw new RuntimeException('The new password needs at least 6 characters.');
            }
            if ($new !== ($_POST['confirm'] ?? '')) {
                throw new RuntimeException('The two new passwords do not match.');
            }
            db_run($conn, 'UPDATE users SET password = ? WHERE user_id = ?', [password_hash($new, PASSWORD_BCRYPT), $userId]);
            flash('success', 'Password changed.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('account.php');
}

$customer = db_row($conn, 'SELECT * FROM customer WHERE customer_id = ?', [$cid]);
$lista = db_row($conn, 'SELECT * FROM lista_account WHERE customer_id = ?', [$cid]);
$ledger = [];
if ($lista) {
    $ledger = db_rows($conn, 'SELECT * FROM lista_transaction WHERE lista_id = ? ORDER BY lista_trans_id', [(int) $lista['lista_id']]);
}
$points = (int) db_val($conn, 'SELECT loyalty_points FROM customer WHERE customer_id = ?', [$cid]);
$loyalty = db_rows($conn, 'SELECT * FROM loyalty_transaction WHERE customer_id = ? ORDER BY loyalty_trans_id DESC LIMIT 10', [$cid]);

$pageTitle = 'My account';
$active = 'account';
include("includes/header.php");
?>
<h1>My Account</h1>

<h2>My Suki List</h2>
<?php if ($lista) { ?>
    <?php if ($lista['status'] !== 'active') { ?>
        <p><strong>Your suki list is suspended.</strong></p>
    <?php } ?>
    <p>Credit limit: <?= money($lista['credit_limit']) ?></p>
    <p>I owe: <?= money($lista['balance']) ?></p>
    <p>Still available: <?= money(max(0, (float) $lista['credit_limit'] - (float) $lista['balance'])) ?></p>

    <table border="1" cellpadding="6">
        <tr>
            <th>Date</th>
            <th>Details</th>
            <th>Utang</th>
            <th>Paid</th>
            <th>Balance</th>
        </tr>
        <?php foreach ($ledger as $t) { ?>
            <tr>
                <td><?= e(format_dt($t['created_at'], 'M j, Y')) ?></td>
                <td><?= e($t['note']) ?></td>
                <td><?= $t['type'] === 'utang' ? money($t['amount']) : '' ?></td>
                <td><?= $t['type'] !== 'utang' ? money($t['amount']) : '' ?></td>
                <td><?= money($t['balance_after']) ?></td>
            </tr>
        <?php } ?>
        <?php if (!$ledger) { ?>
            <tr><td colspan="5">Nothing on your list yet.</td></tr>
        <?php } ?>
    </table>
<?php } else { ?>
    <p>You do not have a suki list yet. Ask the shop to open one for you, and you can pay sa lista at checkout.</p>
<?php } ?>

<h2>Loyalty Points</h2>
<p>You have <strong><?= $points ?> points</strong>. You earn 1 point for every <?= money(LOYALTY_PESOS_PER_POINT) ?> you spend.</p>
<?php if (!$loyalty) { ?>
    <p>No points yet. Your first order starts the count.</p>
<?php } else { ?>
    <ul>
        <?php foreach ($loyalty as $l) { ?>
            <li><?= e($l['note']) ?>: <?= $l['points'] > 0 ? '+' : '' ?><?= (int) $l['points'] ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<h2>My Details</h2>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">

    <p>
        <label for="fname">First name</label><br>
        <input id="fname" name="fname" value="<?= e($customer['fname']) ?>" required>
    </p>

    <p>
        <label for="lname">Last name</label><br>
        <input id="lname" name="lname" value="<?= e($customer['lname']) ?>" required>
    </p>

    <p>
        <label for="phone">Mobile number</label><br>
        <input id="phone" name="phone" value="<?= e($customer['phone']) ?>">
    </p>

    <p>
        <label for="addressline">Address</label><br>
        <input id="addressline" name="addressline" value="<?= e($customer['addressline']) ?>">
    </p>

    <p>
        <label for="town">Town or city</label><br>
        <input id="town" name="town" value="<?= e($customer['town']) ?>">
    </p>

    <p>
        <label for="zipcode">Zip code</label><br>
        <input id="zipcode" name="zipcode" value="<?= e($customer['zipcode']) ?>">
    </p>

    <p>Login email: <?= e($_SESSION['email']) ?></p>

    <p><button type="submit">Save details</button></p>
</form>

<h2>Change password</h2>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">

    <p>
        <label for="current">Current password</label><br>
        <input type="password" id="current" name="current" required>
    </p>

    <p>
        <label for="new">New password</label><br>
        <input type="password" id="new" name="new" minlength="6" required>
    </p>

    <p>
        <label for="confirm">Confirm new password</label><br>
        <input type="password" id="confirm" name="confirm" minlength="6" required>
    </p>

    <p><button type="submit">Change password</button></p>
</form>

<?php

include("includes/footer.php"); ?>