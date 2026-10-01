<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$rows = db_rows($conn, 'SELECT * FROM lista_balances ORDER BY balance DESC, lname, fname');
$totalOwed = 0.0;
$totalLimit = 0.0;
foreach ($rows as $r) {
    $totalOwed += (float) $r['balance'];
    $totalLimit += (float) $r['credit_limit'];
}

$pageTitle = 'Suki list';
$adminActive = 'lista';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <div><h1 class="page-title">Suki list</h1><div class="muted">Who owes what, and how much credit each suki has left.</div></div>
  <a class="btn btn-primary" href="<?= e(url('admin/customers.php')) ?>">Open a list for a customer</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-4"><div class="kpi"><div class="label">Total utang</div><div class="value"><?= money($totalOwed) ?></div></div></div>
  <div class="col-sm-4"><div class="kpi"><div class="label">Total credit given</div><div class="value"><?= money($totalLimit) ?></div></div></div>
  <div class="col-sm-4"><div class="kpi"><div class="label">Suki accounts</div><div class="value"><?= count($rows) ?></div></div></div>
</div>

<div class="panel table-wrap">
  <table class="table mb-0">
    <thead><tr><th>Customer</th><th class="text-end">Owes</th><th class="text-end">Limit</th><th class="text-end">Available</th><th style="min-width:140px">Used</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $used = (float) $r['credit_limit'] > 0 ? min(100, (float) $r['balance'] / (float) $r['credit_limit'] * 100) : 0; ?>
      <tr>
        <td><a class="fw-bold" href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>"><?= e($r['lname'] . ', ' . $r['fname']) ?></a><?= $r['status'] !== 'active' ? ' <span class="pill b-void">Suspended</span>' : '' ?></td>
        <td class="text-end num fw-bold"><?= money($r['balance']) ?></td>
        <td class="text-end num"><?= money($r['credit_limit']) ?></td>
        <td class="text-end num"><?= money(max(0, (float) $r['available_credit'])) ?></td>
        <td><div class="credit-meter <?= $used >= 85 ? 'is-high' : '' ?>"><span style="width:<?= round($used) ?>%"></span></div></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>">Statement</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center muted py-5">No suki lists yet. Open one from a customer's page.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
