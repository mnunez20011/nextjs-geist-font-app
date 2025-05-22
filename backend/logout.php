<?php
require_once 'config.php';
require_once 'functions.php';

// Log the logout activity if user was logged in
if (isLoggedIn()) {
    logActivity('logout', 'Usuario cerró sesión');
}

// Destroy the session
session_start();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
