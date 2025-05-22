<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in
requireLogin();

// Initialize filters
$filters = [
    'state' => $_GET['state'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Validate date filters
if (!empty($filters['date_from']) && !validateDate($filters['date_from'])) {
    $filters['date_from'] = '';
}
if (!empty($filters['date_to']) && !validateDate($filters['date_to'])) {
    $filters['date_to'] = '';
}

// Build query
$query = "
    SELECT c.*, i.invoice_number 
    FROM cheques c 
    LEFT JOIN invoices i ON c.invoice_id = i.id 
    WHERE 1=1
";
$params = [];

// Add filters to query
if (!empty($filters['state'])) {
    $query .= " AND c.state = ?";
    $params[] = $filters['state'];
}

if (!empty($filters['search'])) {
    $query .= " AND (
        c.beneficiary LIKE ? OR 
        c.cheque_number LIKE ? OR 
        c.detail LIKE ? OR 
        i.invoice_number LIKE ?
    )";
    $searchTerm = "%{$filters['search']}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filters['date_from'])) {
    $query .= " AND c.due_date >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $query .= " AND c.due_date <= ?";
    $params[] = $filters['date_to'];
}

// Add sorting
$validSortColumns = ['cheque_number', 'beneficiary', 'amount', 'due_date', 'state', 'created_at'];
$validOrders = ['ASC', 'DESC'];

$sortColumn = in_array($filters['sort'], $validSortColumns) ? $filters['sort'] : 'created_at';
$sortOrder = in_array(strtoupper($filters['order']), $validOrders) ? strtoupper($filters['order']) : 'DESC';

$query .= " ORDER BY c.$sortColumn $sortOrder";

try {
    // Get total count for pagination
    $countQuery = str_replace("SELECT c.*, i.invoice_number", "SELECT COUNT(*) as total", $query);
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];

    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 10;
    $totalPages = ceil($totalRecords / $perPage);
    $offset = ($page - 1) * $perPage;

    // Get paginated results
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $cheques = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Cheques list error: " . $e->getMessage());
    $_SESSION['error'] = "Error al cargar la lista de cheques.";
    $cheques = [];
    $totalPages = 0;
    $page = 1;
}

// Include header
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Gestión de Cheques</h1>
        <a href="add_cheque.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nuevo Cheque
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Buscar..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <label for="search">Buscar por beneficiario, N° cheque o detalle</label>
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
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="cheques.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Cheques Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['sort' => 'cheque_number', 'order' => $sortColumn === 'cheque_number' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                N° Cheque
                                <?php if ($sortColumn === 'cheque_number'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['sort' => 'beneficiary', 'order' => $sortColumn === 'beneficiary' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>"
                               class="text-decoration-none text-dark">
                                Beneficiario
                                <?php if ($sortColumn === 'beneficiary'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['sort' => 'amount', 'order' => $sortColumn === 'amount' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>"
                               class="text-decoration-none text-dark">
                                Monto
                                <?php if ($sortColumn === 'amount'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['sort' => 'state', 'order' => $sortColumn === 'state' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>"
                               class="text-decoration-none text-dark">
                                Estado
                                <?php if ($sortColumn === 'state'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Factura</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['sort' => 'due_date', 'order' => $sortColumn === 'due_date' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>"
                               class="text-decoration-none text-dark">
                                Fecha Venc.
                                <?php if ($sortColumn === 'due_date'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cheques)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No se encontraron cheques</td>
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
                            <td><?php echo date('d/m/Y', strtotime($cheque['due_date'])); ?></td>
                            <td>
                                <div class="btn-group" role="group">
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
                                    <?php if (isAdmin() && $cheque['state'] !== 'anulado'): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="Anular"
                                            onclick="confirmCancel(<?php echo $cheque['id']; ?>)">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmCancel(chequeId) {
    if (confirm('¿Está seguro de que desea anular este cheque? Esta acción no se puede deshacer.')) {
        window.location.href = `cancel_cheque.php?id=${chequeId}&token=<?php echo generateToken(); ?>`;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
