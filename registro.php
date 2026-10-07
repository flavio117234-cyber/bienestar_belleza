<?php
session_start();
require_once 'conexion/bd.php';

$mensaje_error = '';
$mensaje_exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $tipo_usuario = $_POST['tipo_usuario'];
    $foto_nombre = 'default_avatar.png'; // Imagen por defecto

    // Validar campos obligatorios
    if (empty($nombre) || empty($correo) || empty($contrasena) || empty($tipo_usuario)) {
        $mensaje_error = "Por favor, completa todos los campos obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "El correo electrónico no tiene un formato válido.";
    } else {
        // Verificar si el correo ya existe
        $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt_check->bind_param("s", $correo);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check->num_rows > 0) {
            $mensaje_error = "El correo electrónico ya está registrado.";
        } else {
            // Procesar la foto de perfil si se subió una
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $foto_tmp = $_FILES['foto_perfil']['tmp_name'];
                $nombre_original = $_FILES['foto_perfil']['name'];
                $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                $ext_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (in_array($extension, $ext_permitidas)) {
                    // Nombre único para evitar sobreescribir imágenes
                    $foto_nombre = uniqid('user_') . '.' . $extension;
                    $ruta_destino = 'uploads/perfiles/' . $foto_nombre;

                    if (!is_dir('uploads/perfiles')) {
                        mkdir('uploads/perfiles', 0777, true);
                    }

                    if (!move_uploaded_file($foto_tmp, $ruta_destino)) {
                        $foto_nombre = 'default_avatar.png';
                    }
                } else {
                    $mensaje_error = "Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.";
                }
            }

            // Guardar usuario en la base de datos si no hay errores
            if (empty($mensaje_error)) {
                $hash_password = password_hash($contrasena, PASSWORD_BCRYPT);
                $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, correo, contrasena, tipo_usuario, foto_perfil) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("sssss", $nombre, $correo, $hash_password, $tipo_usuario, $foto_nombre);

                if ($stmt_insert->execute()) {
                    $mensaje_exito = "¡Cuenta creada exitosamente! Ya puedes iniciar sesión.";
                } else {
                    $mensaje_error = "Ocurrió un error al registrar la cuenta. Inténtalo de nuevo.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Servicios Bienestar y Belleza</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="auth-body">

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Crear Cuenta</h2>
                <p>Bienestar & Belleza - Xalapa</p>
            </div>

            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
            <?php endif; ?>

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
            <?php endif; ?>

            <form action="registro.php" method="POST" enctype="multipart/form-data" class="auth-form">
                <div class="form-group">
                    <label for="nombre">Nombre Completo *</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej. Ana García" required value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="correo">Correo Electrónico *</label>
                    <input type="email" id="correo" name="correo" placeholder="correo@ejemplo.com" required value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="contrasena">Contraseña *</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Cuenta *</label>
                    <select id="tipo_usuario" name="tipo_usuario" required>
                        <option value="cliente" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'cliente') ? 'selected' : ''; ?>>Cliente (Buscar y reservar servicios)</option>
                        <option value="profesional" <?php echo (isset($_POST['tipo_usuario']) && $_POST['tipo_usuario'] === 'profesional') ? 'selected' : ''; ?>>Profesional / Negocio (Ofrecer servicios)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="foto_perfil">Foto de Perfil (Opcional)</label>
                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*">
                </div>

                <button type="submit" class="btn btn-primary">Registrarse</button>
            </form>

            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>

</body>
</html>