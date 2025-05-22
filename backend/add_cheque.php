<?php
require_once 'config.php';
require_once 'functions.php';

// Require user to be logged in
requireLogin();

$error = '';
$success = '';

// Get list of invoices for dropdown
try {
    $stmt = $pdo->prepare("
        SELECT id, invoice_number, remaining_balance 
        FROM invoices 
        WHERE remaining_balance > 0 
        ORDER BY invoice_number
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching invoices: " . $e->getMessage());
    $invoices = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyToken($_POST['token'] ?? '')) {
    $beneficiary = trim($_POST['beneficiary'] ?? '');
    $cheque_number = trim($_POST['cheque_number'] ?? '');
    $detail = trim($_POST['detail'] ?? '');
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $due_date = $_POST['due_date'] ?? '';
    $bank = trim($_POST['bank'] ?? '');
    $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;

    // Validate input
    if (empty($beneficiary) || empty($cheque_number) || empty($bank)) {
        $error = 'Por favor complete todos los campos requeridos.';
    } elseif ($amount <= 0) {
        $error = 'El monto debe ser mayor a cero.';
    } elseif (!validateDate($due_date)) {
        $error = 'La fecha de vencimiento no es válida.';
    } else {
        try {
            // Check if cheque number already exists
            $stmt = $pdo->prepare("SELECT id FROM cheques WHERE cheque_number = ?");
            $stmt->execute([$cheque_number]);
            if ($stmt->fetch()) {
                $error = 'El número de cheque ya existe.';
            } else {
                // If invoice_id is provided, verify remaining balance
                if ($invoice_id) {
                    $stmt = $pdo->prepare("SELECT remaining_balance FROM invoices WHERE id = ?");
                    $stmt->execute([$invoice_id]);
                    $invoice = $stmt->fetch();
                    
                    if (!$invoice) {
                        $error = 'La factura seleccionada no existe.';
                    } elseif ($amount > $invoice['remaining_balance']) {
                        $error = 'El monto del cheque excede el saldo pendiente de la factura.';
                    }
                }

                if (empty($error)) {
                    // Insert new cheque
                    $stmt = $pdo->prepare("
                        INSERT INTO cheques (
                            beneficiary, cheque_number, detail, amount, 
                            issue_date, due_date, bank, state, 
                            invoice_id, created_by
                        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'creado', ?, ?)
                    ");
                    $stmt->execute([
                        $beneficiary,
                        $cheque_number,
                        $detail,
                        $amount,
                        $due_date,
                        $bank,
                        $invoice_id,
                        $_SESSION['user_id']
                    ]);

                    logActivity(
                        'create_cheque',
                        "Cheque creado: {$cheque_number} - Beneficiario: {$beneficiary} - Monto: $" . number_format($amount, 2)
                    );

                    $success = 'Cheque registrado exitosamente.';
                    
                    // Redirect to the cheque list after success
                    $_SESSION['success'] = $success;
                    header('Location: cheques.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error creating cheque: " . $e->getMessage());
            $error = 'Error al registrar el cheque.';
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
                    <h4 class="card-title mb-0">Registrar Nuevo Cheque</h4>
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
                            <!-- Beneficiary -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="beneficiary" name="beneficiary"
                                           placeholder="Beneficiario" required
                                           value="<?php echo htmlspecialchars($_POST['beneficiary'] ?? ''); ?>">
                                    <label for="beneficiary">Beneficiario *</label>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el beneficiario.
                                    </div>
                                </div>
                            </div>

                            <!-- Cheque Number -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="cheque_number" name="cheque_number"
                                           placeholder="Número de Cheque" required
                                           value="<?php echo htmlspecialchars($_POST['cheque_number'] ?? ''); ?>">
                                    <label for="cheque_number">Número de Cheque *</label>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el número de cheque.
                                    </div>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="amount" name="amount"
                                           placeholder="Monto" required step="0.01" min="0.01"
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
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
                                           value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
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
                                           value="<?php echo htmlspecialchars($_POST['bank'] ?? ''); ?>">
                                    <label for="bank">Banco *</label>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el banco.
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="invoice_id" name="invoice_id">
                                        <option value="">Seleccione una factura (opcional)</option>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <option value="<?php echo $invoice['id']; ?>"
                                                    data-balance="<?php echo $invoice['remaining_balance']; ?>"
                                                    <?php echo (isset($_POST['invoice_id']) && $_POST['invoice_id'] == $invoice['id']) ? 'selected' : ''; ?>>
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
                                              placeholder="Detalle" style="height: 100px"><?php echo htmlspecialchars($_POST['detail'] ?? ''); ?></textarea>
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
                                        <i class="fas fa-save me-2"></i>Guardar Cheque
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
    // Set minimum date to today for due date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('due_date').min = today;

    // Validate amount against invoice remaining balance
    const amountInput = document.getElementById('amount');
    const invoiceSelect = document.getElementById('invoice_id');

    function validateAmount() {
        const selectedOption = invoiceSelect.options[invoiceSelect.selectedIndex];
        if (selectedOption.value && selectedOption.dataset.balance) {
            const remainingBalance = parseFloat(selectedOption.dataset.balance);
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
