<?php

function stock_context(mysqli $c, ?string $reason = null, ?int $ref = null): void
{
    $r = $reason === null ? 'NULL' : "'" . $c->real_escape_string($reason) . "'";
    $f = $ref === null ? 'NULL' : (string) (int) $ref;
    $c->query("SET @stock_reason = $r, @stock_ref = $f");
}

function place_order(mysqli $c, array $lines, array $o): int
{
    $lines = array_filter(array_map('intval', $lines), fn($q) => $q > 0);
    if (!$lines) {
        throw new RuntimeException('Add at least one item first.');
    }
    ksort($lines);

    $type = $o['order_type'] ?? 'storefront';
    $method = $o['payment_method'] ?? 'cash';
    $customerId = !empty($o['customer_id']) ? (int) $o['customer_id'] : null;
    $walkin = $type === 'walk_in';

    if ($method === 'lista' && !$customerId) {
        throw new RuntimeException('The suki list is only for registered customers.');
    }

    $c->begin_transaction();
    try {
        $total = 0.0;
        $priced = [];
        foreach ($lines as $itemId => $qty) {
            $it = db_row(
                $c,
                'SELECT i.item_id, i.description, i.sell_price, i.is_active, s.quantity
                 FROM item i JOIN stock s ON s.item_id = i.item_id
                 WHERE i.item_id = ? FOR UPDATE',
                [(int) $itemId]
            );
            if (!$it || !$it['is_active']) {
                throw new RuntimeException('An item in this order is no longer available.');
            }
            if ((int) $it['quantity'] < $qty) {
                throw new RuntimeException('Not enough stock for ' . $it['description'] . '. Only ' . max(0, (int) $it['quantity']) . ' left.');
            }
            $priced[] = ['item_id' => (int) $it['item_id'], 'qty' => $qty, 'price' => (float) $it['sell_price']];
            $total += (float) $it['sell_price'] * $qty;
        }
        $total = round($total, 2);

        $acct = null;
        if ($method === 'lista') {
            $acct = db_row($c, 'SELECT * FROM lista_account WHERE customer_id = ? FOR UPDATE', [$customerId]);
            if (!$acct) {
                throw new RuntimeException('This customer does not have a suki list yet.');
            }
            if ($acct['status'] !== 'active') {
                throw new RuntimeException('This suki list is suspended. Pay in cash instead.');
            }
            $available = (float) $acct['credit_limit'] - (float) $acct['balance'];
            if ($total > $available + 0.001) {
                throw new RuntimeException('This order is ' . money($total) . ' but only ' . money(max(0, $available)) . ' is left on the suki list.');
            }
        }

        $status = $walkin ? 'Completed' : 'Pending';
        $payStatus = $method === 'lista' ? 'on_lista' : ($walkin ? 'paid' : 'unpaid');
        db_run(
            $c,
            'INSERT INTO orderinfo (customer_id, walkin_name, order_type, payment_method, payment_status, status, total_amount, note, date_placed, date_completed, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ' . ($walkin ? 'NOW()' : 'NULL') . ', ?)',
            [$customerId, $o['walkin_name'] ?? null, $type, $method, $payStatus, $status, $total, $o['note'] ?? null, $o['created_by'] ?? null]
        );
        $orderId = (int) $c->insert_id;

        stock_context($c, 'sale', $orderId);
        foreach ($priced as $p) {
            db_run($c, 'INSERT INTO orderline (orderinfo_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)', [$orderId, $p['item_id'], $p['qty'], $p['price']]);
            $changed = db_run($c, 'UPDATE stock SET quantity = quantity - ? WHERE item_id = ? AND quantity >= ?', [$p['qty'], $p['item_id'], $p['qty']]);
            if ($changed !== 1) {
                throw new RuntimeException('Stock changed while saving the order. Please try again.');
            }
        }

        if ($acct) {
            $newBalance = round((float) $acct['balance'] + $total, 2);
            db_run($c, 'INSERT INTO lista_transaction (lista_id, orderinfo_id, type, amount, balance_after, note) VALUES (?, ?, ?, ?, ?, ?)',
                [(int) $acct['lista_id'], $orderId, 'utang', $total, $newBalance, 'Order ' . order_label($orderId)]);
            db_run($c, 'UPDATE lista_account SET balance = ? WHERE lista_id = ?', [$newBalance, (int) $acct['lista_id']]);
        }

        if ($customerId) {
            $points = intdiv((int) floor($total), LOYALTY_PESOS_PER_POINT);
            if ($points > 0) {
                db_run($c, 'INSERT INTO loyalty_transaction (customer_id, orderinfo_id, points, note) VALUES (?, ?, ?, ?)', [$customerId, $orderId, $points, 'Earned from order ' . order_label($orderId)]);
                db_run($c, 'UPDATE customer SET loyalty_points = loyalty_points + ? WHERE customer_id = ?', [$points, $customerId]);
            }
        }

        $c->commit();
        stock_context($c);
        return $orderId;
    } catch (Throwable $e) {
        $c->rollback();
        stock_context($c);
        throw $e;
    }
}

function cancel_order(mysqli $c, int $orderId): void
{
    $c->begin_transaction();
    try {
        $o = db_row($c, 'SELECT * FROM orderinfo WHERE orderinfo_id = ? FOR UPDATE', [$orderId]);
        if (!$o) {
            throw new RuntimeException('Order not found.');
        }
        if ($o['status'] === 'Canceled') {
            throw new RuntimeException('This order is already canceled.');
        }

        stock_context($c, 'cancel', $orderId);
        foreach (db_rows($c, 'SELECT item_id, quantity FROM orderline WHERE orderinfo_id = ? ORDER BY item_id', [$orderId]) as $l) {
            db_run($c, 'UPDATE stock SET quantity = quantity + ? WHERE item_id = ?', [(int) $l['quantity'], (int) $l['item_id']]);
        }

        if ($o['payment_method'] === 'lista' && $o['customer_id']) {
            $acct = db_row($c, 'SELECT * FROM lista_account WHERE customer_id = ? FOR UPDATE', [(int) $o['customer_id']]);
            if ($acct) {
                $charged = (float) db_val($c, "SELECT COALESCE(SUM(amount), 0) FROM lista_transaction WHERE orderinfo_id = ? AND type = 'utang'", [$orderId]);
                $reversed = (float) db_val($c, "SELECT COALESCE(SUM(amount), 0) FROM lista_transaction WHERE orderinfo_id = ? AND type = 'reversal'", [$orderId]);
                $amount = round($charged - $reversed, 2);
                if ($amount > 0) {
                    $newBalance = round((float) $acct['balance'] - $amount, 2);
                    db_run($c, 'INSERT INTO lista_transaction (lista_id, orderinfo_id, type, amount, balance_after, note) VALUES (?, ?, ?, ?, ?, ?)',
                        [(int) $acct['lista_id'], $orderId, 'reversal', $amount, $newBalance, 'Canceled order ' . order_label($orderId)]);
                    db_run($c, 'UPDATE lista_account SET balance = ? WHERE lista_id = ?', [$newBalance, (int) $acct['lista_id']]);
                }
            }
        }

        if ($o['customer_id']) {
            $pts = (int) db_val($c, 'SELECT COALESCE(SUM(points), 0) FROM loyalty_transaction WHERE orderinfo_id = ?', [$orderId]);
            if ($pts !== 0) {
                db_run($c, 'INSERT INTO loyalty_transaction (customer_id, orderinfo_id, points, note) VALUES (?, ?, ?, ?)', [(int) $o['customer_id'], $orderId, -$pts, 'Order ' . order_label($orderId) . ' canceled']);
                db_run($c, 'UPDATE customer SET loyalty_points = loyalty_points - ? WHERE customer_id = ?', [$pts, (int) $o['customer_id']]);
            }
        }

        db_run($c, "UPDATE orderinfo SET status = 'Canceled', payment_status = 'void', date_completed = NULL WHERE orderinfo_id = ?", [$orderId]);
        $c->commit();
        stock_context($c);
    } catch (Throwable $e) {
        $c->rollback();
        stock_context($c);
        throw $e;
    }
}

function set_order_status(mysqli $c, int $orderId, string $new): void
{
    if (!in_array($new, ['Pending', 'Processing', 'Completed', 'Canceled'], true)) {
        throw new RuntimeException('Unknown status.');
    }
    $current = db_val($c, 'SELECT status FROM orderinfo WHERE orderinfo_id = ?', [$orderId]);
    if ($current === null) {
        throw new RuntimeException('Order not found.');
    }
    if ($current === 'Canceled') {
        throw new RuntimeException('A canceled order cannot be changed. Record a new sale instead.');
    }
    if ($new === 'Canceled') {
        cancel_order($c, $orderId);
        return;
    }
    db_run($c, 'UPDATE orderinfo SET status = ?, date_completed = ' . ($new === 'Completed' ? 'NOW()' : 'NULL') . ' WHERE orderinfo_id = ?', [$new, $orderId]);
}

function mark_order_paid(mysqli $c, int $orderId): void
{
    $n = db_run($c, "UPDATE orderinfo SET payment_status = 'paid' WHERE orderinfo_id = ? AND payment_method = 'cash' AND payment_status = 'unpaid' AND status <> 'Canceled'", [$orderId]);
    if ($n !== 1) {
        throw new RuntimeException('Only unpaid cash orders can be marked as paid.');
    }
}

function record_lista_payment(mysqli $c, int $customerId, float $amount, string $note = ''): float
{
    if ($amount <= 0) {
        throw new RuntimeException('Enter a payment amount greater than zero.');
    }
    $c->begin_transaction();
    try {
        $acct = db_row($c, 'SELECT * FROM lista_account WHERE customer_id = ? FOR UPDATE', [$customerId]);
        if (!$acct) {
            throw new RuntimeException('This customer does not have a suki list.');
        }
        if ($amount > (float) $acct['balance'] + 0.001) {
            throw new RuntimeException('The payment is more than the balance of ' . money($acct['balance']) . '.');
        }
        $newBalance = round((float) $acct['balance'] - $amount, 2);
        db_run($c, 'INSERT INTO lista_transaction (lista_id, orderinfo_id, type, amount, balance_after, note) VALUES (?, NULL, ?, ?, ?, ?)',
            [(int) $acct['lista_id'], 'payment', $amount, $newBalance, $note !== '' ? $note : 'Payment at the counter']);
        db_run($c, 'UPDATE lista_account SET balance = ? WHERE lista_id = ?', [$newBalance, (int) $acct['lista_id']]);
        $c->commit();
        return $newBalance;
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}

function restock_item(mysqli $c, int $itemId, int $qty): void
{
    if ($qty <= 0) {
        throw new RuntimeException('Enter how many pieces arrived.');
    }
    stock_context($c, 'restock');
    try {
        $n = db_run($c, 'UPDATE stock SET quantity = quantity + ? WHERE item_id = ?', [$qty, $itemId]);
    } finally {
        stock_context($c);
    }
    if ($n !== 1) {
        throw new RuntimeException('Item not found.');
    }
}

function register_customer(mysqli $c, array $d, string $role = 'user'): int
{
    $email = strtolower(trim($d['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }
    if (strlen($d['password'] ?? '') < 6) {
        throw new RuntimeException('The password needs at least 6 characters.');
    }
    if (trim($d['fname'] ?? '') === '' || trim($d['lname'] ?? '') === '') {
        throw new RuntimeException('Enter the first and last name.');
    }
    if (db_val($c, 'SELECT user_id FROM users WHERE email = ?', [$email])) {
        throw new RuntimeException('That email is already registered. Try logging in.');
    }
    $c->begin_transaction();
    try {
        db_run($c, "INSERT INTO users (email, password, status, role) VALUES (?, ?, 'active', ?)", [$email, password_hash($d['password'], PASSWORD_BCRYPT), $role]);
        $userId = (int) $c->insert_id;
        db_run(
            $c,
            'INSERT INTO customer (fname, lname, addressline, town, zipcode, phone, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [trim($d['fname']), trim($d['lname']), trim($d['addressline'] ?? ''), trim($d['town'] ?? ''), trim($d['zipcode'] ?? ''), trim($d['phone'] ?? ''), $userId]
        );
        $customerId = (int) $c->insert_id;
        $c->commit();
        return $userId;
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}
