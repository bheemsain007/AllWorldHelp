<?php
// auth/logout.php
// Updated in T7: uses SessionManager::destroy()

require_once "../includes/SessionManager.php";

SessionManager::start();
SessionManager::destroy();

// Redirect to homepage (or login page)
header("Location: ../index.php");
exit;
?>
