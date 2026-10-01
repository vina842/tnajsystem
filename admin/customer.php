<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$customer = db_row($conn, 'SELECT c.*, u.email, u.status AS user_status FROM customer c JOIN users u ON u.user_id = c.user_id WHERE c.customer_id = ?', [$id]);
if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('admin/customers.php');
}

if (is_post()) {
    csrf_check();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'profile') {
            $fname = post_str('fname', 50);
            $lname = post_str('lname', 50);
            if ($fname === '' || $lname === '') {
                throw new RuntimeException('Enter the first and last name.');
            }
            $active = ($_POST['user_status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            db_run($conn, 'UPDATE customer SET fname = ?, lname = ?, phone = ?, addressline = ?, town = ?, zipcode = ? WHERE customer_id = ?',
                [$fname, $lname, post_str('phone', 20), post_str('addressline', 100), post_str('town', 50), post_str('zipcode', 10), $id]);
            db_run($conn, 'UPDATE users SET status = ? WHERE user_id = ?', [$active, (int) $customer['user_id']]);
            flash('success', 'Customer details saved.');
        } elseif ($action === 'open_lista') {
            $limit = post_money('credit_limit');
            db_run($conn, "INSERT INTO lista_account (customer_id, credit_limit, balance, status) VALUES (?, ?, 0, 'active')", [$id, $limit]);
            log_activity($conn, 'suki list opened', $customer['fname'] . ' ' . $customer['lname'] . ' limit ' . money($limit));
            flash('success', 'Suki list opened with a limit of ' . money($limit) . '.');
        } elseif ($action === 'update_lista') {
            $limit = post_money('credit_limit');
            $status = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
            db_run($conn, 'UPDATE lista_account SET credit_limit = ?, status = ? WHERE customer_id = ?', [$limit, $status, $id]);
            log_activity($conn, 'suki list updated', $customer['fname'] . ' ' . $customer['lname'] . ' limit ' . money($limit) . ' ' . $status);
            flash('success', 'Suki list updated.');
        } elseif ($action === 'payment') {
            $amount = post_money('amount');
            $newBalance = record_lista_payment($conn, $id, $amount, post_str('note', 150));
            log_activity($conn, 'suki payment', $customer['fname'] . ' ' . $customer['lname'] . ' ' . money($amount));
            flash('success', 'Payment of ' . money($amount) . ' recorded. New balance: ' . money($newBalance) . '.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (mysqli_sql_exception $e) {
        flash('danger', 'That did not go through, and nothing was changed. Please try again.');
    }
    redirect('admin/customer.php?id=' . $id);
}

$customer = db_row($conn, 'SELECT c.*, u.email, u.status AS user_status FROM customer c JOIN users u ON u.user_id = c.user_id WHERE c.customer_id = ?', [$id]);
$lista = db_row($conn, 'SELECT * FROM lista_account WHERE customer_id = ?', [$id]);
$ledger = $lista ? db_rows($conn, 'SELECT * FROM lista_transaction WHERE lista_id = ? ORDER BY lista_trans_id', [(int) $lista['lista_id']]) : [];
$orders = db_rows($conn, 'SELECT * FROM orderinfo WHERE customer_id = ? ORDER BY orderinfo_id DESC LIMIT 15', [$id]);
$points = db_rows($conn, 'SELECT * FROM loyalty_transaction WHERE customer_id = ? ORDER BY loyalty_trans_id DESC LIMIT 8', [$id]);
$used = $lista && (float) $lista['credit_limit'] > 0 ? min(100, (float) $lista['balance'] / (float) $lista['credit_limit'] * 100) : 0;

$pageTitle = $customer['fname'] . ' ' . $customer['lname'];
$adminActive = 'customers';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div>
    <h1 class="page-title"><?= e($customer['fname'] . ' ' . $customer['lname']) ?></h1>
    <div class="muted"><?= e($customer['email']) ?>, <?= (int) $customer['loyalty_points'] ?> loyalty points</div>
  </div>
  <div class="d-flex gap-2 no-print">
    <a class="btn btn-yellow" href="<?= e(url('admin/pos.php')) ?>">New sale</a>
    <a class="btn btn-outline-primary" href="<?= e(url('admin/customers.php')) ?>">All customers</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <section class="panel mb-4">
      <div class="panel-head"><h2>Suki list</h2><?php if ($lista && $lista['status'] !== 'active'): ?><span class="pill b-void">Suspended</span><?php endif; ?></div>
      <?php if (!$lista): ?>
        <form method="post" class="panel-body">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="open_lista">
          <p class="muted">This customer has no suki list. Set a credit limit to let them buy sa lista.</p>
          <div class="row g-2 align-items-end">
            <div class="col-sm-6"><label class="form-label" for="open_limit">Credit limit</label><input class="form-control" id="open_limit" name="credit_limit" inputmode="decimal" required></div>
            <div class="col-sm-auto"><button class="btn btn-primary" type="submit">Open suki list</button></div>
          </div>
        </form>
      <?php else: ?>
        <div class="panel-body">
          <div class="row text-center g-3 mb-3">
            <div class="col-4"><div class="muted small">Credit limit</div><div class="fs-5 fw-bold num"><?= money($lista['credit_limit']) ?></div></div>
            <div class="col-4"><div class="muted small">Balance owed</div><div class="fs-5 fw-bold num"><?= money($lista['balance']) ?></div></div>
            <div class="col-4"><div class="muted small">Available</div><div class="fs-5 fw-bold num"><?= money(max(0, (float) $lista['credit_limit'] - (float) $lista['balance'])) ?></div></div>
          </div>
          <div class="credit-meter mb-4 <?= $used >= 85 ? 'is-high' : '' ?>"><span style="width:<?= round($used) ?>%"></span></div>
          <div class="row g-3 no-print">
            <form method="post" class="col-md-6">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="payment">
              <label class="form-label" for="amount">Record a payment</label>
              <div class="input-group mb-2"><span class="input-group-text">₱</span><input class="form-control" id="amount" name="amount" inputmode="decimal" placeholder="Amount received" required></div>
              <input class="form-control mb-2" name="note" placeholder="Note (optional)" aria-label="Note">
              <button class="btn btn-primary w-100" type="submit" <?= (float) $lista['balance'] <= 0 ? 'disabled' : '' ?>>Save payment</button>
            </form>
            <form method="post" class="col-md-6">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="update_lista">
              <label class="form-label" for="credit_limit">Credit limit</label>
              <input class="form-control mb-2" id="credit_limit" name="credit_limit" inputmode="decimal" value="<?= e($lista['credit_limit']) ?>" required>
              <select class="form-select mb-2" name="status" aria-label="Suki list status">
                <option value="active" <?= $lista['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $lista['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
              </select>
              <button class="btn btn-outline-primary w-100" type="submit">Update list</button>
            </form>
          </div>
        </div>
        <div class="table-wrap">
          <table class="ledger">
            <thead><tr><th>Date</th><th>Details</th><th class="r">Utang</th><th class="r">Paid</th><th class="r">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($ledger as $t): $isDebit = $t['type'] === 'utang'; ?>
              <tr>
                <td class="text-nowrap"><?= e(format_dt($t['created_at'], 'M j, Y')) ?></td>
                <td><?= e($t['note']) ?><?= $t['type'] === 'reversal' ? ' (reversal)' : '' ?></td>
                <td class="r debit"><?= $isDebit ? money($t['amount']) : '' ?></td>
                <td class="r credit"><?= !$isDebit ? money($t['amount']) : '' ?></td>
                <td class="r"><?= money($t['balance_after']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$ledger): ?><tr><td colspan="5" class="muted text-center py-4">No entries yet.</td></tr><?php endif; ?>
            </tbody>
            <tfoot><tr><td colspan="4" class="text-end">Balance owed</td><td class="r"><?= money($lista['balance']) ?></td></tr></tfoot>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Recent orders</h2></div>
      <div class="table-wrap">
        <table class="table mb-0">
          <thead><tr><th>Order</th><th>Date</th><th class="text-end">Total</th><th>Status</th><th>Payment</th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><a class="fw-bold" href="<?= e(url('admin/order.php?id=' . $o['orderinfo_id'])) ?>"><?= order_label((int) $o['orderinfo_id']) ?></a></td>
              <td><?= e(format_dt($o['date_placed'], 'M j, Y')) ?></td>
              <td class="text-end num"><?= money($o['total_amount']) ?></td>
              <td><?= status_badge($o['status']) ?></td>
              <td><?= payment_badge($o['payment_method'], $o['payment_status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$orders): ?><tr><td colspan="5" class="muted text-center py-4">No orders yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="col-xl-5 no-print">
    <form method="post" class="panel mb-4">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="profile">
      <div class="panel-head"><h2>Details</h2></div>
      <div class="panel-body">
        <div class="row g-3">
          <div class="col-6"><label class="form-label" for="fname">First name</label><input class="form-control" id="fname" name="fname" value="<?= e($customer['fname']) ?>" required></div>
          <div class="col-6"><label class="form-label" for="lname">Last name</label><input class="form-control" id="lname" name="lname" value="<?= e($customer['lname']) ?>" required></div>
          <div class="col-12"><label class="form-label" for="phone">Mobile number</label><input class="form-control" id="phone" name="phone" value="<?= e($customer['phone']) ?>"></div>
          <div class="col-12"><label class="form-label" for="addressline">Address</label><input class="form-control" id="addressline" name="addressline" value="<?= e($customer['addressline']) ?>"></div>
          <div class="col-7"><label class="form-label" for="town">Town or city</label><input class="form-control" id="town" name="town" value="<?= e($customer['town']) ?>"></div>
          <div class="col-5"><label class="form-label" for="zipcode">Zip code</label><input class="form-control" id="zipcode" name="zipcode" value="<?= e($customer['zipcode']) ?>"></div>
          <div class="col-12">
            <label class="form-label" for="user_status">Account</label>
            <select class="form-select" id="user_status" name="user_status"><option value="active" <?= $customer['user_status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $customer['user_status'] === 'inactive' ? 'selected' : '' ?>>Inactive (cannot log in)</option></select>
          </div>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Save details</button>
      </div>
    </form>

    <section class="panel">
      <div class="panel-head"><h2>Loyalty points</h2><strong class="num"><?= (int) $customer['loyalty_points'] ?></strong></div>
      <div class="panel-body">
        <?php foreach ($points as $p): ?>
          <div class="d-flex justify-content-between py-1 border-bottom"><span><?= e($p['note']) ?></span><strong class="num"><?= $p['points'] > 0 ? '+' : '' ?><?= (int) $p['points'] ?></strong></div>
        <?php endforeach; ?>
        <?php if (!$points): ?><span class="muted">No points yet.</span><?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
