<?php
$pageTitle = $pageTitle ?? SHOP_NAME;
$active = $active ?? '';
$customerRow = is_logged_in() && !is_admin() ? current_customer($conn) : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(SHOP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/vendor/icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="store-body">
<div class="awning" aria-hidden="true"></div>
<header class="store-top">
  <div class="container inner">
    <a class="brand" href="<?= e(url('index.php')) ?>"><?= e(SHOP_NAME) ?></a>
    <form class="store-search" action="<?= e(url('index.php')) ?>" method="get" role="search">
      <div class="input-group">
        <input class="form-control" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search paint, brushes, paper" aria-label="Search products">
        <button class="btn btn-primary" type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
      </div>
    </form>
    <nav class="nav-links ms-lg-auto" aria-label="Main">
      <a href="<?= e(url('index.php')) ?>" class="<?= $active === 'shop' ? 'active' : '' ?>">Shop</a>
      <?php if (is_admin()): ?>
        <a href="<?= e(url('admin/index.php')) ?>">Admin</a>
      <?php elseif (is_logged_in()): ?>
        <a href="<?= e(url('orders.php')) ?>" class="<?= $active === 'orders' ? 'active' : '' ?>">My orders</a>
        <a href="<?= e(url('account.php')) ?>" class="<?= $active === 'account' ? 'active' : '' ?>">My account</a>
      <?php endif; ?>
      <a href="<?= e(url('cart.php')) ?>" class="cart-link <?= $active === 'cart' ? 'active' : '' ?>" aria-label="Cart">
        <i class="bi bi-basket2-fill"></i> Cart
        <?php if (cart_count() > 0): ?><span class="cart-count"><?= cart_count() ?></span><?php endif; ?>
      </a>
      <?php if (is_logged_in()): ?>
        <a href="<?= e(url('logout.php')) ?>">Log out</a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">Log in</a>
        <a class="btn btn-primary btn-sm text-white" href="<?= e(url('register.php')) ?>">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container py-4">
<?php render_flashes(); ?>
