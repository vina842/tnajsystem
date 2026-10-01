<?php
require_once __DIR__ . '/includes/config.php';
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
        if (($_POST['action'] ?? '') === 'profile') {
            $fname = post_str('fname', 50);
            $lname = post_str('lname', 50);
            if ($fname === '' || $lname === '') {
                throw new RuntimeException('Enter your first and last name.');
            }
            db_run(
                $conn,
                'UPDATE customer SET fname = ?, lname = ?, phone = ?, addressline = ?, town = ?, zipcode = ? WHERE customer_id = ?',
                [$fname, $lname, post_str('phone', 20), post_str('addressline', 100), post_str('town', 50), post_str('zipcode', 10), $cid]
            );
            flash('success', 'Profile saved.');
        } elseif (($_POST['action'] ?? '') === 'password') {
            $hash = db_val($conn, 'SELECT password FROM users WHERE user_id = ?', [(int) $_SESSION['user_id']]);
            if (!password_verify((string) ($_POST['current'] ?? ''), (string) $hash)) {
                throw new RuntimeException('Your current password is not correct.');
            }
            $new = (string) ($_POST['new'] ?? '');
            if (strlen($new) < 6) {
                throw new RuntimeException('The new password needs at least 6 characters.');
            }
            if ($new !== ($_POST['confirm'] ?? '')) {
                throw new RuntimeException('The two new passwords do not match.');
            }
            db_run($conn, 'UPDATE users SET password = ? WHERE user_id = ?', [password_hash($new, PASSWORD_BCRYPT), (int) $_SESSION['user_id']]);
            flash('success', 'Password changed.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('account.php');
}

$lista = db_row($conn, 'SELECT * FROM lista_account WHERE customer_id = ?', [$cid]);
$ledger = $lista ? db_rows($conn, 'SELECT * FROM lista_transaction WHERE lista_id = ? ORDER BY lista_trans_id', [(int) $lista['lista_id']]) : [];
$points = (int) db_val($conn, 'SELECT loyalty_points FROM customer WHERE customer_id = ?', [$cid]);
$loyalty = db_rows($conn, 'SELECT * FROM loyalty_transaction WHERE customer_id = ? ORDER BY loyalty_trans_id DESC LIMIT 10', [$cid]);
$customer = db_row($conn, 'SELECT * FROM customer WHERE customer_id = ?', [$cid]);

$pageTitle = 'My account';
$active = 'account';
include __DIR__ . '/includes/header.php';
?>
<h1 class="page-title mb-3">My account</h1>
<div class="row g-4">
  <div class="col-lg-7">
    <?php if ($lista): $used = (float) $lista['credit_limit'] > 0 ? min(100, (float) $lista['balance'] / (float) $lista['credit_limit'] * 100) : 0; ?>
    <section class="panel mb-4">
      <div class="panel-head">
        <h2>My suki list</h2>
        <?php if ($lista['status'] !== 'active'): ?><span class="pill b-void">Suspended</span><?php endif; ?>
      </div>
      <div class="panel-body">
        <div class="row text-center g-3 mb-3">
          <div class="col-4"><div class="muted small">Credit limit</div><div class="fs-5 fw-bold num"><?= money($lista['credit_limit']) ?></div></div>
          <div class="col-4"><div class="muted small">I owe</div><div class="fs-5 fw-bold num"><?= money($lista['balance']) ?></div></div>
          <div class="col-4"><div class="muted small">Still available</div><div class="fs-5 fw-bold num"><?= money(max(0, (float) $lista['credit_limit'] - (float) $lista['balance'])) ?></div></div>
        </div>
        <div class="credit-meter <?= $used >= 85 ? 'is-high' : '' ?>"><span style="width:<?= round($used) ?>%"></span></div>
      </div>
      <div class="table-wrap">
        <table class="ledger">
          <thead><tr><th>Date</th><th>Details</th><th class="r">Utang</th><th class="r">Paid</th><th class="r">Balance</th></tr></thead>
          <tbody>
          <?php foreach ($ledger as $t): $isDebit = $t['type'] === 'utang'; ?>
            <tr>
              <td><?= e(format_dt($t['created_at'], 'M j, Y')) ?></td>
              <td><?= e($t['note']) ?></td>
              <td class="r debit"><?= $isDebit ? money($t['amount']) : '' ?></td>
              <td class="r credit"><?= !$isDebit ? money($t['amount']) : '' ?></td>
              <td class="r"><?= money($t['balance_after']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ledger): ?><tr><td colspan="5" class="muted text-center py-4">Nothing on your list yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php else: ?>
    <section class="panel panel-body mb-4">
      <h2 class="h5">Suki list</h2>
      <p class="muted mb-0">You do not have a suki list yet. Ask the shop to open one for you, and you can pay sa lista at checkout.</p>
    </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head"><h2>Loyalty points</h2><strong class="num"><?= $points ?> points</strong></div>
      <div class="panel-body">
        <p class="muted small">You earn 1 point for every <?= money(LOYALTY_PESOS_PER_POINT) ?> you spend.</p>
        <?php if (!$loyalty): ?>
          <p class="mb-0 muted">No points yet. Your first order starts the count.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($loyalty as $l): ?>
              <li class="d-flex justify-content-between py-1 border-bottom"><span><?= e($l['note']) ?></span><strong class="num"><?= $l['points'] > 0 ? '+' : '' ?><?= (int) $l['points'] ?></strong></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="col-lg-5">
    <form method="post" class="panel mb-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <div class="panel-head"><h2>My details</h2></div>
      <div class="panel-body">
        <div class="row g-3">
          <div class="col-6"><label class="form-label" for="fname">First name</label><input class="form-control" id="fname" name="fname" value="<?= e($customer['fname']) ?>" required></div>
          <div class="col-6"><label class="form-label" for="lname">Last name</label><input class="form-control" id="lname" name="lname" value="<?= e($customer['lname']) ?>" required></div>
          <div class="col-12"><label class="form-label" for="phone">Mobile number</label><input class="form-control" id="phone" name="phone" value="<?= e($customer['phone']) ?>"></div>
          <div class="col-12"><label class="form-label" for="addressline">Address</label><input class="form-control" id="addressline" name="addressline" value="<?= e($customer['addressline']) ?>"></div>
          <div class="col-7"><label class="form-label" for="town">Town or city</label><input class="form-control" id="town" name="town" value="<?= e($customer['town']) ?>"></div>
          <div class="col-5"><label class="form-label" for="zipcode">Zip code</label><input class="form-control" id="zipcode" name="zipcode" value="<?= e($customer['zipcode']) ?>"></div>
        </div>
        <p class="muted small mt-3 mb-2">Login email: <?= e($_SESSION['email']) ?></p>
        <button class="btn btn-primary" type="submit">Save details</button>
      </div>
    </form>

    <form method="post" class="panel">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="panel-head"><h2>Change password</h2></div>
      <div class="panel-body">
        <div class="mb-3"><label class="form-label" for="current">Current password</label><input class="form-control" type="password" id="current" name="current" required></div>
        <div class="mb-3"><label class="form-label" for="new">New password</label><input class="form-control" type="password" id="new" name="new" minlength="6" required></div>
        <div class="mb-3"><label class="form-label" for="confirm">Confirm new password</label><input class="form-control" type="password" id="confirm" name="confirm" minlength="6" required></div>
        <button class="btn btn-outline-primary" type="submit">Change password</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
