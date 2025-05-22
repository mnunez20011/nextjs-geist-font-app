<?php
require_once 'config.php';
require_once 'functions.php';

$error_code = $_SERVER['REDIRECT_STATUS'] ?? 404;
$error_messages = [
    403 => 'Acceso Denegado',
    404 => 'Página No Encontrada',
    500 => 'Error Interno del Servidor',
    'default' => 'Error Desconocido'
];

$error_descriptions = [
    403 => 'No tiene permiso para acceder a este recurso.',
    404 => 'La página que está buscando no existe o ha sido movida.',
    500 => 'Ha ocurrido un error interno del servidor. Por favor intente más tarde.',
    'default' => 'Ha ocurrido un error inesperado.'
];

$error_title = $error_messages[$error_code] ?? $error_messages['default'];
$error_description = $error_descriptions[$error_code] ?? $error_descriptions['default'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error_title; ?> - <?php echo APP_NAME; ?></title>
    
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
        .error-container {
            max-width: 500px;
            width: 90%;
            text-align: center;
            padding: 2rem;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        .error-description {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .error-icon {
            font-size: 4rem;
            color: #3498db;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <?php if ($error_code == 403): ?>
                <i class="fas fa-lock"></i>
            <?php elseif ($error_code == 404): ?>
                <i class="fas fa-map-signs"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php endif; ?>
        </div>
        
        <div class="error-code"><?php echo $error_code; ?></div>
        <h1 class="error-title"><?php echo $error_title; ?></h1>
        <p class="error-description"><?php echo $error_description; ?></p>
        
        <div class="d-grid gap-3">
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Ir al Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                </a>
            <?php endif; ?>
            
            <button onclick="history.back()" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver Atrás
            </button>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
