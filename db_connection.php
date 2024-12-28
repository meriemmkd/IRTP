<?php
$host = 'localhost';
$db_name = 'information_retreival';  // your database name
$username = 'root';  // your MySQL username
$password = '';  // your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
