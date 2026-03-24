<?php
require_once __DIR__ . '/db.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin('/login.php');
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: /portal/dashboard.php');
        exit;
    }
}

function requireAdminOrEmployee(): void {
    requireLogin('/login.php');
    if (!in_array($_SESSION['user_role'], ['admin', 'employee'])) {
        header('Location: /portal/dashboard.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
    ];
}

function login(string $email, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, email, password, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || !password_verify($password, $user['password'])) {
        return false;
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    session_regenerate_id(true);
    return true;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function getClientForUser(int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM clients WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}
