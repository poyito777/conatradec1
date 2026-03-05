<?php
require __DIR__ . '/../app/config/db.php';

try {
    $stmt = $pdo->query("SELECT NOW() as fecha");
    $row = $stmt->fetch();
    echo "Conexión exitosa ✅<br>";
    echo "Fecha del servidor: " . $row['fecha'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}  