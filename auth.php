<?php
require_once __DIR__ . '/config.php';

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    // Keep last_seen fresh
    try {
        $pdo = getDB();
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")
            ->execute([$_SESSION['user_id']]);
    } catch (Exception $e) { /* ignore */ }
}

function requireAdmin(): void {
    requireLogin();
    if (empty($_SESSION['is_admin'])) {
        header('Location: dashboard.php');
        exit;
    }
}

function apiAuth(): array {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return [
        'id'       => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'is_admin' => (bool)$_SESSION['is_admin'],
    ];
}

function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
