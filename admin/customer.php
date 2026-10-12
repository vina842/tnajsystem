<?php
include("../includes/config.php");
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$customer = db_row($conn, 'SELECT c.*, u.email, u.status AS user_status
                           FROM customer c JOIN users u ON u.user_id = c.user_id
                           WHERE c.customer_id = ?', [$id]);
if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('admin/customers.php');
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $fullName = $customer['fname'] . ' ' . $customer['lname'];

    try {
        if ($action === 'profile') {
            $fname = post_str('fname', 50);
            $lname = post_str('lname', 50);
            if ($fname === '' || $lname === '') {
                throw new RuntimeException('Enter the first and last name.');
            }
            $userStatus = 'active';
            if (($_POST['user_status'] ?? '') === 'inactive') {
                $userStatus = 'inactive';
            }
            db_run($conn, 'UPDATE customer SET fname = ?, lname = ?, phone = ?, addressline = ?, town = ?, zipcode = ? WHERE customer_id = ?',
                [$fname, $lname, post_str('phone', 20), post_str('addressline', 100), post_str('town', 50), post_str('zipcode', 10), $id]);
            db_run($conn, 'UPDATE users SET status = ? WHERE user_id = ?', [$userStatus, (int) $customer['user_id']]);
            flash('success', 'Customer details saved.');

        } elseif ($action === 'open_lista') {
            $limit = post_money('credit_limit');
            db_run($conn, "INSERT INTO lista_account (customer_id, credit_limit, balance, status) VALUES (?, ?, 0, 'active')", [$id, $limit]);
            log_activity($conn, 'suki list opened', $fullName . ' limit ' . money($limit));
            flash('success', 'Suki list opened with a limit of ' . money($limit) . '.');

        } elseif ($action === 'update_lista') {
            $limit = post_money('credit_limit');
            $status = 'active';
            if (($_POST['status'] ?? '') === 'suspended') {
                $status = 'suspended';
            }
            db_run($conn, 'UPDATE lista_account SET credit_limit = ?, status = ? WHERE customer_id = ?', [$limit, $status, $id]);
            log_activity($conn, 'suki list updated', $fullName . ' limit ' . money($limit) . ' ' . $status);
            flash('success', 'Suki list updated.');

        } elseif ($action === 'payment') {
            $amount = post_money('amount');
            $newBalance = record_lista_payment($conn, $id, $amount, post_str('note', 150));
            log_activity($conn, 'suki payment', $fullName . ' ' . money($amount));
            flash('success', 'Payment of ' . money($amount) . ' recorded. New balance: ' . money($newBalance) . '.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (mysqli_sql_exception $e) {
        flash('danger', 'That did not go through, and nothing was changed. Please try again.');
    }
    redirect('admin/customer.php?id=' . $id);
}

$lista = db_row($conn, 'SELECT * FROM lista_account WHERE customer_id = ?', [$id]);

$ledger = [];
if ($lista) {
    $ledger = db_rows($conn, 'SELECT * FROM lista_transaction WHERE lista_id = ? ORDER BY lista_trans_id', [(int) $lista['lista_id']]);
}

$orders = db_rows($conn, 'SELECT * FROM orderinfo WHERE customer_id = ? ORDER BY orderinfo_id DESC LIMIT 15', [$id]);
$points = db_rows($conn, 'SELECT * FROM loyalty_transaction WHERE customer_id = ? ORDER BY loyalty_trans_id DESC LIMIT 8', [$id]);

$pageTitle = $customer['fname'] . ' ' . $customer['lname'];
$adminActive = 'customers';
include("../includes/admin_header.php");
?>
<h1><?= e($customer['fname'] . ' ' . $customer['lname']) ?></h1>
<p><?= e($customer['email']) ?>, <?= (int) $customer['loyalty_points'] ?> loyalty points</p>
<p>
    <a href="<?= e(url('admin/pos.php')) ?>">New sale</a> |
    <a href="<?= e(url('admin/customers.php')) ?>">All customers</a>
</p>

<h2>Suki list</h2>
<?php if (!$lista) { ?>
    <p>This customer has no suki list. Set a credit limit to let them buy sa lista.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="open_lista">
        <p>
            <label for="open_limit">Credit limit</label><br>
            <input id="open_limit" name="credit_limit" inputmode="decimal" required>
        </p>
        <p><button type="submit">Open suki list</button></p>
    </form>
<?php } else { ?>
    <?php if ($lista['status'] !== 'active') { ?>
        <p><strong>Suspended</strong></p>
    <?php } ?>
    <p>Credit limit: <?= money($lista['credit_limit']) ?></p>
    <p>Balance owed: <?= money($lista['balance']) ?></p>
    <p>Available: <?= money(max(0, (float) $lista['credit_limit'] - (float) $lista['balance'])) ?></p>

    <h3>Record a payment</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="payment">
        <p>
            <label for="amount">Amount received (₱)</label><br>
            <input id="amount" name="amount" inputmode="decimal" required>
        </p>
        <p>
            <label for="note">Note (optional)</label><br>
            <input id="note" name="note">
        </p>
        <p><button type="submit" <?= (float) $lista['balance'] <= 0 ? 'disabled' : '' ?>>Save payment</button></p>
    </form>

    <h3>Update the list</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="update_lista">
        <p>
            <label for="credit_limit">Credit limit</label><br>
            <input id="credit_limit" name="credit_limit" inputmode="decimal" value="<?= e($lista['credit_limit']) ?>" required>
        </p>
        <p>
            <label for="status">Status</label><br>
            <select id="status" name="status">
                <option value="active" <?= $lista['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $lista['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
        </p>
        <p><button type="submit">Update list</button></p>
    </form>

    <h3>Ledger</h3>
    <table border="1">
        <tr>
            <th>Date</th>
            <th>Details</th>
            <th>Utang</th>
            <th>Paid</th>
            <th>Balance</th>
        </tr>
        <?php foreach ($ledger as $t) { ?>
            <?php $isDebit = ($t['type'] === 'utang'); ?>
            <tr>
                <td><?= e(format_dt($t['created_at'], 'M j, Y')) ?></td>
                <td><?= e($t['note']) ?><?= $t['type'] === 'reversal' ? ' (reversal)' : '' ?></td>
                <td><?= $isDebit ? money($t['amount']) : '' ?></td>
                <td><?= !$isDebit ? money($t['amount']) : '' ?></td>
                <td><?= money($t['balance_after']) ?></td>
            </tr>
        <?php } ?>
        <?php if (!$ledger) { ?>
            <tr><td colspan="5">No entries yet.</td></tr>
        <?php } ?>
        <tr>
            <td colspan="4"><strong>Balance owed</strong></td>
            <td><strong><?= money($lista['balance']) ?></strong></td>
        </tr>
    </table>
<?php } ?>

<h2>Recent orders</h2>
<table border="1">
    <tr>
        <th>Order</th>
        <th>Date</th>
        <th>Total</th>
        <th>Status</th>
        <th>Payment</th>
    </tr>
    <?php foreach ($orders as $o) { ?>
        <tr>
            <td><a href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>"><?= order_label((int) $o['orderinfo_id']) ?></a></td>
            <td><?= e(format_dt($o['date_placed'], 'M j, Y')) ?></td>
            <td><?= money($o['total_amount']) ?></td>
            <td><?= status_badge($o['status']) ?></td>
            <td><?= payment_badge($o['payment_method'], $o['payment_status']) ?></td>
        </tr>
    <?php } ?>
    <?php if (!$orders) { ?>
        <tr><td colspan="5">No orders yet.</td></tr>
    <?php } ?>
</table>

<h2>Details</h2>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
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
    <p>
        <label for="user_status">Account</label><br>
        <select id="user_status" name="user_status">
            <option value="active" <?= $customer['user_status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $customer['user_status'] === 'inactive' ? 'selected' : '' ?>>Inactive (cannot log in)</option>
        </select>
    </p>
    <p><button type="submit">Save details</button></p>
</form>

<h2>Loyalty points: <?= (int) $customer['loyalty_points'] ?></h2>
<?php if (!$points) { ?>
    <p>No points yet.</p>
<?php } else { ?>
    <ul>
        <?php foreach ($points as $p) { ?>
            <li><?= e($p['note']) ?>: <?= $p['points'] > 0 ? '+' : '' ?><?= (int) $p['points'] ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<?php

include("../includes/admin_footer.php"); ?>