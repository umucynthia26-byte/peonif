<?php
/** Paystack redirects here after payment; we verify server-side (the source of truth). */
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$pdo = db();

$paymentRef = $_GET['reference'] ?? $_GET['trxref'] ?? '';
$st = $pdo->prepare('SELECT * FROM orders WHERE payment_ref = ?');
$st->execute([$paymentRef]);
$order = $st->fetch();

if (!$order) {
    flash('error', "We couldn't find that payment. If you were charged, contact support with your reference.");
    header('Location: account.php'); exit;
}
if ($order['status'] !== 'pending_payment') {
    header('Location: account.php'); exit; // already settled
}
if (!verify_payment($paymentRef)) {
    flash('error', 'Payment not completed yet — you can retry from your cart, or contact support if you were charged.');
    header('Location: account.php'); exit;
}

$pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
$pdo->prepare("INSERT INTO order_events (order_id,status,note) VALUES (?,?,?)")
    ->execute([$order['id'], 'paid', 'Payment received — the order is with our atelier.']);
notify_user((int)$order['user_id'], 'Order ' . $order['reference'] . ' paid',
    "Payment received. We'll prepare your flowers and deliver them in your chosen window.");
notify_admins('New paid order ' . $order['reference'],
    $order['customer_name'] . ' — ' . money((int)$order['total_cents']) . ', delivery ' . $order['delivery_date'] . ' ' . $order['delivery_window'] . '.');
flash('ok', 'Payment confirmed — order ' . $order['reference'] . ' is on its way!');
header('Location: account.php');
