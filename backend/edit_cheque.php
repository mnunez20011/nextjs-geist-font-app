<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in
requireLogin();

$error = '';
$success = '';
$cheque = null;
$cheque_id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$cheque_id) {
    $_SESSION['error'] = 'ID de cheque no válido.';
    header('Location: cheques.php');
    exit;
}

// Get cheque data
try {
    $stmt = $pdo->prepare("
        SELECT c.*, i.invoice_number, i.remaining_balance 
        FROM cheques c 
        LEFT JOIN invoices i ON c.invoice_id = i.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$cheque_id]);
    $cheque = $stmt->fetch();

    if (!$cheque) {
        $_SESSION['error'] = 'Cheque no encontrado.';
        header('Location: cheques.php');
        exit;
    }

    // Check if user has permission to edit
    if (!hasPermission('edit_cheque')) {
        $_SESSION['error'] = 'No tiene permiso para editar cheques.';
        header('Location: cheques.php');
        exit;
    }

    // Get list of invoices for dropdown
    $stmt = $pdo->prepare("
        SELECT id, invoice_number, remaining_balance 
        FROM invoices 
        WHERE remaining_balance > 0 OR id = ?
        ORDER BY invoice_number
    ");
    $stmt->execute([$cheque['invoice_id']]);
    $invoices = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching cheque data: " . $e->getMessage());
    $_SESSION['error'] = 'Error al cargar los datos del cheque.';
    header('Location: cheques.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyToken($_POST['token'] ?? '')) {
    $new_state = $_POST['state'] ?? $cheque['state'];
    $beneficiary = trim($_POST['beneficiary'] ?? '');
    $detail = trim($_POST['detail'] ?? '');
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $due_date = $_POST['due_date'] ?? '';
    $bank = trim($_POST['bank'] ?? '');
    $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;

    // Validate input
    if (empty($beneficiary) || empty($bank)) {
        $error = 'Por favor complete todos los campos requeridos.';
    } elseif ($amount <= 0) {
        $error = 'El monto debe ser mayor a cero.';
    } elseif (!validateDate($due_date)) {
        $error = 'La fecha de vencimiento no es válida.';
    } elseif (!isValidStateTransition($cheque['state'], $new_state)) {
        $error = 'Transición de estado no válida.';
    } else {
        try {
            $pdo->beginTransaction();

            // If changing to 'depositado', verify invoice balance
            if ($new_state === 'depositado' && $invoice_id) {
                $stmt = $pdo->prepare("
                    SELECT remaining_balance 
                    FROM invoices 
                    WHERE id = ? FOR UPDATE
                ");
                $stmt->execute([$invoice_id]);
                $invoice = $stmt->fetch();

                if (!$invoice) {
                    throw new Exception('La factura seleccionada no existe.');
                }

                if ($amount > $invoice['remaining_balance']) {
                    throw new Exception('El monto del cheque excede el saldo pendiente de la factura.');
                }

                // Update invoice balance
                $stmt = $pdo->prepare("
                    UPDATE invoices 
                    SET remaining_balance = remaining_balance - ? 
                    WHERE id = ?
                ");
                $stmt->execute([$amount, $invoice_id]);
            }

            // Update cheque
            $stmt = $pdo->prepare("
                UPDATE cheques 
                SET beneficiary = ?,
                    detail = ?,
                    amount = ?,
                    due_date = ?,
                    bank = ?,
                    state = ?,
                    invoice_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $beneficiary,
                $detail,
                $amount,
                $due_date,
                $bank,
                $new_state,
                $invoice_id,
                $cheque_id
            ]);

            // Log the activity
            logActivity(
                'edit_cheque',
                "Cheque actualizado: {$cheque['cheque_number']} - Nuevo estado: {$new_state}"
            );

            $pdo->commit();
            
            $success = 'Cheque actualizado exitosamente.';
            
            // Redirect to the cheque list after success
            $_SESSION['success'] = $success;
            header('Location: cheques.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error updating cheque: " . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        Editar Cheque #<?php echo htmlspecialchars($cheque['cheque_number']); ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="token" value="<?php echo generateToken(); ?>">

                        <div class="row g-3">
                            <!-- Cheque Number (Read-only) -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($cheque['cheque_number']); ?>" readonly>
                                    <label>Número de Cheque</label>
                                </div>
                            </div>

                            <!-- State -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="state" name="state" required>
                                        <option value="creado" <?php echo $cheque['state'] === 'creado' ? 'selected' : ''; ?>>Creado</option>
                                        <option value="depositado" <?php echo $cheque['state'] === 'depositado' ? 'selected' : ''; ?>>Depositado</option>
                                        <option value="devuelto" <?php echo $cheque['state'] === 'devuelto' ? 'selected' : ''; ?>>Devuelto</option>
                                        <option value="anulado" <?php echo $cheque['state'] === 'anulado' ? 'selected' : ''; ?>>Anulado</option>
                                        <option value="modificado" <?php echo $cheque['state'] === 'modificado' ? 'selected' : ''; ?>>Modificado</option>
                                    </select>
                                    <label for="state">Estado *</label>
                                </div>
                            </div>

                            <!-- Beneficiary -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="beneficiary" name="beneficiary"
                                           placeholder="Beneficiario" required
                                           value="<?php echo htmlspecialchars($cheque['beneficiary']); ?>">
                                    <label for="beneficiary">Beneficiario *</label>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el beneficiario.
                                    </div>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="amount" name="amount"
                                           placeholder="Monto" required step="0.01" min="0.01"
                                           value="<?php echo htmlspecialchars($cheque['amount']); ?>">
                                    <label for="amount">Monto *</label>
                                    <div class="invalid-feedback">
                                        Por favor ingrese un monto válido.
                                    </div>
                                </div>
                            </div>

                            <!-- Due Date -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="due_date" name="due_date"
                                           placeholder="Fecha de Vencimiento" required
                                           value="<?php echo htmlspecialchars($cheque['due_date']); ?>">
                                    <label for="due_date">Fecha de Vencimiento *</label>
                                    <div class="invalid-feedback">
                                        Por favor seleccione la fecha de vencimiento.
                                    </div>
                                </div>
                            </div>

                            <!-- Bank -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="bank" name="bank"
                                           placeholder="Banco" required
                                           value="<?php echo htmlspecialchars($cheque['bank']); ?>">
                                    <label for="bank">Banco *</label>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el banco.
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice -->
                            <div class="col-md-12">
                                <div class="form-floating">
                                    <select class="form-select" id="invoice_id" name="invoice_id">
                                        <option value="">Seleccione una factura (opcional)</option>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <option value="<?php echo $invoice['id']; ?>"
                                                    data-balance="<?php echo $invoice['remaining_balance']; ?>"
                                                    <?php echo $cheque['invoice_id'] == $invoice['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($invoice['invoice_number']); ?> 
                                                (Saldo: $<?php echo number_format($invoice['remaining_balance'], 2); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="invoice_id">Factura Asociada</label>
                                </div>
                            </div>

                            <!-- Detail -->
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="detail" name="detail" 
                                              placeholder="Detalle" style="height: 100px"><?php echo htmlspecialchars($cheque['detail']); ?></textarea>
                                    <label for="detail">Detalle (opcional)</label>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="col-12">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="cheques.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.getElementById('state');
    const currentState = '<?php echo $cheque['state']; ?>';
    
    // Define valid state transitions
    const validTransitions = {
        'creado': ['creado', 'devuelto', 'depositado', 'anulado', 'modificado'],
        'devuelto': ['devuelto', 'depositado', 'anulado'],
        'depositado': ['depositado', 'anulado'],
        'anulado': ['anulado'],
        'modificado': ['modificado', 'devuelto', 'depositado', 'anulado']
    };

    // Filter available states based on current state
    Array.from(stateSelect.options).forEach(option => {
        if (!validTransitions[currentState].includes(option.value)) {
            option.disabled = true;
        }
    });

    // Validate amount against invoice remaining balance
    const amountInput = document.getElementById('amount');
    const invoiceSelect = document.getElementById('invoice_id');
    const originalAmount = <?php echo $cheque['amount']; ?>;

    function validateAmount() {
        const selectedOption = invoiceSelect.options[invoiceSelect.selectedIndex];
        if (selectedOption.value && selectedOption.dataset.balance) {
            let remainingBalance = parseFloat(selectedOption.dataset.balance);
            
            // If it's the same invoice as before, add the original amount to the available balance
            if (selectedOption.value == '<?php echo $cheque['invoice_id']; ?>') {
                remainingBalance += originalAmount;
            }
            
            const amount = parseFloat(amountInput.value);
            
            if (amount > remainingBalance) {
                amountInput.setCustomValidity(`El monto no puede exceder el saldo pendiente de la factura ($${remainingBalance.toFixed(2)})`);
            } else {
                amountInput.setCustomValidity('');
            }
        } else {
            amountInput.setCustomValidity('');
        }
    }

    amountInput.addEventListener('input', validateAmount);
    invoiceSelect.addEventListener('change', validateAmount);
});
</script>

<?php require_once 'includes/footer.php'; ?>
