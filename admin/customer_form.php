<?php
include("../includes/config.php");
require_admin();

$f = [
    'fname' => '',
    'lname' => '',
    'email' => '',
    'phone' => '',
    'addressline' => '',
    'town' => '',
    'zipcode' => '',
];

if (is_post()) {
    csrf_check();

    foreach ($f as $key => $value) {
        $f[$key] = post_str($key, 100);
    }

    try {
        $data = $f;
        $data['password'] = (string) ($_POST['password'] ?? '');
        $userId = register_customer($conn, $data);

        $cid = (int) db_val($conn, 'SELECT customer_id FROM customer WHERE user_id = ?', [$userId]);

        $limit = trim((string) ($_POST['credit_limit'] ?? ''));
        if ($limit !== '' && is_numeric($limit) && (float) $limit > 0) {
            db_run($conn, "INSERT INTO lista_account (customer_id, credit_limit, balance, status) VALUES (?, ?, 0, 'active')",
                [$cid, round((float) $limit, 2)]);
        }

        log_activity($conn, 'customer registered', $f['fname'] . ' ' . $f['lname']);
        flash('success', $f['fname'] . ' ' . $f['lname'] . ' is registered.');
        redirect('admin/customer.php?id=' . $cid);
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$pageTitle = 'Register a customer';
$adminActive = 'customers';
include("../includes/admin_header.php");
?>
<h1>Register a customer</h1>
<p><a href="<?= e(url('admin/customers.php')) ?>">Back to customers</a></p>

<form method="post">
    <?= csrf_field() ?>

    <p>
        <label for="fname">First name</label><br>
        <input id="fname" name="fname" value="<?= e($f['fname']) ?>" required>
    </p>

    <p>
        <label for="lname">Last name</label><br>
        <input id="lname" name="lname" value="<?= e($f['lname']) ?>" required>
    </p>

    <p>
        <label for="email">Email (they log in with this)</label><br>
        <input type="email" id="email" name="email" value="<?= e($f['email']) ?>" required>
    </p>

    <p>
        <label for="password">Starting password (tell the customer to change it in My account)</label><br>
        <input id="password" name="password" minlength="6" required>
    </p>

    <p>
        <label for="phone">Mobile number</label><br>
        <input id="phone" name="phone" value="<?= e($f['phone']) ?>">
    </p>

    <p>
        <label for="addressline">Address</label><br>
        <input id="addressline" name="addressline" value="<?= e($f['addressline']) ?>">
    </p>

    <p>
        <label for="town">Town or city</label><br>
        <input id="town" name="town" value="<?= e($f['town']) ?>">
    </p>

    <p>
        <label for="zipcode">Zip code</label><br>
        <input id="zipcode" name="zipcode" value="<?= e($f['zipcode']) ?>">
    </p>

    <hr>

    <p>
        <label for="credit_limit">Open a suki list with this credit limit (optional)</label><br>
        <input id="credit_limit" name="credit_limit" inputmode="decimal" placeholder="Leave empty for no suki list">
    </p>

    <p><button type="submit">Register customer</button></p>
</form>

<?php

include("../includes/admin_footer.php"); ?>