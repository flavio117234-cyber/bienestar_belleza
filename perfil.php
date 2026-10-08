<?php
session_start();
require_once 'conexion/bd.php';

// Verificar inicio de sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje_error = '';
$mensaje_exito = '';
$mensaje_info = '';

// Obtener datos actuales del usuario
$stmt = $conexion->prepare("SELECT nombre, correo, tipo_usuario, foto_perfil FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$usuario = $res->fetch_assoc();

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_nombre = trim($_POST['nombre']);
    $password_actual = trim($_POST['password_actual']);
    $password_nueva = trim($_POST['password_nueva']);
    $foto_nombre = $usuario['foto_perfil'];

    $hubo_cambios = false;

    // 1. Validar nombre no vacío
    if (empty($nuevo_nombre)) {
        $mensaje_error = "El nombre no puede estar vacío.";
    } else {

        // Detectar cambio de nombre
        if ($nuevo_nombre !== $usuario['nombre']) {
            $hubo_cambios = true;
        }

        // 2. Procesar cambio de contraseña si se interactuó con los campos
        $cambiar_pass = false;
        $hash_nueva_pass = '';

        if (!empty($password_actual) || !empty($password_nueva)) {
            if (empty($password_actual)) {
                $mensaje_error = "Debes ingresar tu contraseña actual para autorizar el cambio.";
            } elseif (empty($password_nueva)) {
                $mensaje_error = "Debes ingresar la nueva contraseña que deseas establecer.";
            } else {
                // Verificar contraseña actual contra la base de datos
                $stmt_pass = $conexion->prepare("SELECT contrasena FROM usuarios WHERE id = ?");
                $stmt_pass->bind_param("i", $usuario_id);
                $stmt_pass->execute();
                $pass_db = $stmt_pass->get_result()->fetch_assoc()['contrasena'];

                if (password_verify($password_actual, $pass_db)) {
                    $cambiar_pass = true;
                    $hubo_cambios = true;
                    $hash_nueva_pass = password_hash($password_nueva, PASSWORD_BCRYPT);
                } else {
                    $mensaje_error = "La contraseña actual que ingresaste es incorrecta.";
                }
            }
        }

        // 3. Procesar foto de perfil solo si no hay errores previos
        if (empty($mensaje_error) && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['foto_perfil']['tmp_name'];
            $nombre_original = $_FILES['foto_perfil']['name'];
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            $ext_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($extension, $ext_permitidas)) {
                $nueva_foto = uniqid('user_') . '.' . $extension;
                $ruta_destino = 'uploads/perfiles/' . $nueva_foto;

                if (move_uploaded_file($foto_tmp, $ruta_destino)) {
                    // Eliminar foto anterior si no era la genérica
                    if ($foto_nombre !== 'default_avatar.png' && file_exists('uploads/perfiles/' . $foto_nombre)) {
                        unlink('uploads/perfiles/' . $foto_nombre);
                    }
                    $foto_nombre = $nueva_foto;
                    $hubo_cambios = true;
                }
            } else {
                $mensaje_error = "Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.";
            }
        }

        // 4. Guardar cambios si todo es válido
        if (empty($mensaje_error)) {
            if ($hubo_cambios) {
                if ($cambiar_pass) {
                    $stmt_upd = $conexion->prepare("UPDATE usuarios SET nombre = ?, foto_perfil = ?, contrasena = ? WHERE id = ?");
                    $stmt_upd->bind_param("sssi", $nuevo_nombre, $foto_nombre, $hash_nueva_pass, $usuario_id);
                } else {
                    $stmt_upd = $conexion->prepare("UPDATE usuarios SET nombre = ?, foto_perfil = ? WHERE id = ?");
                    $stmt_upd->bind_param("ssi", $nuevo_nombre, $foto_nombre, $usuario_id);
                }

                if ($stmt_upd->execute()) {
                    // Actualizar sesión
                    $_SESSION['usuario_nombre'] = $nuevo_nombre;
                    $_SESSION['usuario_foto'] = $foto_nombre;

                    // Reconsultar estado actual
                    $usuario['nombre'] = $nuevo_nombre;
                    $usuario['foto_perfil'] = $foto_nombre;

                    $mensaje_exito = "¡Perfil actualizado correctamente!";
                } else {
                    $mensaje_error = "Ocurrió un error al guardar los cambios en la base de datos.";
                }
            } else {
                $mensaje_info = "No realizaste ningún cambio en tu perfil.";
            }
        }
    }
}

// Ruta de la foto actual
$ruta_foto_actual = "uploads/perfiles/" . $usuario['foto_perfil'];
if (!file_exists($ruta_foto_actual) || empty($usuario['foto_perfil'])) {
    $ruta_foto_actual = "https://ui-avatars.com/api/?name=" . urlencode($usuario['nombre']) . "&background=9b51e0&color=ffffff";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Servicios Bienestar y Belleza</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <!-- Encabezado / Navbar -->
    <header class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand-logo">✨ Bienestar & Belleza</a>
            <nav class="nav-menu">
                <a href="index.php" class="nav-link">Inicio</a>
                <div class="user-menu">
                    <img src="<?php echo htmlspecialchars($ruta_foto_actual); ?>" alt="Foto de perfil" class="avatar-img">
                    <span class="user-name"><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                    <div class="dropdown-content">
                        <a href="perfil.php">Ver / Editar Perfil</a>
                        <?php if ($usuario['tipo_usuario'] === 'profesional'): ?>
                            <a href="profesional/panel.php">Mi Panel de Negocio</a>
                        <?php else: ?>
                            <a href="cliente/mis_reservas.php">Mis Reservas</a>
                        <?php endif; ?>
                        <a href="cerrar_sesion.php" class="logout-link">Cerrar Sesión</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Formulario de Edición de Perfil -->
    <main class="auth-body" style="min-height: calc(100vh - 80px); padding: 40px 20px;">
        <div class="auth-container" style="max-width: 520px;">
            <div class="auth-card">
                <div class="auth-header">
                    <h2>Mi Perfil</h2>
                    <p>Consulta y actualiza tu información personal</p>
                </div>

                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
                <?php endif; ?>

                <?php if (!empty($mensaje_exito)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
                <?php endif; ?>

                <?php if (!empty($mensaje_info)): ?>
                    <div class="alert alert-info" style="background-color: #e2e8f0; color: #4a5568; border: 1px solid #cbd5e0; padding: 12px 15px; border-radius: 8px; font-size: 14px; margin-bottom: 20px;"><?php echo htmlspecialchars($mensaje_info); ?></div>
                <?php endif; ?>

                <form action="perfil.php" method="POST" enctype="multipart/form-data" class="auth-form">
                    
                    <!-- Vista previa de foto actual -->
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="<?php echo htmlspecialchars($ruta_foto_actual); ?>" alt="Foto actual" style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #9b51e0;">
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="correo">Correo Electrónico (No editable)</label>
                        <input type="email" id="correo" value="<?php echo htmlspecialchars($usuario['correo']); ?>" disabled style="background-color: #edf2f7; cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label for="tipo_usuario">Tipo de Cuenta</label>
                        <input type="text" value="<?php echo ucfirst($usuario['tipo_usuario']); ?>" disabled style="background-color: #edf2f7; cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label for="foto_perfil">Cambiar Foto de Perfil (Opcional)</label>
                        <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*">
                    </div>

                    <hr style="border: 0; border-top: 1px solid #edf2f7; margin: 25px 0;">
                    <h3 style="font-size: 16px; color: #2d3748; margin-bottom: 15px;">Cambiar Contraseña (Opcional)</h3>

                    <div class="form-group">
                        <label for="password_actual">Contraseña Actual</label>
                        <input type="password" id="password_actual" name="password_actual" placeholder="Ingresa tu contraseña actual">
                    </div>

                    <div class="form-group">
                        <label for="password_nueva">Nueva Contraseña</label>
                        <input type="password" id="password_nueva" name="password_nueva" placeholder="Ingresa la nueva contraseña">
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </form>

            </div>
        </div>
    </main>

</body>
</html>