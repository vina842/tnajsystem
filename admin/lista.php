<?php
include("../includes/config.php");
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
include("../includes/admin_header.php");
?>
<h1>Suki list</h1>
<p>Who owes what, and how much credit each suki has left.</p>
<p><a href="<?= e(url('admin/customers.php')) ?>">Open a list for a customer</a></p>

<p>Total utang: <strong><?= money($totalOwed) ?></strong></p>
<p>Total credit given: <strong><?= money($totalLimit) ?></strong></p>
<p>Suki accounts: <strong><?= count($rows) ?></strong></p>

<table border="1">
    <tr>
        <th>Customer</th>
        <th>Owes</th>
        <th>Limit</th>
        <th>Available</th>
        <th></th>
    </tr>
    <?php foreach ($rows as $r) { ?>
        <tr>
            <td>
                <a href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>"><?= e($r['lname'] . ', ' . $r['fname']) ?></a>
                <?php if ($r['status'] !== 'active') { ?>(Suspended)<?php } ?>
            </td>
            <td><?= money($r['balance']) ?></td>
            <td><?= money($r['credit_limit']) ?></td>
            <td><?= money(max(0, (float) $r['available_credit'])) ?></td>
            <td><a href="<?= e(url('admin/customer.php?id=' . $r['customer_id'])) ?>">Statement</a></td>
        </tr>
    <?php } ?>
    <?php if (!$rows) { ?>
        <tr>
            <td colspan="5">No suki lists yet. Open one from a customer's page.</td>
        </tr>
    <?php } ?>
</table>

<?php

include("../includes/admin_footer.php"); ?>