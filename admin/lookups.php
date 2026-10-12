<?php
include("../includes/config.php");
require_admin();

$types = [
    'category' => ['label' => 'Categories', 'one' => 'category', 'table' => 'category', 'key' => 'category_id',
                   'fields' => ['name' => 'Name']],
    'brand' => ['label' => 'Brands', 'one' => 'brand', 'table' => 'brand', 'key' => 'brand_id',
                'fields' => ['name' => 'Name']],
    'supplier' => ['label' => 'Suppliers', 'one' => 'supplier', 'table' => 'supplier', 'key' => 'supplier_id',
                   'fields' => ['name' => 'Name', 'contact_name' => 'Contact person', 'phone' => 'Phone', 'address' => 'Address']],
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
        // Read each field from the form (empty becomes NULL)
        $cols = array_keys($cfg['fields']);
        $vals = [];
        foreach ($cols as $col) {
            $text = post_str($col, 150);
            $vals[] = ($text !== '') ? $text : null;
        }

        if (($action === 'add' || $action === 'save') && $vals[0] === null) {
            throw new RuntimeException('Enter a name.');
        }

        if ($action === 'add') {
            $marks = implode(', ', array_fill(0, count($cols), '?'));
            db_run($conn, 'INSERT INTO ' . $cfg['table'] . ' (' . implode(', ', $cols) . ') VALUES (' . $marks . ')', $vals);
            log_activity($conn, $cfg['one'] . ' added', (string) $vals[0]);
            flash('success', ucfirst($cfg['one']) . ' "' . $vals[0] . '" added.');

        } elseif ($action === 'save') {
            $sets = [];
            foreach ($cols as $col) {
                $sets[] = $col . ' = ?';
            }
            $vals[] = (int) $_POST['id'];
            db_run($conn, 'UPDATE ' . $cfg['table'] . ' SET ' . implode(', ', $sets) . ' WHERE ' . $cfg['key'] . ' = ?', $vals);
            flash('success', 'Saved.');

        } elseif ($action === 'delete') {
            db_run($conn, 'DELETE FROM ' . $cfg['table'] . ' WHERE ' . $cfg['key'] . ' = ?', [(int) $_POST['id']]);
            log_activity($conn, $cfg['one'] . ' deleted', 'id ' . (int) $_POST['id']);
            flash('success', 'Deleted. Items that used it now show no ' . $cfg['one'] . '.');
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            flash('danger', 'That name already exists.');
        } else {
            flash('danger', 'Could not save. Please try again.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('admin/lookups.php?t=' . $t);
}

$key = $cfg['key'];
$rows = db_rows($conn, 'SELECT t.*, (SELECT COUNT(*) FROM item i WHERE i.' . $key . ' = t.' . $key . ') AS used
                        FROM ' . $cfg['table'] . ' t ORDER BY t.name');

$pageTitle = $cfg['label'];
$adminActive = 'lookups';
include("../includes/admin_header.php");
?>
<h1>Categories and brands</h1>

<p>
    <?php foreach ($types as $k => $c) { ?>
        <?php if ($k === $t) { ?>
            <strong><?= e($c['label']) ?></strong>
        <?php } else { ?>
            <a href="<?= e(url('admin/lookups.php?t=' . $k)) ?>"><?= e($c['label']) ?></a>
        <?php } ?>
        |
    <?php } ?>
</p>

<h2>Add <?= e($cfg['one']) ?></h2>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($t) ?>">
    <input type="hidden" name="action" value="add">

    <?php foreach ($cfg['fields'] as $col => $label) { ?>
        <p>
            <label for="new_<?= $col ?>"><?= e($label) ?></label><br>
            <input id="new_<?= $col ?>" name="<?= $col ?>" <?= $col === 'name' ? 'required' : '' ?>>
        </p>
    <?php } ?>

    <p><button type="submit">Add <?= e($cfg['one']) ?></button></p>
</form>

<h2><?= e($cfg['label']) ?></h2>
<table border="1">
    <tr>
        <?php foreach ($cfg['fields'] as $label) { ?>
            <th><?= e($label) ?></th>
        <?php } ?>
        <th>Items</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($rows as $r) { ?>
        <?php $formId = 'f' . $r[$key]; ?>
        <tr>
            <?php foreach ($cfg['fields'] as $col => $label) { ?>
                <td><input form="<?= $formId ?>" name="<?= $col ?>" value="<?= e($r[$col]) ?>" aria-label="<?= e($label) ?>"></td>
            <?php } ?>
            <td><?= (int) $r['used'] ?></td>
            <td>
                <form id="<?= $formId ?>" method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="t" value="<?= e($t) ?>">
                    <input type="hidden" name="id" value="<?= (int) $r[$key] ?>">
                    <button type="submit" name="action" value="save">Save</button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this <?= e($cfg['one']) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="t" value="<?= e($t) ?>">
                    <input type="hidden" name="id" value="<?= (int) $r[$key] ?>">
                    <button type="submit" name="action" value="delete">Delete</button>
                </form>
            </td>
        </tr>
    <?php } ?>
    <?php if (!$rows) { ?>
        <tr>
            <td colspan="<?= count($cfg['fields']) + 2 ?>">Nothing here yet. Add the first <?= e($cfg['one']) ?> above.</td>
        </tr>
    <?php } ?>
</table>

<?php

include("../includes/admin_footer.php"); ?>