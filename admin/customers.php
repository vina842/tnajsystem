<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$q = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE c.fname LIKE ? OR c.lname LIKE ? OR u.email LIKE ? OR c.phone LIKE ?';
    $params = array_fill(0, 4, "%$q%");
}
$rows = db_rows(
    $conn,
    "SELECT c.*, u.email, u.status AS user_status, la.balance, la.credit_limit, la.status AS lista_status,
            (SELECT COUNT(*) FROM orderinfo o WHERE o.customer_id = c.customer_id AND o.status <> 'Canceled') AS orders,
            (SELECT COALESCE(SUM(o.total_amount), 0) FROM orderinfo o WHERE o.customer_id = c.customer_id AND o.status <> 'Canceled') AS spent
     FROM customer c JOIN users u ON u.user_id = c.user_id
     LEFT JOIN lista_account la ON la.customer_id = c.customer_id
     $where ORDER BY c.lname, c.fname",
    $params
);

$pageTitle = 'Customers';
$adminActive = 'customers';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar">
  <h1 class="page-title">Customers</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/customer_form.php')) ?>"><i class="bi bi-person-plus"></i> Register a customer</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, email, or phone" aria-label="Search customers"></div>
  <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Search</button></div>
</form>

<div class="panel table-wrap">
  <table class="table mb-0">
    <thead><tr><th>Customer</th><th>Contact</th><th class="text-end">Orders</th><th class="text-end">Spent</th><th class="text-end">Points</th><th>Suki list</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a class="fw-bold" href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>"><?= e($r['lname'] . ', ' . $r['fname']) ?></a><?= $r['user_status'] !== 'active' ? ' <span class="pill b-void">Inactive</span>' : '' ?></td>
        <td><div><?= e($r['email']) ?></div><div class="muted small"><?= e($r['phone']) ?></div></td>
        <td class="text-end num"><?= (int) $r['orders'] ?></td>
        <td class="text-end num"><?= money($r['spent']) ?></td>
        <td class="text-end num"><?= (int) $r['loyalty_points'] ?></td>
        <td>
          <?php if ($r['credit_limit'] !== null): ?>
            <div class="num fw-bold"><?= money($r['balance']) ?> <span class="muted fw-normal">of <?= money($r['credit_limit']) ?></span></div>
            <?php if ($r['lista_status'] !== 'active'): ?><span class="pill b-void">Suspended</span><?php endif; ?>
          <?php else: ?>
            <span class="muted">None</span>
          <?php endif; ?>
        </td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center muted py-5">No customers found. <a href="<?= e(url('admin/customer_form.php')) ?>">Register the first one</a>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
