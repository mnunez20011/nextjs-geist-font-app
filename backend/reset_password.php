<?php
require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;
$username = '';

// Verify token
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, username 
            FROM users 
            WHERE reset_token = ? 
            AND reset_token_expiry > NOW() 
            AND reset_token IS NOT NULL
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $validToken = true;
            $username = $user['username'];
        } else {
            $error = 'El enlace de recuperación no es válido o ha expirado.';
        }
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
        $error = 'Error al verificar el enlace de recuperación.';
    }
} else {
    $error = 'Token no proporcionado.';
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Por favor complete todos los campos.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            // Update password and clear reset token
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, 
                    reset_token = NULL, 
                    reset_token_expiry = NULL 
                WHERE reset_token = ?
            ");
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$hashed_password, $token]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Su contraseña ha sido actualizada exitosamente.';
                // Log the activity
                logActivity('password_reset_success', 'Contraseña restablecida para el usuario: ' . $username);
            } else {
                $error = 'Error al actualizar la contraseña.';
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Error al procesar el cambio de contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-password-container {
            max-width: 400px;
            width: 90%;
            padding: 2rem;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #2c3e50;
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-bottom: none;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52,152,219,.25);
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            padding: 0.75rem;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .system-name {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .form-floating > label {
            color: #6c757d;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <div class="card">
            <div class="card-header">
                <h1 class="system-name"><?php echo APP_NAME; ?></h1>
                <p class="mb-0">Restablecer Contraseña</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success; ?>
                        <br><br>
                        <a href="login.php" class="alert-link">Ir a iniciar sesión</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($validToken && !$success): ?>
                    <p class="text-muted mb-4">
                        Hola <?php echo htmlspecialchars($username); ?>, por favor ingrese su nueva contraseña.
                    </p>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Nueva Contraseña" required minlength="6">
                            <label for="password">Nueva Contraseña</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" placeholder="Confirmar Contraseña" required>
                            <label for="confirm_password">Confirmar Contraseña</label>
                        </div>
                        
                        <div class="password-requirements mb-4">
                            <p class="mb-1"><i class="fas fa-info-circle"></i> La contraseña debe:</p>
                            <ul class="mb-0">
                                <li>Tener al menos 6 caracteres</li>
                                <li>Coincidir en ambos campos</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Cambiar Contraseña
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver al Login
                            </a>
                        </div>
                    </form>
                <?php elseif (!$success): ?>
                    <div class="d-grid">
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-3 text-muted">
            <small>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.</small>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    // Check if passwords match
                    const password = form.querySelector('#password');
                    const confirmPassword = form.querySelector('#confirm_password');
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
