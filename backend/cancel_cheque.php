<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in and admin
requireLogin();

if (!isAdmin()) {
    $_SESSION['error'] = 'No tiene permiso para anular cheques.';
    header('Location: cheques.php');
    exit;
}

$cheque_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$token = $_GET['token'] ?? '';

if (!$cheque_id || !verifyToken($token)) {
    $_SESSION['error'] = 'Solicitud no válida.';
    header('Location: cheques.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Get cheque details
    $stmt = $pdo->prepare("
        SELECT c.*, i.remaining_balance, i.id as invoice_id 
        FROM cheques c 
        LEFT JOIN invoices i ON c.invoice_id = i.id 
        WHERE c.id = ? 
        FOR UPDATE
    ");
    $stmt->execute([$cheque_id]);
    $cheque = $stmt->fetch();

    if (!$cheque) {
        throw new Exception('Cheque no encontrado.');
    }

    if ($cheque['state'] === 'anulado') {
        throw new Exception('El cheque ya está anulado.');
    }

    // If cheque was deposited and linked to an invoice, restore invoice balance
    if ($cheque['state'] === 'depositado' && $cheque['invoice_id']) {
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET remaining_balance = remaining_balance + ? 
            WHERE id = ?
        ");
        $stmt->execute([$cheque['amount'], $cheque['invoice_id']]);
    }

    // Update cheque state to cancelled
    $stmt = $pdo->prepare("
        UPDATE cheques 
        SET state = 'anulado' 
        WHERE id = ?
    ");
    $stmt->execute([$cheque_id]);

    // Log the activity
    logActivity(
        'cancel_cheque',
        "Cheque anulado: {$cheque['cheque_number']} - Monto: $" . number_format($cheque['amount'], 2)
    );

    $pdo->commit();
    
    $_SESSION['success'] = 'Cheque anulado exitosamente.';

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error cancelling cheque: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to cheques list
header('Location: cheques.php');
exit;
