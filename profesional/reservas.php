<?php
session_start();
require_once '../conexion/bd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'profesional') {
    header("Location: ../login.php");
    exit();
}

$usuario_id = (int) $_SESSION['usuario_id'];
$mensaje = '';

// Obtener negocio del profesional
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

// Cambiar estado de una cita (confirmar / cancelar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cita_id'], $_POST['nuevo_estado'])) {
    $cita_id = (int) $_POST['cita_id'];
    $nuevo_estado = $_POST['nuevo_estado'];

    if (in_array($nuevo_estado, ['confirmada', 'cancelada'], true)) {
        $stmt_upd = $conexion->prepare("UPDATE citas SET estado = ? WHERE id = ? AND negocio_id = ?");
        $stmt_upd->bind_param("sii", $nuevo_estado, $cita_id, $negocio_id);
        if ($stmt_upd->execute()) {
            $mensaje = "Estado de la cita actualizado a: " . ucfirst($nuevo_estado) . ".";
        }
    }
}

// Listar citas del negocio
$stmt_citas = $conexion->prepare("
    SELECT c.id, c.fecha, c.hora, c.notas, c.estado,
           u.nombre AS cliente_nombre, u.correo AS cliente_correo,
           s.nombre_servicio, s.precio, s.duracion_minutos
    FROM citas c
    INNER JOIN usuarios u ON c.cliente_id = u.id
    INNER JOIN servicios s ON c.servicio_id = s.id
    WHERE c.negocio_id = ?
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmt_citas->bind_param("i", $negocio_id);
$stmt_citas->execute();
$citas = $stmt_citas->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Gestionar Citas · Bienestar &amp; Belleza</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #F4F2FD; color: #2D3748; }
        .cita-card { background: #fff; border: 1px solid #E4E0F2; border-radius: 16px; padding: 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .st-pendiente { background: #FEF3C7; color: #92400E; }
        .st-confirmada { background: #DCFCE7; color: #166534; }
        .st-cancelada { background: #FEE2E2; color: #991B1B; }
        .btn-act { padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; }
        .btn-ok { background: #9048DB; color: #fff; }
        .btn-no { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>

    <header class="navbar" style="background:#fff; border-bottom:1px solid #E4E0F2;">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo" style="color:#2D3748;">✨ Bienestar &amp; Belleza</a>
            <nav class="nav-menu">
                <a href="panel.php" class="nav-link">← Volver al Panel</a>
                <div class="user-menu">
                    <img src="<?php echo htmlspecialchars($ruta_foto_user); ?>" alt="Foto" class="avatar-img" style="border-color:#9048DB;">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                </div>
            </nav>
        </div>
    </header>

    <main class="main-container">
        <h2 class="section-title">Citas y Reservaciones · <?php echo htmlspecialchars($negocio['nombre_negocio']); ?></h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success" style="margin-bottom:20px; border-radius:12px;"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (empty($citas)): ?>
            <div style="background:#fff; border:1px solid #E4E0F2; border-radius:16px; padding:40px 20px; text-align:center; color:#64748B;">
                Aún no tienes citas agendadas por clientes.
            </div>
        <?php else: ?>
            <?php foreach ($citas as $c): ?>
                <div class="cita-card">
                    <div>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                            <strong style="font-size:17px;"><?php echo htmlspecialchars($c['nombre_servicio']); ?></strong>
                            <span class="badge st-<?php echo htmlspecialchars($c['estado']); ?>">
                                <?php echo htmlspecialchars($c['estado']); ?>
                            </span>
                        </div>
                        <div style="font-size:14px; color:#4B5563;">
                            📅 <strong><?php echo date('d/m/Y', strtotime($c['fecha'])); ?></strong> a las <strong><?php echo substr($c['hora'], 0, 5); ?> hrs</strong>
                            · Cliente: <strong><?php echo htmlspecialchars($c['cliente_nombre']); ?></strong> (<?php echo htmlspecialchars($c['cliente_correo']); ?>)
                        </div>
                        <?php if (!empty($c['notas'])): ?>
                            <div style="font-size:13px; color:#64748B; margin-top:6px;">📝 Nota: <?php echo htmlspecialchars($c['notas']); ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($c['estado'] === 'pendiente'): ?>
                        <div style="display:flex; gap:8px;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="cita_id" value="<?php echo (int)$c['id']; ?>">
                                <input type="hidden" name="nuevo_estado" value="confirmada">
                                <button type="submit" class="btn-act btn-ok">✓ Confirmar</button>
                            </form>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('¿Cancelar esta cita?');">
                                <input type="hidden" name="cita_id" value="<?php echo (int)$c['id']; ?>">
                                <input type="hidden" name="nuevo_estado" value="cancelada">
                                <button type="submit" class="btn-act btn-no">✕ Cancelar</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

</body>
</html>