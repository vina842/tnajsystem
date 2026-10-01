<?php
require_once __DIR__ . '/includes/config.php';

$q = trim((string) ($_GET['q'] ?? ''));
$catId = (int) ($_GET['category'] ?? 0);

$where = ['i.is_active = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(i.description LIKE ? OR b.name LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($catId > 0) {
    $where[] = 'i.category_id = ?';
    $params[] = $catId;
}

$items = db_rows(
    $conn,
    'SELECT i.item_id, i.description, i.sell_price, i.img_path, c.name AS category, b.name AS brand, s.quantity, s.reorder_threshold
     FROM item i
     JOIN stock s ON s.item_id = i.item_id
     LEFT JOIN category c ON c.category_id = i.category_id
     LEFT JOIN brand b ON b.brand_id = i.brand_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY c.name, i.description',
    $params
);
$categories = db_rows($conn, 'SELECT category_id, name FROM category ORDER BY name');
$showHero = $q === '' && $catId === 0;

$pageTitle = 'Art supplies';
$active = 'shop';
include __DIR__ . '/includes/header.php';
?>

<?php if ($showHero): ?>
<section class="hero">
  <div>
    <h1>Art supplies for every barkada.</h1>
    <p>Pick what you need, reserve it online, and pay at the counter. Suki customers can put it sa lista.</p>
    <?php if (!is_logged_in()): ?>
      <a class="btn btn-yellow" href="<?= e(url('register.php')) ?>">Create an account</a>
    <?php endif; ?>
  </div>
  <div class="chips">
    <?php foreach (array_slice($categories, 0, 6) as $cat): [$bg] = swatch_colors($cat['name']); ?>
      <a class="chip" href="<?= e(url('index.php?category=' . $cat['category_id'])) ?>">
        <div class="color" style="background:<?= $bg ?>"></div>
        <div class="label"><?= e($cat['name']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php else: ?>
<div class="d-flex align-items-baseline justify-content-between flex-wrap gap-2 mb-2">
  <h1 class="page-title"><?= $q !== '' ? 'Results for "' . e($q) . '"' : 'Shop' ?></h1>
  <span class="muted"><?= count($items) ?> <?= count($items) === 1 ? 'product' : 'products' ?></span>
</div>
<?php endif; ?>

<div class="filter-row" role="navigation" aria-label="Categories">
  <a href="<?= e(url('index.php')) ?>" class="<?= $catId === 0 && $q === '' ? 'active' : '' ?>">All</a>
  <?php foreach ($categories as $cat): ?>
    <a href="<?= e(url('index.php?category=' . $cat['category_id'])) ?>" class="<?= $catId === (int) $cat['category_id'] ? 'active' : '' ?>"><?= e($cat['name']) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$items): ?>
  <div class="panel panel-body text-center py-5">
    <h2 class="h5">No products found</h2>
    <p class="muted mb-3">Try a different word, or browse everything we have.</p>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Show all products</a>
  </div>
<?php else: ?>
  <div class="product-grid">
    <?php foreach ($items as $it): $out = (int) $it['quantity'] <= 0; ?>
      <article class="product <?= $out ? 'is-out' : '' ?>">
        <div class="pic"><?= item_visual($it) ?></div>
        <div class="name"><?= e($it['description']) ?></div>
        <div class="meta"><?= e(trim(($it['brand'] ?? '') . ($it['brand'] && $it['category'] ? ', ' : '') . ($it['category'] ?? ''))) ?></div>
        <div class="price num"><?= money($it['sell_price']) ?></div>
        <div class="mb-1"><?= stock_note((int) $it['quantity'], (int) $it['reorder_threshold']) ?></div>
        <?php if (!is_admin()): ?>
        <form action="<?= e(url('cart.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="item_id" value="<?= (int) $it['item_id'] ?>">
          <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
          <div class="qty">
            <button type="button" data-step="-1" aria-label="Less">-</button>
            <input type="number" name="qty" value="1" min="1" max="<?= max(1, (int) $it['quantity']) ?>" aria-label="Quantity" <?= $out ? 'disabled' : '' ?>>
            <button type="button" data-step="1" aria-label="More">+</button>
          </div>
          <button class="btn btn-primary flex-grow-1" type="submit" <?= $out ? 'disabled' : '' ?>>Add to cart</button>
        </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
