<?php
session_start();
require_once 'conexion/bd.php';

// Redirección si ya está logueado
if (isset($_SESSION['usuario_id'])) {
    if (isset($_GET['redirect']) && $_GET['redirect'] === 'registro_negocio') {
        header("Location: profesional/mi_negocio.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$mensaje_error = '';

// Si viene con intención de registrar negocio sin iniciar sesión
if (isset($_GET['redirect']) && $_GET['redirect'] === 'registro_negocio') {
    $mensaje_error = "Para registrar tu negocio, primero debes iniciar sesión o crear una cuenta de Profesional.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];

    if (empty($correo) || empty($contrasena)) {
        $mensaje_error = "Ingresa tu correo y contraseña.";
    } else {
        $stmt = $conexion->prepare("SELECT id, nombre, correo, contrasena, tipo_usuario, foto_perfil FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($contrasena, $usuario['contrasena'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
                $_SESSION['usuario_foto'] = $usuario['foto_perfil'];

                // Si venía de intentar registrar un negocio, llevarlo directamente al formulario del negocio
                if (isset($_GET['redirect']) && $_GET['redirect'] === 'registro_negocio') {
                    header("Location: profesional/mi_negocio.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $mensaje_error = "Contraseña incorrecta.";
            }
        } else {
            $mensaje_error = "No existe una cuenta registrada con este correo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Servicios Bienestar y Belleza</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="auth-body">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Iniciar Sesión</h2>
                <p>Bienestar & Belleza - Xalapa</p>
            </div>

            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
            <?php endif; ?>

            <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="correo">Correo Electrónico</label>
                    <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>

            <div class="auth-footer">
                <p>¿No tienes una cuenta? <a href="registro.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">Regístrate aquí</a></p>
            </div>
        </div>
    </div>

</body>
</html>