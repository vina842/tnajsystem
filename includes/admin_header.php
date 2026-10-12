<?php
require_admin();
$pageTitle = $pageTitle ?? 'Admin';
$adminActive = $adminActive ?? '';
$pendingCount = (int) db_val($conn, "SELECT COUNT(*) FROM orderinfo WHERE status IN ('Pending','Processing')");
$nav = function (string $key, string $href, string $label, ?int $badge = null) use ($adminActive) {
    $current = $adminActive === $key ? ' aria-current="page"' : '';
    $b = $badge ? ' (' . $badge . ')' : '';
    $text = e($label) . $b;
    if ($adminActive === $key) {
        $text = '<strong>' . $text . '</strong>';
    }
    return '<li><a href="' . e(url($href)) . '"' . $current . '>' . $text . '</a></li>';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Admin | <?= e(SHOP_NAME) ?></title>
</head>
<body>
<aside aria-label="Admin menu">
  <h1><a href="<?= e(url('admin/index.php')) ?>"><?= e(SHOP_NAME) ?></a></h1>
  <ul>
    <?= $nav('dashboard', 'admin/index.php', 'Dashboard') ?>
    <?= $nav('pos', 'admin/pos.php', 'Walk-in sale') ?>
    <?= $nav('orders', 'admin/orders.php', 'Orders', $pendingCount) ?>
  </ul>
  <h2>Inventory</h2>
  <ul>
    <?= $nav('items', 'admin/items.php', 'Items') ?>
    <?= $nav('restock', 'admin/restock.php', 'Restock') ?>
    <?= $nav('lookups', 'admin/lookups.php', 'Categories and brands') ?>
  </ul>
  <h2>Customers</h2>
  <ul>
    <?= $nav('customers', 'admin/customers.php', 'Customers') ?>
    <?= $nav('lista', 'admin/lista.php', 'Suki list') ?>
  </ul>
  <h2>Shop</h2>
  <ul>
    <?= $nav('store', 'index.php', 'View storefront') ?>
    <?= $nav('logout', 'logout.php', 'Log out') ?>
  </ul>
</aside>
<main>
<?php render_flashes(); ?>