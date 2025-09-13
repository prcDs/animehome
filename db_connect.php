<?php
require __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Проверка
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

echo "✅ Подключение успешно!";
?>