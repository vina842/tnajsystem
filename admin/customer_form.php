<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$f = ['fname' => '', 'lname' => '', 'email' => '', 'phone' => '', 'addressline' => '', 'town' => '', 'zipcode' => ''];
if (is_post()) {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = post_str($k, 100);
    }
    try {
        $userId = register_customer($conn, $f + ['password' => (string) ($_POST['password'] ?? '')]);
        $cid = (int) db_val($conn, 'SELECT customer_id FROM customer WHERE user_id = ?', [$userId]);
        $limit = trim((string) ($_POST['credit_limit'] ?? ''));
        if ($limit !== '' && is_numeric($limit) && (float) $limit > 0) {
            db_run($conn, "INSERT INTO lista_account (customer_id, credit_limit, balance, status) VALUES (?, ?, 0, 'active')", [$cid, round((float) $limit, 2)]);
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
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <h1 class="page-title">Register a customer</h1>
  <a class="btn btn-outline-primary" href="<?= e(url('admin/customers.php')) ?>">Back to customers</a>
</div>
<form method="post" class="panel panel-body" style="max-width:760px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="fname">First name</label><input class="form-control" id="fname" name="fname" value="<?= e($f['fname']) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="lname">Last name</label><input class="form-control" id="lname" name="lname" value="<?= e($f['lname']) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="email">Email (they log in with this)</label><input class="form-control" type="email" id="email" name="email" value="<?= e($f['email']) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="password">Starting password</label><input class="form-control" id="password" name="password" minlength="6" required><div class="form-text">Tell the customer to change it in My account.</div></div>
    <div class="col-md-6"><label class="form-label" for="phone">Mobile number</label><input class="form-control" id="phone" name="phone" value="<?= e($f['phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="addressline">Address</label><input class="form-control" id="addressline" name="addressline" value="<?= e($f['addressline']) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="town">Town or city</label><input class="form-control" id="town" name="town" value="<?= e($f['town']) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="zipcode">Zip code</label><input class="form-control" id="zipcode" name="zipcode" value="<?= e($f['zipcode']) ?>"></div>
    <div class="col-12"><hr class="my-1"></div>
    <div class="col-md-6"><label class="form-label" for="credit_limit">Open a suki list with this credit limit (optional)</label><input class="form-control" id="credit_limit" name="credit_limit" inputmode="decimal" placeholder="Leave empty for no suki list"></div>
  </div>
  <button class="btn btn-primary mt-4" type="submit">Register customer</button>
</form>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
