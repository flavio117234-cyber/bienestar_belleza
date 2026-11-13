<?php
session_start();
require_once '../conexion/bd.php';

// 1. Verificar autenticación y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'profesional') {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje_exito = '';
$mensaje_error = '';

// 2. Obtener el ID del negocio asociado a este profesional
$stmt_neg = $conexion->prepare("SELECT id, nombre_negocio FROM negocios WHERE usuario_id = ?");
$stmt_neg->bind_param("i", $usuario_id);
$stmt_neg->execute();
$res_neg = $stmt_neg->get_result();

if ($res_neg->num_rows === 0) {
    header("Location: mi_negocio.php");
    exit();
}

$negocio = $res_neg->fetch_assoc();
$negocio_id = (int) $negocio['id'];

// 3. Procesar acciones (Agregar, Editar o Eliminar servicio)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $nombre = trim($_POST['nombre_servicio'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = (float) ($_POST['precio'] ?? 0);
        $duracion = (int) ($_POST['duracion_minutos'] ?? 30);

        if (empty($nombre) || $precio <= 0 || $duracion <= 0) {
            $mensaje_error = "Por favor ingresa un nombre válido, un precio mayor a $0 y una duración en minutos.";
        } else {
            if ($accion === 'agregar') {
                $stmt = $conexion->prepare("INSERT INTO servicios (negocio_id, nombre_servicio, descripcion, precio, duracion_minutos) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issdi", $negocio_id, $nombre, $descripcion, $precio, $duracion);
                if ($stmt->execute()) {
                    $mensaje_exito = "¡Servicio agregado correctamente!";
                } else {
                    $mensaje_error = "Error al guardar el servicio.";
                }
            } elseif ($accion === 'editar') {
                $servicio_id = (int) ($_POST['servicio_id'] ?? 0);
                $stmt = $conexion->prepare("UPDATE servicios SET nombre_servicio = ?, descripcion = ?, precio = ?, duracion_minutos = ? WHERE id = ? AND negocio_id = ?");
                $stmt->bind_param("ssdiii", $nombre, $descripcion, $precio, $duracion, $servicio_id, $negocio_id);
                if ($stmt->execute()) {
                    $mensaje_exito = "¡Servicio actualizado!";
                } else {
                    $mensaje_error = "Error al actualizar el servicio.";
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $servicio_id = (int) ($_POST['servicio_id'] ?? 0);
        $stmt = $conexion->prepare("DELETE FROM servicios WHERE id = ? AND negocio_id = ?");
        $stmt->bind_param("ii", $servicio_id, $negocio_id);
        if ($stmt->execute()) {
            $mensaje_exito = "Servicio eliminado.";
        } else {
            $mensaje_error = "No se pudo eliminar el servicio.";
        }
    }
}

// 4. Si se solicitó editar un servicio por GET, cargar sus datos
$servicio_editar = null;
if (isset($_GET['editar'])) {
    $edit_id = (int) $_GET['editar'];
    $stmt_ed = $conexion->prepare("SELECT id, nombre_servicio, descripcion, precio, duracion_minutos FROM servicios WHERE id = ? AND negocio_id = ?");
    $stmt_ed->bind_param("ii", $edit_id, $negocio_id);
    $stmt_ed->execute();
    $res_ed = $stmt_ed->get_result();
    if ($res_ed->num_rows === 1) {
        $servicio_editar = $res_ed->fetch_assoc();
    }
}

// 5. Listar todos los servicios actuales del negocio
$stmt_lista = $conexion->prepare("SELECT id, nombre_servicio, descripcion, precio, duracion_minutos FROM servicios WHERE negocio_id = ? ORDER BY id DESC");
$stmt_lista->bind_param("i", $negocio_id);
$stmt_lista->execute();
$servicios = $stmt_lista->get_result()->fetch_all(MYSQLI_ASSOC);

// Foto de perfil para navbar
$ruta_foto_user = "../uploads/perfiles/" . ($_SESSION['usuario_foto'] ?? '');
if (!file_exists($ruta_foto_user) || empty($_SESSION['usuario_foto'])) {
    $ruta_foto_user = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['usuario_nombre']) . "&background=9048DB&color=ffffff";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Servicios · Bienestar &amp; Belleza</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #F4F2FD; color: #2D3748; }
        .layout-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        @media (min-width: 900px) { .layout-grid { grid-template-columns: 380px 1fr; align-items: start; } }
        .card-box { background: #fff; border: 1px solid #E4E0F2; border-radius: 16px; padding: 24px; }
        .btn-purple { background-color: #9048DB; color: #fff; border: none; border-radius: 10px; padding: 12px 18px; font-weight: 700; font-size: 14px; cursor: pointer; width: 100%; }
        .btn-purple:hover { background-color: #7b35c4; }
        .btn-sm { padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; }
        .btn-edit { background: #F0E6FC; color: #6D28B8; }
        .btn-del { background: #FEE2E2; color: #991B1B; }
        .svc-item { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 0; border-bottom: 1px solid #E4E0F2; }
        .svc-item:last-child { border-bottom: none; padding-bottom: 0; }
        .price-tag { font-size: 16px; font-weight: 700; color: #9048DB; }
        .form-input { width: 100%; padding: 11px 14px; border: 1px solid #DDD8F0; border-radius: 10px; font-family: inherit; font-size: 14px; margin-top: 6px; background: #F7F8FC; }
        .form-input:focus { outline: none; border-color: #9048DB; background: #fff; }
    </style>
</head>
<body>

    <header class="navbar" style="background:#fff; border-bottom:1px solid #E4E0F2;">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo" style="color:#2D3748;">✨ Bienestar &amp; Belleza</a>
            <nav class="nav-menu">
                <a href="panel.php" class="nav-link">← Volver al Panel</a>
                <a href="../negocio.php?id=<?php echo $negocio_id; ?>" class="nav-link" style="color:#9048DB;">Ver mi perfil público</a>
                <div class="user-menu">
                    <img src="<?php echo htmlspecialchars($ruta_foto_user); ?>" alt="Foto" class="avatar-img" style="border-color:#9048DB;">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                </div>
            </nav>
        </div>
    </header>

    <main class="main-container">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
            <div>
                <h2 class="section-title" style="margin:0;">Mis Servicios y Precios</h2>
                <p style="color:#64748B; font-size:14px; margin-top:4px;">Establecimiento: <strong><?php echo htmlspecialchars($negocio['nombre_negocio']); ?></strong></p>
            </div>
        </div>

        <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success" style="margin-bottom:20px; border-radius:12px;"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-error" style="margin-bottom:20px; border-radius:12px;"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <div class="layout-grid">
            <!-- Formulario Agregar / Editar -->
            <div class="card-box">
                <h3 style="margin:0 0 16px; font-size:18px;">
                    <?php echo $servicio_editar ? '✏️ Editar Servicio' : '➕ Agregar Nuevo Servicio'; ?>
                </h3>

                <form action="servicios.php" method="POST">
                    <input type="hidden" name="accion" value="<?php echo $servicio_editar ? 'editar' : 'agregar'; ?>">
                    <?php if ($servicio_editar): ?>
                        <input type="hidden" name="servicio_id" value="<?php echo (int) $servicio_editar['id']; ?>">
                    <?php endif; ?>

                    <div style="margin-bottom:14px;">
                        <label style="font-size:13px; font-weight:700;">Nombre del servicio *</label>
                        <input type="text" name="nombre_servicio" class="form-input" placeholder="Ej. Corte clásico + Barba" required
                               value="<?php echo $servicio_editar ? htmlspecialchars($servicio_editar['nombre_servicio']) : ''; ?>">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="font-size:13px; font-weight:700;">Precio ($ MXN) *</label>
                            <input type="number" step="0.01" min="1" name="precio" class="form-input" placeholder="180" required
                                   value="<?php echo $servicio_editar ? htmlspecialchars($servicio_editar['precio']) : ''; ?>">
                        </div>
                        <div>
                            <label style="font-size:13px; font-weight:700;">Duración (min) *</label>
                            <input type="number" step="5" min="5" name="duracion_minutos" class="form-input" placeholder="45" required
                                   value="<?php echo $servicio_editar ? (int) $servicio_editar['duracion_minutos'] : '30'; ?>">
                        </div>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="font-size:13px; font-weight:700;">Descripción breve (opcional)</label>
                        <textarea name="descripcion" rows="2" class="form-input" placeholder="Incluye lavado y arreglo de ceja..."><?php echo $servicio_editar ? htmlspecialchars($servicio_editar['descripcion']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn-purple">
                        <?php echo $servicio_editar ? 'Guardar Cambios' : 'Publicar Servicio'; ?>
                    </button>

                    <?php if ($servicio_editar): ?>
                        <a href="servicios.php" style="display:block; text-align:center; margin-top:12px; font-size:13px; color:#64748B;">Cancelar edición</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Lista de Servicios Publicados -->
            <div class="card-box">
                <h3 style="margin:0 0 16px; font-size:18px;">Servicios Publicados (<?php echo count($servicios); ?>)</h3>

                <?php if (empty($servicios)): ?>
                    <p style="color:#64748B; text-align:center; padding:30px 10px;">
                        Aún no has agregado ningún servicio.<br>Usa el formulario de la izquierda para publicar tu primer corte, masaje o tratamiento.
                    </p>
                <?php else: ?>
                    <div>
                        <?php foreach ($servicios as $s): ?>
                            <div class="svc-item">
                                <div>
                                    <div style="font-weight:700; font-size:15px;"><?php echo htmlspecialchars($s['nombre_servicio']); ?></div>
                                    <div style="font-size:13px; color:#64748B; margin-top:2px;">
                                        ⏱️ <?php echo (int) $s['duracion_minutos']; ?> min
                                        <?php if (!empty($s['descripcion'])): ?>
                                            · <?php echo htmlspecialchars($s['descripcion']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="price-tag" style="margin-top:4px;">$<?php echo number_format((float) $s['precio'], 0, '.', ','); ?></div>
                                </div>

                                <div style="display:flex; gap:8px; flex-shrink:0;">
                                    <a href="servicios.php?editar=<?php echo (int) $s['id']; ?>" class="btn-sm btn-edit">Editar</a>
                                    <form action="servicios.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Seguro que deseas eliminar este servicio?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="servicio_id" value="<?php echo (int) $s['id']; ?>">
                                        <button type="submit" class="btn-sm btn-del">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>
</html>