<?php
require_once __DIR__ . '/includes/config.php';

$next = safe_next($_GET['next'] ?? ($_POST['next'] ?? ''));
if (is_logged_in()) {
    redirect($next ?: (is_admin() ? 'admin/index.php' : 'index.php'));
}

$email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $user = db_row($conn, 'SELECT user_id, email, password, role, status FROM users WHERE email = ?', [$email]);
    if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password'])) {
        if ($user['status'] !== 'active') {
            flash('danger', 'This account is inactive. Ask the shop to turn it back on.');
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            redirect($next ?: ($user['role'] === 'admin' ? 'admin/index.php' : 'index.php'));
        }
    } else {
        flash('danger', 'Wrong email or password. Check them and try again.');
    }
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <h1 class="page-title mb-3">Log in</h1>
  <form method="post" class="panel panel-body">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Log in</button>
    <p class="muted mt-3 mb-0">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
