<?php
declare(strict_types=1);

require_once __DIR__ . '/APIs/database.php';
require_once __DIR__ . '/CONTROLLERs/PageController.php';
require_once __DIR__ . '/CONTROLLERs/ApiController.php';

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$trimmed = trim($uriPath, '/');

// API routing: support both /api/* (clean URLs) and ?route=api/* (fallback).
$route = $_GET['route'] ?? '';
if ($route === '' && str_starts_with($trimmed, 'api/')) {
    $route = $trimmed;
}

if (str_starts_with($route, 'api/')) {
    $api = new ApiController(new Database());
    $api->handle($route);
    exit;
}

// Page routing: clean route paths that mirror the React app.
if (!isset($_GET['page'])) {
    $segments = $trimmed === '' ? [] : explode('/', $trimmed);
    if (count($segments) === 0) {
        $_GET['page'] = 'home';
    } elseif ($segments[0] === 'admin') {
        $adminSection = $segments[1] ?? 'dashboard';
        $map = [
            'dashboard' => 'admin_dashboard',
            'catalog' => 'admin_catalog',
            'orders' => 'admin_orders',
            'reviews' => 'admin_reviews',
            'support' => 'admin_support',
            'activity' => 'admin_activity',
        ];
        $_GET['page'] = $map[$adminSection] ?? 'not_found';
    } elseif ($segments[0] === 'shop' && isset($segments[1]) && $segments[1] !== '') {
        $_GET['page'] = 'product';
        $_GET['slug'] = urldecode($segments[1]);
    } elseif ($segments[0] === 'payment' && ($segments[1] ?? '') === 'callback') {
        $_GET['page'] = 'checkout';
    } else {
        $_GET['page'] = $segments[0];
    }
}

$page = new PageController();
$page->render();
