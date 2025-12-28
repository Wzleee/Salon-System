<?php
$host = 'localhost';
$user = 'root';
$pass = ''; 
$dbname = 'salonsystem';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

define('SECRET_KEY', 'MySuperSecretKey_RandomString12345!@#');
?>