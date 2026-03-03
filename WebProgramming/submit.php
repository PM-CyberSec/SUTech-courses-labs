<?php
require 'connect.php';

$name=trim($_POST['username'] ?? '');
$email=trim($_POST['email'] ?? '');
$message=trim($_POST['message'] ?? '');

try {
    $stmt = $pdo->prepare(
    "INSERT INTO users2 (name, email, message)
    VALUES (:name, :email, :message)"
    );
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':message' => $message,
    ]);
    
    echo "<h2>thanks , $name - your message has been saved </h2>";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}

