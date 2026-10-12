<?php
include("../includes/config.php");
require_admin();

$q = trim((string) ($_GET['q'] ?? ''));

// Optional search by name, email, or phone
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE c.fname LIKE ? OR c.lname LIKE ? OR u.email LIKE ? OR c.phone LIKE ?';
    $params = array_fill(0, 4, '%' . $q . '%');
}

$rows = db_rows($conn, "SELECT c.*, u.email, u.status AS user_status, la.balance, la.credit_limit, la.status AS lista_status,
                               (SELECT COUNT(*) FROM orderinfo o WHERE o.customer_id = c.customer_id AND o.status <> 'Canceled') AS orders,
                               (SELECT COALESCE(SUM(o.total_amount), 0) FROM orderinfo o WHERE o.customer_id = c.customer_id AND o.status <> 'Canceled') AS spent
                        FROM customer c JOIN users u ON u.user_id = c.user_id
                        LEFT JOIN lista_account la ON la.customer_id = c.customer_id
                        $where
                        ORDER BY c.lname, c.fname", $params);

$pageTitle = 'Customers';
$adminActive = 'customers';
include("../includes/admin_header.php");
?>
<h1>Customers</h1>
<p><a href="<?= e(url('admin/customer_form.php')) ?>">Register a customer</a></p>

<form method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, email, or phone" aria-label="Search customers">
    <button type="submit">Search</button>
</form>

<table border="1">
    <tr>
        <th>Customer</th>
        <th>Contact</th>
        <th>Orders</th>
        <th>Spent</th>
        <th>Points</th>
        <th>Suki list</th>
        <th></th>
    </tr>
    <?php foreach ($rows as $r) { ?>
        <tr>
            <td>
                <a href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>"><?= e($r['lname'] . ', ' . $r['fname']) ?></a>
                <?php if ($r['user_status'] !== 'active') { ?>(Inactive)<?php } ?>
            </td>
            <td><?= e($r['email']) ?><br><?= e($r['phone']) ?></td>
            <td><?= (int) $r['orders'] ?></td>
            <td><?= money($r['spent']) ?></td>
            <td><?= (int) $r['loyalty_points'] ?></td>
            <td>
                <?php if ($r['credit_limit'] !== null) { ?>
                    <?= money($r['balance']) ?> of <?= money($r['credit_limit']) ?>
                    <?php if ($r['lista_status'] !== 'active') { ?>(Suspended)<?php } ?>
                <?php } else { ?>
                    None
                <?php } ?>
            </td>
            <td><a href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>">Open</a></td>
        </tr>
    <?php } ?>
    <?php if (!$rows) { ?>
        <tr>
            <td colspan="7">No customers found. <a href="<?= e(url('admin/customer_form.php')) ?>">Register the first one</a>.</td>
        </tr>
    <?php } ?>
</table>

<?php

include("../includes/admin_footer.php"); ?>