<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in
requireLogin();

// Check if user has permission to view reports
if (!hasPermission('view_reports')) {
    $_SESSION['error'] = 'No tiene permiso para ver reportes.';
    header('Location: dashboard.php');
    exit;
}

// Initialize filters
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'state' => $_GET['state'] ?? '',
    'invoice' => $_GET['invoice'] ?? '',
    'beneficiary' => $_GET['beneficiary'] ?? ''
];

// Validate date filters
if (!empty($filters['date_from']) && !validateDate($filters['date_from'])) {
    $filters['date_from'] = '';
}
if (!empty($filters['date_to']) && !validateDate($filters['date_to'])) {
    $filters['date_to'] = '';
}

try {
    // Build query for cheques with invoice information
    $query = "
        SELECT 
            c.*,
            i.invoice_number,
            i.total_amount as invoice_total,
            i.remaining_balance as invoice_remaining,
            u.username as created_by_user
        FROM cheques c
        LEFT JOIN invoices i ON c.invoice_id = i.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE 1=1
    ";
    $params = [];

    // Add filters to query
    if (!empty($filters['date_from'])) {
        $query .= " AND c.due_date >= ?";
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $query .= " AND c.due_date <= ?";
        $params[] = $filters['date_to'];
    }
    if (!empty($filters['state'])) {
        $query .= " AND c.state = ?";
        $params[] = $filters['state'];
    }
    if (!empty($filters['invoice'])) {
        $query .= " AND i.invoice_number LIKE ?";
        $params[] = "%{$filters['invoice']}%";
    }
    if (!empty($filters['beneficiary'])) {
        $query .= " AND c.beneficiary LIKE ?";
        $params[] = "%{$filters['beneficiary']}%";
    }

    $query .= " ORDER BY c.due_date DESC";

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $cheques = $stmt->fetchAll();

    // Calculate totals
    $totals = [
        'total_amount' => 0,
        'deposited_amount' => 0,
        'pending_amount' => 0,
        'returned_amount' => 0,
        'cancelled_amount' => 0
    ];

    foreach ($cheques as $cheque) {
        $totals['total_amount'] += $cheque['amount'];
        switch ($cheque['state']) {
            case 'depositado':
                $totals['deposited_amount'] += $cheque['amount'];
                break;
            case 'creado':
            case 'modificado':
                $totals['pending_amount'] += $cheque['amount'];
                break;
            case 'devuelto':
                $totals['returned_amount'] += $cheque['amount'];
                break;
            case 'anulado':
                $totals['cancelled_amount'] += $cheque['amount'];
                break;
        }
    }

} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
    $_SESSION['error'] = 'Error al generar el reporte.';
    $cheques = [];
    $totals = [
        'total_amount' => 0,
        'deposited_amount' => 0,
        'pending_amount' => 0,
        'returned_amount' => 0,
        'cancelled_amount' => 0
    ];
}

// Include header
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Estado de Cuenta y Trazabilidad</h1>
        <button type="button" class="btn btn-primary" onclick="exportToExcel()">
            <i class="fas fa-file-excel me-2"></i>Exportar a Excel
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-2">
                    <div class="form-floating">
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        <label for="date_from">Fecha Desde</label>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-floating">
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        <label for="date_to">Fecha Hasta</label>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="state" name="state">
                            <option value="">Todos</option>
                            <option value="creado" <?php echo $filters['state'] === 'creado' ? 'selected' : ''; ?>>Creado</option>
                            <option value="depositado" <?php echo $filters['state'] === 'depositado' ? 'selected' : ''; ?>>Depositado</option>
                            <option value="devuelto" <?php echo $filters['state'] === 'devuelto' ? 'selected' : ''; ?>>Devuelto</option>
                            <option value="anulado" <?php echo $filters['state'] === 'anulado' ? 'selected' : ''; ?>>Anulado</option>
                            <option value="modificado" <?php echo $filters['state'] === 'modificado' ? 'selected' : ''; ?>>Modificado</option>
                        </select>
                        <label for="state">Estado</label>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="invoice" name="invoice" 
                               placeholder="N° Factura" value="<?php echo htmlspecialchars($filters['invoice']); ?>">
                        <label for="invoice">N° Factura</label>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="beneficiary" name="beneficiary" 
                               placeholder="Beneficiario" value="<?php echo htmlspecialchars($filters['beneficiary']); ?>">
                        <label for="beneficiary">Beneficiario</label>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Amount Card -->
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total en Cheques</h6>
                            <h3 class="mb-0">$<?php echo number_format($totals['total_amount'], 2); ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deposited Amount Card -->
        <div class="col-md-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Depositado</h6>
                            <h3 class="mb-0">$<?php echo number_format($totals['deposited_amount'], 2); ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Amount Card -->
        <div class="col-md-4">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Pendiente</h6>
                            <h3 class="mb-0">$<?php echo number_format($totals['pending_amount'], 2); ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Report Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="reportTable">
                <thead>
                    <tr>
                        <th>N° Cheque</th>
                        <th>Beneficiario</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Factura</th>
                        <th>Saldo Factura</th>
                        <th>Fecha Venc.</th>
                        <th>Creado Por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cheques)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">No se encontraron registros</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($cheques as $cheque): ?>
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
                            <td>
                                <?php if ($cheque['invoice_number']): ?>
                                    $<?php echo number_format($cheque['invoice_remaining'], 2); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($cheque['due_date'])); ?></td>
                            <td><?php echo htmlspecialchars($cheque['created_by_user']); ?></td>
                            <td>
                                <a href="view_cheque.php?id=<?php echo $cheque['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Ver Detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Get the table
    const table = document.getElementById('reportTable');
    
    // Convert table to CSV
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (const row of rows) {
        const cols = row.querySelectorAll('td, th');
        const tempRow = [];
        
        for (const col of cols) {
            // Get text content, removing any special characters
            let text = col.textContent.trim().replace(/"/g, '""');
            // Remove the "Ver Detalles" text from actions column
            if (text === 'Ver Detalles') continue;
            tempRow.push(`"${text}"`);
        }
        
        csv.push(tempRow.join(','));
    }
    
    // Create CSV content
    const csvContent = csv.join('\n');
    
    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'reporte_cheques.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'includes/footer.php'; ?>
