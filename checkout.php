<?php
require_once __DIR__ . '/includes/config.php';

if (!is_post()) {
    redirect('cart.php');
}
csrf_check();
require_login();

if (is_admin()) {
    flash('info', 'Admins record sales on the Walk-in sale page.');
    redirect('admin/pos.php');
}

$customer = current_customer($conn);
if (!$customer) {
    flash('danger', 'This account has no customer profile yet.');
    redirect('cart.php');
}

$method = ($_POST['payment_method'] ?? 'cash') === 'lista' ? 'lista' : 'cash';

try {
    $orderId = place_order($conn, cart_get(), [
        'customer_id' => (int) $customer['customer_id'],
        'order_type' => 'storefront',
        'payment_method' => $method,
        'created_by' => (int) $_SESSION['user_id'],
    ]);
    cart_clear();
    flash('success', 'Order ' . order_label($orderId) . ' is in. We will have it ready for you at the counter.');
    redirect('order.php?id=' . $orderId);
} catch (RuntimeException $e) {
    flash('danger', $e->getMessage());
    redirect('cart.php');
} catch (mysqli_sql_exception $e) {
    flash('danger', 'Something went wrong while saving your order, and nothing was charged. Please try again.');
    redirect('cart.php');
}
