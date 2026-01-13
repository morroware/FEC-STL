<?php
/**
 * FEC STL Vault - Logout
 */

require_once __DIR__ . '/includes/config.php';

// Destroy session
session_destroy();

// Redirect to home
header('Location: index.php');
exit;
