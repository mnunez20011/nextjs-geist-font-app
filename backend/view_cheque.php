<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in
requireLogin();

$cheque_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$cheque_id) {
    $_SESSION['error'] = 'ID de cheque no válido.';
    header('Location: cheques.php');
    exit;
}

try {
    // Get cheque details with invoice information
    $stmt = $pdo->prepare("
        SELECT c.*, 
               i.invoice_number,
               i.total_amount as invoice_total,
               i.remaining_balance as invoice_remaining,
               u.username as created_by_user
        FROM cheques c 
        LEFT JOIN invoices i ON c.invoice_id = i.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cheque_id]);
    $cheque = $stmt->fetch();

    if (!$cheque) {
        $_SESSION['error'] = 'Cheque no encontrado.';
        header('Location: cheques.php');
        exit;
    }

    // Get state history from activity log
    $stmt = $pdo->prepare("
        SELECT al.*, u.username
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.details LIKE ?
        ORDER BY al.created_at DESC
    ");
    $stmt->execute(["%Cheque {$cheque['cheque_number']}%"]);
    $history = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching cheque details: " . $e->getMessage());
    $_SESSION['error'] = 'Error al cargar los detalles del cheque.';
    header('Location: cheques.php');
    exit;
}

// Include header
require_once 'includes/header.php';

// Helper function to get badge class based on state
function getStateBadgeClass($state) {
    return match($state) {
        'creado' => 'primary',
        'depositado' => 'success',
        'devuelto' => 'warning',
        'anulado' => 'danger',
        'modificado' => 'info',
        default => 'secondary'
    };
}
?>

<div class="container py-4">
    <div class="row">
        <!-- Cheque Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Detalles del Cheque</h4>
                    <div class="btn-group">
                        <a href="cheques.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <?php if (hasPermission('edit_cheque') && $cheque['state'] !== 'anulado'): ?>
                        <a href="edit_cheque.php?id=<?php echo $cheque_id; ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Número de Cheque</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($cheque['cheque_number']); ?></p>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Estado</h6>
                            <span class="badge bg-<?php echo getStateBadgeClass($cheque['state']); ?>">
                                <?php echo ucfirst($cheque['state']); ?>
                            </span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Beneficiario</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($cheque['beneficiary']); ?></p>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Monto</h6>
                            <p class="mb-0">$<?php echo number_format($cheque['amount'], 2); ?></p>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Banco</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($cheque['bank']); ?></p>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Fecha de Vencimiento</h6>
                            <p class="mb-0"><?php echo date('d/m/Y', strtotime($cheque['due_date'])); ?></p>
                        </div>
                        <?php if ($cheque['invoice_number']): ?>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Factura Asociada</h6>
                            <p class="mb-0">
                                <?php echo htmlspecialchars($cheque['invoice_number']); ?>
                                <br>
                                <small class="text-muted">
                                    Total: $<?php echo number_format($cheque['invoice_total'], 2); ?>
                                    <br>
                                    Saldo Pendiente: $<?php echo number_format($cheque['invoice_remaining'], 2); ?>
                                </small>
                            </p>
                        </div>
                        <?php endif; ?>
                        <div class="col-sm-6 mb-3">
                            <h6 class="text-muted">Creado Por</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($cheque['created_by_user']); ?></p>
                        </div>
                        <?php if ($cheque['detail']): ?>
                        <div class="col-12">
                            <h6 class="text-muted">Detalle</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($cheque['detail'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Timeline of Changes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Historial de Cambios</h5>
                </div>
                <div class="card-body p-0">
                    <div class="timeline p-4">
                        <?php foreach ($history as $index => $entry): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">
                                    <?php echo htmlspecialchars($entry['action']); ?>
                                </h6>
                                <p class="timeline-text">
                                    <?php echo htmlspecialchars($entry['details']); ?>
                                    <br>
                                    <small class="text-muted">
                                        Por: <?php echo htmlspecialchars($entry['username']); ?>
                                        - <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions and Info -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <?php if ($cheque['state'] !== 'anulado'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Acciones Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (hasPermission('edit_cheque')): ?>
                            <?php if ($cheque['state'] === 'creado'): ?>
                            <button type="button" class="btn btn-success" 
                                    onclick="confirmStateChange('depositado')">
                                <i class="fas fa-check-circle"></i> Marcar como Depositado
                            </button>
                            <button type="button" class="btn btn-warning" 
                                    onclick="confirmStateChange('devuelto')">
                                <i class="fas fa-undo"></i> Marcar como Devuelto
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($cheque['state'] === 'devuelto'): ?>
                            <button type="button" class="btn btn-success" 
                                    onclick="confirmStateChange('depositado')">
                                <i class="fas fa-check-circle"></i> Marcar como Depositado
                            </button>
                            <?php endif; ?>
                            
                            <?php if (isAdmin()): ?>
                            <button type="button" class="btn btn-danger" 
                                    onclick="confirmStateChange('anulado')">
                                <i class="fas fa-ban"></i> Anular Cheque
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información Adicional</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                            Creado: <?php echo date('d/m/Y H:i', strtotime($cheque['created_at'])); ?>
                        </li>
                        <?php if ($cheque['created_at'] != $cheque['updated_at']): ?>
                        <li class="mb-2">
                            <i class="fas fa-edit text-muted me-2"></i>
                            Última modificación: <?php echo date('d/m/Y H:i', strtotime($cheque['updated_at'])); ?>
                        </li>
                        <?php endif; ?>
                        <li>
                            <i class="fas fa-clock text-muted me-2"></i>
                            <?php
                            $due_date = new DateTime($cheque['due_date']);
                            $now = new DateTime();
                            $diff = $now->diff($due_date);
                            
                            if ($due_date < $now) {
                                echo "Vencido hace {$diff->days} días";
                            } else {
                                echo "Vence en {$diff->days} días";
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline-item {
    position: relative;
    padding-left: 40px;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background-color: #3498db;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px rgba(52,152,219,.2);
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 7px;
    top: 15px;
    height: 100%;
    width: 1px;
    background-color: #e9ecef;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-title {
    margin-bottom: 5px;
    color: #2c3e50;
}

.timeline-text {
    margin-bottom: 0;
    color: #6c757d;
}
</style>

<script>
function confirmStateChange(newState) {
    const messages = {
        'depositado': '¿Está seguro de marcar este cheque como depositado?',
        'devuelto': '¿Está seguro de marcar este cheque como devuelto?',
        'anulado': '¿Está seguro de anular este cheque? Esta acción no se puede deshacer.'
    };

    if (confirm(messages[newState])) {
        window.location.href = `edit_cheque.php?id=<?php echo $cheque_id; ?>&state=${newState}&token=<?php echo generateToken(); ?>`;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
