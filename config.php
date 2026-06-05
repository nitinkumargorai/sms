<?php
/**
 * StudyBuddyHub - Centralized Database Configuration
 * All pages should include this file instead of repeating credentials.
 */

$host = "localhost";
$user = "root";
$password = "";
$db = "users";

$data = mysqli_connect($host, $user, $password, $db);

if (!$data) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset for proper encoding
mysqli_set_charset($data, "utf8mb4");
?>
