<?php
session_start();

function isLogged(): bool {
  return isset($_SESSION['user']);
}

function requireLogin(): void {
  if (!isLogged()) {
    header("Location: /docentes/public/index.php");
    exit;
  }
}

function requireRole(string $role): void {
  requireLogin();
  if (($_SESSION['user']['role'] ?? '') !== $role) {
    http_response_code(403);
    echo "403 - Acceso denegado";
    exit;
  }
}

function requirePasswordChangeIfNeeded(): void {
  if (isLogged() && (int)($_SESSION['user']['must_change_password'] ?? 0) === 1) {
    $path = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($path, 'change_password.php') === false && strpos($path, 'logout.php') === false) {
      header("Location: /docentes/public/change_password.php");
      exit;
    }
  }
}