<?php
require_once __DIR__ . '/../includes/config.php';
require_admin();

$types = [
    'category' => ['label' => 'Categories', 'one' => 'category', 'table' => 'category', 'key' => 'category_id', 'fields' => ['name' => 'Name']],
    'brand' => ['label' => 'Brands', 'one' => 'brand', 'table' => 'brand', 'key' => 'brand_id', 'fields' => ['name' => 'Name']],
    'supplier' => ['label' => 'Suppliers', 'one' => 'supplier', 'table' => 'supplier', 'key' => 'supplier_id', 'fields' => ['name' => 'Name', 'contact_name' => 'Contact person', 'phone' => 'Phone', 'address' => 'Address']],
];
$t = $_GET['t'] ?? $_POST['t'] ?? 'category';
if (!isset($types[$t])) {
    $t = 'category';
}
$cfg = $types[$t];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        $cols = array_keys($cfg['fields']);
        $vals = array_map(fn($c) => post_str($c, 150) !== '' ? post_str($c, 150) : null, $cols);
        if ($action === 'add' || $action === 'save') {
            if ($vals[0] === null) {
                throw new RuntimeException('Enter a name.');
            }
        }
        if ($action === 'add') {
            db_run($conn, "INSERT INTO {$cfg['table']} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $vals);
            log_activity($conn, $cfg['one'] . ' added', (string) $vals[0]);
            flash('success', ucfirst($cfg['one']) . ' "' . $vals[0] . '" added.');
        } elseif ($action === 'save') {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            db_run($conn, "UPDATE {$cfg['table']} SET $set WHERE {$cfg['key']} = ?", array_merge($vals, [(int) $_POST['id']]));
            flash('success', 'Saved.');
        } elseif ($action === 'delete') {
            db_run($conn, "DELETE FROM {$cfg['table']} WHERE {$cfg['key']} = ?", [(int) $_POST['id']]);
            log_activity($conn, $cfg['one'] . ' deleted', 'id ' . (int) $_POST['id']);
            flash('success', 'Deleted. Items that used it now show no ' . $cfg['one'] . '.');
        }
    } catch (mysqli_sql_exception $e) {
        flash('danger', $e->getCode() === 1062 ? 'That name already exists.' : 'Could not save. Please try again.');
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/lookups.php?t=' . $t);
}

$rows = db_rows($conn, "SELECT t.*, (SELECT COUNT(*) FROM item i WHERE i.{$cfg['key']} = t.{$cfg['key']}) AS used FROM {$cfg['table']} t ORDER BY t.name");

$pageTitle = $cfg['label'];
$adminActive = 'lookups';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-bar"><h1 class="page-title">Categories and brands</h1></div>

<ul class="nav nav-pills mb-3">
  <?php foreach ($types as $k => $c): ?>
    <li class="nav-item"><a class="nav-link <?= $k === $t ? 'active' : '' ?>" href="<?= e(url('admin/lookups.php?t=' . $k)) ?>"><?= e($c['label']) ?></a></li>
  <?php endforeach; ?>
</ul>

<form method="post" class="panel panel-body mb-3">
  <?= csrf_field() ?>
  <input type="hidden" name="t" value="<?= e($t) ?>">
  <input type="hidden" name="action" value="add">
  <div class="row g-2 align-items-end">
    <?php foreach ($cfg['fields'] as $col => $label): ?>
      <div class="col-md"><label class="form-label" for="new_<?= $col ?>"><?= e($label) ?></label><input class="form-control" id="new_<?= $col ?>" name="<?= $col ?>" <?= $col === 'name' ? 'required' : '' ?>></div>
    <?php endforeach; ?>
    <div class="col-md-auto"><button class="btn btn-primary" type="submit">Add <?= e($cfg['one']) ?></button></div>
  </div>
</form>

<div class="panel table-wrap">
  <table class="table mb-0">
    <thead><tr><?php foreach ($cfg['fields'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th class="text-end">Items</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'f' . $r[$cfg['key']]; ?>
      <tr>
        <?php foreach ($cfg['fields'] as $col => $label): ?>
          <td><input class="form-control form-control-sm" form="<?= $fid ?>" name="<?= $col ?>" value="<?= e($r[$col]) ?>" aria-label="<?= e($label) ?>"></td>
        <?php endforeach; ?>
        <td class="text-end num"><?= (int) $r['used'] ?></td>
        <td class="text-end text-nowrap">
          <form id="<?= $fid ?>" method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= e($t) ?>">
            <input type="hidden" name="id" value="<?= (int) $r[$cfg['key']] ?>">
            <button class="btn btn-sm btn-outline-primary" name="action" value="save" type="submit">Save</button>
          </form>
          <form method="post" class="d-inline" data-confirm="Delete <?= e($r['name']) ?>?">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= e($t) ?>">
            <input type="hidden" name="id" value="<?= (int) $r[$cfg['key']] ?>">
            <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit" aria-label="Delete <?= e($r['name']) ?>"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= count($cfg['fields']) + 2 ?>" class="text-center muted py-4">Nothing here yet. Add the first <?= e($cfg['one']) ?> above.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
