<?php

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    if (preg_match('#^https?://#', $path) || str_starts_with($path, '/')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

function format_dt(?string $dt, string $fmt = 'M j, Y g:i A'): string
{
    if (!$dt) {
        return '';
    }
    return date($fmt, strtotime($dt));
}

function order_label(int $id): string
{
    return '#' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function customer_name(array $row): string
{
    $name = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return $row['walkin_name'] ?: 'Walk-in customer';
}


function db_bind(mysqli_stmt $st, array $params): void
{
    if (!$params) {
        return;
    }
    $types = '';
    foreach ($params as $p) {
        if (is_int($p)) {
            $types .= 'i';
        } elseif (is_float($p)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    $st->bind_param($types, ...$params);
}

function db_rows(mysqli $c, string $sql, array $params = []): array
{
    $st = $c->prepare($sql);
    db_bind($st, $params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function db_row(mysqli $c, string $sql, array $params = []): ?array
{
    $rows = db_rows($c, $sql, $params);
    return $rows[0] ?? null;
}

function db_val(mysqli $c, string $sql, array $params = [])
{
    $row = db_row($c, $sql, $params);
    if (!$row) {
        return null;
    }
    return array_values($row)[0];
}

function db_run(mysqli $c, string $sql, array $params = []): int
{
    $st = $c->prepare($sql);
    db_bind($st, $params);
    $st->execute();
    $n = $st->affected_rows;
    $st->close();
    return $n;
}


function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flashes(): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<p>' . e($f['message']) . '</p>';
    }
    unset($_SESSION['flash']);
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page, and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post_str(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}

function post_money(string $key): float
{
    $v = str_replace(',', '', trim((string) ($_POST[$key] ?? '0')));
    if (!is_numeric($v) || (float) $v < 0) {
        throw new RuntimeException('Enter a valid amount for ' . str_replace('_', ' ', $key) . '.');
    }
    return round((float) $v, 2);
}

function post_int(string $key, int $min = 0): int
{
    $v = trim((string) ($_POST[$key] ?? '0'));
    if (!preg_match('/^-?\d+$/', $v) || (int) $v < $min) {
        throw new RuntimeException('Enter a whole number of at least ' . $min . ' for ' . str_replace('_', ' ', $key) . '.');
    }
    return (int) $v;
}


function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function safe_next(?string $next): string
{
    $next = (string) $next;
    if ($next === '' || $next[0] !== '/') {
        return '';
    }
    if (str_starts_with($next, '//') || str_contains($next, "\\")) {
        return '';
    }
    if (!str_starts_with($next, BASE_URL . '/')) {
        return '';
    }
    return $next;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('info', 'Log in to continue.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

function require_admin(): void
{
    if (!is_logged_in()) {
        flash('info', 'Log in as an admin to continue.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'That page is for the shop admin only.');
        redirect('index.php');
    }
}

function current_customer(mysqli $c): ?array
{
    static $cache = false;
    if ($cache === false) {
        if (is_logged_in()) {
            $cache = db_row($c, 'SELECT * FROM customer WHERE user_id = ?', [(int) $_SESSION['user_id']]);
        } else {
            $cache = null;
        }
    }
    return $cache;
}

function log_activity(mysqli $c, string $action, string $details = ''): void
{
    db_run($c, 'INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)',
        [$_SESSION['user_id'] ?? null, $action, mb_substr($details, 0, 255)]);
}

function cart_get(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(cart_get());
}

function cart_set(int $itemId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$itemId]);
    } else {
        $_SESSION['cart'][$itemId] = $qty;
    }
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

function status_badge(string $status): string
{
    return e($status);
}

function payment_badge(string $method, string $status): string
{
    $labels = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'on_lista' => 'On lista', 'void' => 'Void'];
    $how = $method === 'lista' ? 'Lista' : 'Cash';
    return e($how . ': ' . ($labels[$status] ?? $status));
}

function stock_note(int $qty, int $threshold): string
{
    if ($qty <= 0) {
        return 'Out of stock';
    }
    if ($qty <= $threshold) {
        return 'Only ' . $qty . ' left';
    }
    return $qty . ' in stock';
}

function upload_image(array $file, ?string $oldPath = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $oldPath;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image could not be uploaded. Try a smaller file.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('The image is too big. Use one under 3 MB.');
    }

    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    $dir = APP_ROOT . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'item_' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('The image could not be saved. Check that the uploads folder exists.');
    }

    if ($oldPath) {
        delete_image($oldPath);
    }
    return 'uploads/' . $name;
}

function delete_image(?string $path): void
{
    if ($path && str_starts_with($path, 'uploads/') && is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}