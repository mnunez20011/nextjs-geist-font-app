<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in
requireLogin();

// Get user's cheques count
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN state = 'creado' THEN 1 ELSE 0 END) as created,
            SUM(CASE WHEN state = 'depositado' THEN 1 ELSE 0 END) as deposited,
            SUM(CASE WHEN state = 'devuelto' THEN 1 ELSE 0 END) as returned,
            SUM(CASE WHEN state = 'anulado' THEN 1 ELSE 0 END) as cancelled
        FROM cheques 
        WHERE created_by = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $chequeStats = $stmt->fetch();

    // Get total amount of cheques
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as total_amount,
            SUM(CASE WHEN state = 'depositado' THEN amount ELSE 0 END) as deposited_amount
        FROM cheques 
        WHERE created_by = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $amountStats = $stmt->fetch();

    // Get recent cheques
    $stmt = $pdo->prepare("
        SELECT c.*, i.invoice_number 
        FROM cheques c 
        LEFT JOIN invoices i ON c.invoice_id = i.id 
        WHERE c.created_by = ? 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentCheques = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $_SESSION['error'] = "Error al cargar los datos del dashboard.";
}

// Include header
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Dashboard</h1>
        <a href="add_cheque.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nuevo Cheque
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Cheques -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-money-check fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-title mb-1">Total Cheques</h6>
                            <h2 class="mb-0"><?php echo number_format($chequeStats['total'] ?? 0); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Amount -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-dollar-sign fa-2x text-success"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-title mb-1">Monto Total</h6>
                            <h2 class="mb-0">$<?php echo number_format($amountStats['total_amount'] ?? 0, 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deposited Amount -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="fas fa-check-circle fa-2x text-info"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-title mb-1">Monto Depositado</h6>
                            <h2 class="mb-0">$<?php echo number_format($amountStats['deposited_amount'] ?? 0, 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Amount -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-title mb-1">Monto Pendiente</h6>
                            <h2 class="mb-0">$<?php echo number_format(($amountStats['total_amount'] ?? 0) - ($amountStats['deposited_amount'] ?? 0), 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row g-4 mb-4">
        <!-- Created Cheques -->
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-file-alt fa-2x text-primary"></i>
                    </div>
                    <h5 class="card-title">Creados</h5>
                    <p class="card-text display-6"><?php echo number_format($chequeStats['created'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Deposited Cheques -->
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <h5 class="card-title">Depositados</h5>
                    <p class="card-text display-6"><?php echo number_format($chequeStats['deposited'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Returned Cheques -->
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-undo fa-2x text-warning"></i>
                    </div>
                    <h5 class="card-title">Devueltos</h5>
                    <p class="card-text display-6"><?php echo number_format($chequeStats['returned'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Cancelled Cheques -->
        <div class="col-6 col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-ban fa-2x text-danger"></i>
                    </div>
                    <h5 class="card-title">Anulados</h5>
                    <p class="card-text display-6"><?php echo number_format($chequeStats['cancelled'] ?? 0); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Cheques -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Cheques Recientes</h5>
            <a href="cheques.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>N° Cheque</th>
                        <th>Beneficiario</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Factura</th>
                        <th>Fecha Venc.</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentCheques)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No hay cheques registrados</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentCheques as $cheque): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cheque['cheque_number']); ?></td>
                            <td><?php echo htmlspecialchars($cheque['beneficiary']); ?></td>
                            <td>$<?php echo number_format($cheque['amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    echo match($cheque['state']) {
                                        'creado' => 'primary',
                                        'depositado' => 'success',
                                        'devuelto' => 'warning',
                                        'anulado' => 'danger',
                                        'modificado' => 'info',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($cheque['state']); ?>
                                </span>
                            </td>
                            <td><?php echo $cheque['invoice_number'] ? htmlspecialchars($cheque['invoice_number']) : 'N/A'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($cheque['due_date'])); ?></td>
                            <td>
                                <a href="view_cheque.php?id=<?php echo $cheque['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Ver Detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('edit_cheque') && $cheque['state'] !== 'anulado'): ?>
                                <a href="edit_cheque.php?id=<?php echo $cheque['id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary" 
                                   title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
