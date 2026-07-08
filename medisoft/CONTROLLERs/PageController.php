<?php
declare(strict_types=1);

require_once __DIR__ . '/../APIs/database.php';
require_once __DIR__ . '/../MODELs/UserModel.php';

class PageController
{
    public function render(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $page = $_GET['page'] ?? 'home';
        $user = $this->currentUser();
        $isLoggedIn = $user !== null;
        $isAdmin = $isLoggedIn && (($user['role'] ?? '') === 'admin');

        if (in_array($page, ['account', 'checkout'], true) && !$isLoggedIn) {
            header('Location: /login');
            exit;
        }
        if (($page === 'admin' || str_starts_with($page, 'admin_')) && !$isAdmin) {
            header('Location: ' . ($isLoggedIn ? '/account' : '/login'));
            exit;
        }
        if (in_array($page, ['login', 'signup'], true) && $isLoggedIn) {
            header('Location: ' . ($isAdmin ? '/admin/dashboard' : '/account'));
            exit;
        }

        $allowed = [
            'home', 'shop', 'product', 'builder', 'cart', 'checkout',
            'account', 'login', 'signup', 'admin', 'about', 'contact',
            'admin_dashboard', 'admin_catalog', 'admin_orders', 'admin_reviews', 'admin_support', 'admin_activity',
            'terms', 'privacy', 'not_found'
        ];
        if (!in_array($page, $allowed, true)) {
            $page = 'not_found';
            http_response_code(404);
        }
        $pageFile = __DIR__ . '/../VIEWs/pages/' . $page . '.php';
        if (!file_exists($pageFile)) {
            $pageFile = __DIR__ . '/../VIEWs/pages/not_found.php';
            $page = 'not_found';
            http_response_code(404);
        }
        $viewer = $user;
        include __DIR__ . '/../VIEWs/layout.php';
    }

    private function currentUser(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        try {
            $db = new Database();
            $users = new UserModel($db);
            return $users->find($userId);
        } catch (Throwable) {
            return null;
        }
    }
}
