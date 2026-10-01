<?php
require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

$f = ['fname' => '', 'lname' => '', 'email' => '', 'phone' => '', 'addressline' => '', 'town' => '', 'zipcode' => ''];
if (is_post()) {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = post_str($k, 100);
    }
    try {
        if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
            throw new RuntimeException('The two passwords do not match.');
        }
        $userId = register_customer($conn, $f + ['password' => (string) $_POST['password']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = strtolower($f['email']);
        $_SESSION['role'] = 'user';
        flash('success', 'Welcome, ' . $f['fname'] . '! Your account is ready.');
        redirect('index.php');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$pageTitle = 'Create an account';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <h1 class="page-title mb-3">Create an account</h1>
  <form method="post" class="panel panel-body">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-sm-6">
        <label class="form-label" for="fname">First name</label>
        <input class="form-control" id="fname" name="fname" value="<?= e($f['fname']) ?>" required>
      </div>
      <div class="col-sm-6">
        <label class="form-label" for="lname">Last name</label>
        <input class="form-control" id="lname" name="lname" value="<?= e($f['lname']) ?>" required>
      </div>
      <div class="col-12">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" value="<?= e($f['email']) ?>" required>
      </div>
      <div class="col-12">
        <label class="form-label" for="phone">Mobile number</label>
        <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($f['phone']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label" for="addressline">Address</label>
        <input class="form-control" id="addressline" name="addressline" value="<?= e($f['addressline']) ?>">
      </div>
      <div class="col-sm-7">
        <label class="form-label" for="town">Town or city</label>
        <input class="form-control" id="town" name="town" value="<?= e($f['town']) ?>">
      </div>
      <div class="col-sm-5">
        <label class="form-label" for="zipcode">Zip code</label>
        <input class="form-control" id="zipcode" name="zipcode" value="<?= e($f['zipcode']) ?>">
      </div>
      <div class="col-sm-6">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" minlength="6" required>
        <div class="form-text">At least 6 characters.</div>
      </div>
      <div class="col-sm-6">
        <label class="form-label" for="confirm">Confirm password</label>
        <input class="form-control" type="password" id="confirm" name="confirm" minlength="6" required>
      </div>
    </div>
    <button class="btn btn-primary w-100 mt-4" type="submit">Create account</button>
    <p class="muted mt-3 mb-0">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
