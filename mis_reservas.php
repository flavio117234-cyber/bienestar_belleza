<?php
session_start();
require_once 'conexion/bd.php';

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 1. Verificar que haya sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$cliente_id = (int) $_SESSION['usuario_id'];
$mensaje_exito = '';
$mensaje_error = '';

// 2. Procesar cancelación por parte del cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_id'])) {
    $cita_id = (int) $_POST['cancelar_id'];

    // Solo permitir cancelar citas que pertenezcan al cliente y no estén ya canceladas
    $stmt_upd = $conexion->prepare("
        UPDATE citas 
        SET estado = 'cancelada' 
        WHERE id = ? AND cliente_id = ? AND estado IN ('pendiente', 'confirmada')
    ");
    $stmt_upd->bind_param("ii", $cita_id, $cliente_id);

    if ($stmt_upd->execute() && $stmt_upd->affected_rows > 0) {
        $mensaje_exito = "Tu cita ha sido cancelada correctamente.";
    } else {
        $mensaje_error = "No se pudo cancelar la cita seleccionada.";
    }
}

// 3. Consultar todas las citas del cliente con datos del negocio y del servicio
$stmt = $conexion->prepare("
    SELECT 
        c.id, c.fecha, c.hora, c.notas, c.estado,
        n.id AS negocio_id, n.nombre_negocio, n.direccion, n.telefono,
        s.nombre_servicio, s.precio, s.duracion_minutos
    FROM citas c
    INNER JOIN negocios n ON c.negocio_id = n.id
    INNER JOIN servicios s ON c.servicio_id = s.id
    WHERE c.cliente_id = ?
    ORDER BY c.fecha DESC, c.hora DESC
");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$hoy = date('Y-m-d');
$ahora = date('H:i:s');
$proximas = [];
$historial = [];

foreach ($citas as $c) {
    $es_futura = ($c['fecha'] > $hoy) || ($c['fecha'] === $hoy && $c['hora'] >= $ahora);
    if ($es_futura && $c['estado'] !== 'cancelada') {
        $proximas[] = $c;
    } else {
        $historial[] = $c;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis Citas · Bienestar &amp; Belleza</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;background:#F4F2FD;color:#2D3748}
a{color:inherit;text-decoration:none}
.top{background:#fff;border-bottom:1px solid #E4E0F2;padding:16px 20px}
.nav{max-width:960px;margin:0 auto;display:flex;justify-content:space-between;align-items:center}
.brand{font-weight:700;font-size:17px;color:#9048DB}
.nav-links{display:flex;gap:16px;font-size:14px;font-weight:500}
.nav-links a:hover{color:#9048DB}
.wrap{max-width:960px;margin:28px auto;padding:0 20px 48px}
h1{margin:0 0 6px;font-size:26px}
.sub{color:#64748B;font-size:14px;margin-bottom:24px}
h2{font-size:18px;margin:28px 0 14px;color:#2D3748}
.card{background:#fff;border:1px solid #E4E0F2;border-radius:16px;padding:20px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
.badge{padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;text-transform:uppercase}
.st-pendiente{background:#FEF3C7;color:#92400E}
.st-confirmada{background:#DCFCE7;color:#166534}
.st-cancelada{background:#FEE2E2;color:#991B1B}
.meta{font-size:14px;color:#4B5563;margin-top:6px;line-height:1.5}
.acts{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn-ver{padding:9px 14px;border:1.5px solid #9048DB;border-radius:10px;color:#9048DB;font-size:13px;font-weight:700;background:#fff}
.btn-ver:hover{background:#F0E6FC}
.btn-cancel{padding:9px 14px;border:0;border-radius:10px;background:#FEE2E2;color:#991B1B;font:700 13px 'DM Sans',sans-serif;cursor:pointer}
.btn-cancel:hover{background:#FECACA}
.empty{background:#fff;border:1px solid #E4E0F2;border-radius:16px;padding:36px 20px;text-align:center;color:#64748B}
.btn-cta{display:inline-block;margin-top:14px;padding:11px 22px;background:#9048DB;color:#fff;border-radius:12px;font-weight:700;font-size:14px}
.alert{padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:14px;font-weight:500}
.ok{background:#DCFCE7;color:#166534;border:1px solid #BBF7D0}
.err{background:#FEE2E2;color:#991B1B;border:1px solid #FECACA}
</style>
</head>
<body>

<header class="top">
  <div class="nav">
    <a href="index.php" class="brand">✨ Bienestar &amp; Belleza</a>
    <div class="nav-links">
      <a href="index.php">Inicio</a>
      <a href="buscar.php">Explorar</a>
      <a href="perfil.php">Mi Perfil</a>
    </div>
  </div>
</header>

<div class="wrap">
  <h1>Mis Citas</h1>
  <div class="sub">Consulta el estado de tus reservaciones en Xalapa</div>

  <?php if ($mensaje_exito): ?>
    <div class="alert ok"><?= e($mensaje_exito) ?></div>
  <?php elseif ($mensaje_error): ?>
    <div class="alert err"><?= e($mensaje_error) ?></div>
  <?php endif; ?>

  <h2>Próximas citas (<?= count($proximas) ?>)</h2>
  <?php if (empty($proximas)): ?>
    <div class="empty">
      No tienes citas próximas agendadas.
      <br><a href="buscar.php" class="btn-cta">Explorar negocios</a>
    </div>
  <?php else: ?>
    <?php foreach ($proximas as $c): ?>
      <div class="card">
        <div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <strong style="font-size:17px;"><?= e($c['nombre_servicio']) ?></strong>
            <span class="badge st-<?= e($c['estado']) ?>"><?= e($c['estado']) ?></span>
          </div>
          <div class="meta">
            🏪 <strong><?= e($c['nombre_negocio']) ?></strong> · 📍 <?= e($c['direccion']) ?><br>
            📅 <strong><?= date('d/m/Y', strtotime($c['fecha'])) ?></strong> a las <strong><?= date('g:i A', strtotime($c['hora'])) ?></strong>
            · ⏱️ <?= (int)$c['duracion_minutos'] ?> min · 💲$<?= number_format((float)$c['precio'], 0) ?>
          </div>
          <?php if (!empty($c['notas'])): ?>
            <div style="font-size:13px;color:#64748B;margin-top:6px;">📝 Notas: <?= e($c['notas']) ?></div>
          <?php endif; ?>
        </div>

        <div class="acts">
          <a href="negocio.php?id=<?= (int)$c['negocio_id'] ?>" class="btn-ver">Ver negocio</a>
          <form method="POST" style="margin:0;" onsubmit="return confirm('¿Estás seguro de que deseas cancelar esta cita?');">
            <input type="hidden" name="cancelar_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn-cancel">Cancelar cita</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($historial)): ?>
    <h2>Historial y canceladas (<?= count($historial) ?>)</h2>
    <?php foreach ($historial as $c): ?>
      <div class="card" style="opacity:.82;">
        <div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <strong style="font-size:16px;"><?= e($c['nombre_servicio']) ?></strong>
            <span class="badge st-<?= e($c['estado']) ?>"><?= e($c['estado']) ?></span>
          </div>
          <div class="meta">
            🏪 <?= e($c['nombre_negocio']) ?> · 📅 <?= date('d/m/Y', strtotime($c['fecha'])) ?> a las <?= date('g:i A', strtotime($c['hora'])) ?> · 💲$<?= number_format((float)$c['precio'], 0) ?>
          </div>
        </div>
        <div class="acts">
          <?php if ($c['estado'] !== 'cancelada'): ?>
            <a href="resena.php?id=<?= (int)$c['negocio_id'] ?>" class="btn-ver" style="background:#F0E6FC;">⭐ Dejar reseña</a>
          <?php endif; ?>
          <a href="negocio.php?id=<?= (int)$c['negocio_id'] ?>" class="btn-ver">Volver a reservar</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>