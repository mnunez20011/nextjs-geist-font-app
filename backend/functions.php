<?php
/**
 * Utility Functions File
 * PHP Version 7.4
 */

session_start();

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize($data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login to access page
 * @return void
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Por favor inicie sesión para acceder.";
        header("Location: login.php");
        exit;
    }
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Generate CSRF token
 * @return string
 */
function generateToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyToken(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Display flash messages
 * @param string $type
 * @return string|null
 */
function flashMessage(string $type): ?string {
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return null;
}

/**
 * Validate date format
 * @param string $date
 * @return bool
 */
function validateDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency(float $amount): string {
    return number_format($amount, 2, '.', ',');
}

/**
 * Log system activity
 * @param string $action
 * @param string $details
 * @return void
 */
function logActivity(string $action, string $details): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'] ?? 0, $action, $details]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Validate cheque state transition
 * @param string $currentState
 * @param string $newState
 * @return bool
 */
function isValidStateTransition(string $currentState, string $newState): bool {
    $validTransitions = [
        'creado' => ['devuelto', 'depositado', 'anulado', 'modificado'],
        'devuelto' => ['depositado', 'anulado'],
        'depositado' => ['anulado'],
        'anulado' => [],
        'modificado' => ['devuelto', 'depositado', 'anulado']
    ];
    
    return isset($validTransitions[$currentState]) && 
           in_array($newState, $validTransitions[$currentState]);
}

/**
 * Check if user has permission for action
 * @param string $action
 * @return bool
 */
function hasPermission(string $action): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    $adminOnlyActions = ['delete_cheque', 'manage_users', 'view_reports'];
    
    if (in_array($action, $adminOnlyActions)) {
        return isAdmin();
    }
    
    return true;
}

/**
 * Clean output for JSON responses
 * @param mixed $data
 * @return string
 */
function jsonResponse($data): string {
    header('Content-Type: application/json');
    return json_encode($data);
}
