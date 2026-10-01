<?php
require_admin();
$pageTitle = $pageTitle ?? 'Admin';
$adminActive = $adminActive ?? '';
$pendingCount = (int) db_val($conn, "SELECT COUNT(*) FROM orderinfo WHERE status IN ('Pending','Processing')");
$nav = function (string $key, string $href, string $icon, string $label, ?int $badge = null) use ($adminActive) {
    $cls = 'nav-item' . ($adminActive === $key ? ' active' : '');
    $b = $badge ? '<span class="badge-count">' . $badge . '</span>' : '';
    return '<a class="' . $cls . '" href="' . e(url($href)) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . $b . '</a>';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Admin | <?= e(SHOP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/vendor/icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="mobile-bar">
  <button type="button" data-toggle-sidebar aria-label="Open menu"><i class="bi bi-list"></i></button>
  <strong><?= e(SHOP_NAME) ?></strong>
</div>
<div class="admin-shell">
  <aside class="sidebar" aria-label="Admin menu">
    <a class="brand" href="<?= e(url('admin/index.php')) ?>"><?= e(SHOP_NAME) ?></a>
    <?= $nav('dashboard', 'admin/index.php', 'bi-speedometer2', 'Dashboard') ?>
    <?= $nav('pos', 'admin/pos.php', 'bi-cash-coin', 'Walk-in sale') ?>
    <?= $nav('orders', 'admin/orders.php', 'bi-receipt', 'Orders', $pendingCount) ?>
    <div class="group">Inventory</div>
    <?= $nav('items', 'admin/items.php', 'bi-palette2', 'Items') ?>
    <?= $nav('restock', 'admin/restock.php', 'bi-box-seam', 'Restock') ?>
    <?= $nav('lookups', 'admin/lookups.php', 'bi-tags', 'Categories and brands') ?>
    <div class="group">Customers</div>
    <?= $nav('customers', 'admin/customers.php', 'bi-people', 'Customers') ?>
    <?= $nav('lista', 'admin/lista.php', 'bi-journal-text', 'Suki list') ?>
    <div class="group">Reports</div>
    <?= $nav('rsales', 'admin/report_sales.php', 'bi-graph-up', 'Sales report') ?>
    <?= $nav('rinv', 'admin/report_inventory.php', 'bi-clipboard-data', 'Inventory report') ?>
    <div class="group">Shop</div>
    <?= $nav('store', 'index.php', 'bi-shop', 'View storefront') ?>
    <?= $nav('logout', 'logout.php', 'bi-box-arrow-left', 'Log out') ?>
  </aside>
  <div class="admin-main">
<?php render_flashes(); ?>
