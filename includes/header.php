<?php
$pageTitle = $pageTitle ?? SHOP_NAME;
$active = $active ?? '';
$customerRow = is_logged_in() && !is_admin() ? current_customer($conn) : null;

$link = function (string $key, string $href, string $label) use ($active) {
    $current = $active === $key ? ' aria-current="page"' : '';
    $text = $active === $key ? '<strong>' . e($label) . '</strong>' : e($label);
    return '<a href="' . e(url($href)) . '"' . $current . '>' . $text . '</a>';
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(SHOP_NAME) ?></title>
</head>
<body>
<header>
  <a href="<?= e(url('index.php')) ?>"><?= e(SHOP_NAME) ?></a>
  <form action="<?= e(url('index.php')) ?>" method="get" role="search">
    <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search paint, brushes, paper" aria-label="Search products">
    <button type="submit">Search</button>
  </form>
  <nav aria-label="Main">
    <?= $link('shop', 'index.php', 'Shop') ?>
    <?php if (is_admin()): ?>
      <a href="<?= e(url('admin/index.php')) ?>">Admin</a>
    <?php elseif (is_logged_in()): ?>
      <?= $link('orders', 'orders.php', 'My orders') ?>
      <?= $link('account', 'account.php', 'My account') ?>
    <?php endif; ?>
    <?= $link('cart', 'cart.php', 'Cart' . (cart_count() > 0 ? ' (' . cart_count() . ')' : '')) ?>
    <?php if (is_logged_in()): ?>
      <a href="<?= e(url('logout.php')) ?>">Log out</a>
    <?php else: ?>
      <a href="<?= e(url('login.php')) ?>">Log in</a>
      <a href="<?= e(url('register.php')) ?>">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main>
<?php render_flashes(); ?>