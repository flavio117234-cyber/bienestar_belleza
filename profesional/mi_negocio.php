<?php
session_start();
require_once '../conexion/bd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'profesional') {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje_error = '';
$mensaje_exito = '';

$stmt = $conexion->prepare("SELECT id, nombre_negocio, descripcion, direccion, telefono, horario, foto_portada FROM negocios WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$negocio = $res->fetch_assoc();

$tiene_negocio = ($res->num_rows > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_negocio = trim($_POST['nombre_negocio']);
    $descripcion = trim($_POST['descripcion']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $horario = trim($_POST['horario']);
    $foto_portada = $tiene_negocio ? $negocio['foto_portada'] : 'default_negocio.png';

    if (empty($nombre_negocio) || empty($direccion) || empty($telefono) || empty($horario)) {
        $mensaje_error = "El nombre, dirección, teléfono y horario de atención son obligatorios.";
    } else {
        if (isset($_FILES['foto_portada']) && $_FILES['foto_portada']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['foto_portada']['tmp_name'];
            $nombre_original = $_FILES['foto_portada']['name'];
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            $ext_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($extension, $ext_permitidas)) {
                $nueva_foto = uniqid('negocio_') . '.' . $extension;
                $ruta_destino = '../uploads/negocios/' . $nueva_foto;

                if (!is_dir('../uploads/negocios')) {
                    mkdir('../uploads/negocios', 0777, true);
                }

                if (move_uploaded_file($foto_tmp, $ruta_destino)) {
                    if ($foto_portada !== 'default_negocio.png' && file_exists('../uploads/negocios/' . $foto_portada)) {
                        unlink('../uploads/negocios/' . $foto_portada);
                    }
                    $foto_portada = $nueva_foto;
                }
            } else {
                $mensaje_error = "Formato de imagen no válido. Utiliza JPG, PNG, WEBP o GIF.";
            }
        }

        if (empty($mensaje_error)) {
            if ($tiene_negocio) {
                $stmt_upd = $conexion->prepare("UPDATE negocios SET nombre_negocio = ?, descripcion = ?, direccion = ?, telefono = ?, horario = ?, foto_portada = ? WHERE usuario_id = ?");
                $stmt_upd->bind_param("ssssssi", $nombre_negocio, $descripcion, $direccion, $telefono, $horario, $foto_portada, $usuario_id);
                
                if ($stmt_upd->execute()) {
                    $mensaje_exito = "¡Datos del negocio actualizados correctamente!";
                } else {
                    $mensaje_error = "Error al actualizar la información del negocio.";
                }
            } else {
                $stmt_ins = $conexion->prepare("INSERT INTO negocios (usuario_id, nombre_negocio, descripcion, direccion, telefono, horario, foto_portada) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("issssss", $usuario_id, $nombre_negocio, $descripcion, $direccion, $telefono, $horario, $foto_portada);

                if ($stmt_ins->execute()) {
                    $mensaje_exito = "¡Negocio registrado con éxito!";
                    $tiene_negocio = true;
                } else {
                    $mensaje_error = "Error al registrar el negocio.";
                }
            }

            $stmt->execute();
            $negocio = $stmt->get_result()->fetch_assoc();
        }
    }
}

$ruta_foto_user = "../uploads/perfiles/" . $_SESSION['usuario_foto'];
if (!file_exists($ruta_foto_user) || empty($_SESSION['usuario_foto'])) {
    $ruta_foto_user = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['usuario_nombre']) . "&background=9b51e0&color=ffffff";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Negocio - Panel Profesional</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <header class="navbar">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo">✨ Bienestar & Belleza</a>
            <nav class="nav-menu">
                <a href="../index.php" class="nav-link">Inicio</a>
                <a href="panel.php" class="nav-link">Panel</a>
                <div class="user-menu">
                    <img src="<?php echo htmlspecialchars($ruta_foto_user); ?>" alt="Foto de perfil" class="avatar-img">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <div class="dropdown-content">
                        <a href="../perfil.php">Ver / Editar Perfil</a>
                        <a href="panel.php">Mi Panel de Negocio</a>
                        <a href="../cerrar_sesion.php" class="logout-link">Cerrar Sesión</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="auth-body" style="min-height: calc(100vh - 80px); padding: 40px 20px;">
        <div class="auth-container" style="max-width: 580px;">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><?php echo $tiene_negocio ? 'Editar Mi Negocio' : 'Registrar Mi Negocio'; ?></h2>
                    <p>Configura la información que verán tus clientes en Xalapa</p>
                </div>

                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
                <?php endif; ?>

                <?php if (!empty($mensaje_exito)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
                <?php endif; ?>

                <form action="mi_negocio.php" method="POST" enctype="multipart/form-data" class="auth-form">
                    
                    <?php if ($tiene_negocio && !empty($negocio['foto_portada'])): ?>
                        <div style="text-align: center; margin-bottom: 20px;">
                            <img src="../uploads/negocios/<?php echo htmlspecialchars($negocio['foto_portada']); ?>" alt="Portada Negocio" style="width: 100%; height: 160px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nombre_negocio">Nombre del Establecimiento / Marca *</label>
                        <input type="text" id="nombre_negocio" name="nombre_negocio" placeholder="Ej. Barbería Xalapa Centro" required value="<?php echo $tiene_negocio ? htmlspecialchars($negocio['nombre_negocio']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción de tus servicios</label>
                        <input type="text" id="descripcion" name="descripcion" placeholder="Ej. Especialistas en cortes modernos, barba y cuidado facial." value="<?php echo $tiene_negocio ? htmlspecialchars($negocio['descripcion']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="direccion">Dirección en Xalapa *</label>
                        <input type="text" id="direccion" name="direccion" placeholder="Ej. Av. Araucarias #123, Col. Indeco Animas" required value="<?php echo $tiene_negocio ? htmlspecialchars($negocio['direccion']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono / WhatsApp de contacto *</label>
                        <input type="text" id="telefono" name="telefono" placeholder="Ej. 2281234567" required value="<?php echo $tiene_negocio ? htmlspecialchars($negocio['telefono']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="horario">Horario de Atención *</label>
                        <input type="text" id="horario" name="horario" placeholder="Ej. Lun - Vie: 9:00 AM - 7:00 PM | Sáb: 10:00 AM - 3:00 PM" required value="<?php echo $tiene_negocio && isset($negocio['horario']) ? htmlspecialchars($negocio['horario']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="foto_portada">Imagen o Logo del Negocio</label>
                        <input type="file" id="foto_portada" name="foto_portada" accept="image/*">
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo $tiene_negocio ? 'Guardar Cambios' : 'Crear Negocio'; ?></button>
                </form>

            </div>
        </div>
    </main>

</body>
</html>