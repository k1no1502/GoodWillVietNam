<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Log activity before logout
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear remember me token
if (isset($_COOKIE['remember_token'])) {
    try {
        $sql = "UPDATE users SET remember_token = NULL WHERE remember_token = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_COOKIE['remember_token']]);
    } catch (Exception $e) {
        error_log("Error clearing remember token: " . $e->getMessage());
    }
    
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit();
?>
