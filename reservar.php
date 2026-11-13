<?php
session_start();
require_once 'conexion/bd.php';

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Extrae [hora_apertura, hora_cierre] en formato 'HH:MM' desde el texto del horario */
function obtenerRangoHorario(?string $textoHorario): array {
    $texto = (string) $textoHorario;
    // Busca horas como "8:00 AM", "7:00 PM", "09:00", "19:00"
    if (preg_match_all('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $texto, $matches, PREG_SET_ORDER)) {
        $horas24 = [];
        foreach ($matches as $m) {
            $h = (int) $m[1];
            $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $meridiem = strtolower($m[3] ?? '');

            if ($meridiem === 'pm' && $h < 12) $h += 12;
            if ($meridiem === 'am' && $h === 12) $h = 0;

            if ($h >= 6 && $h <= 23) {
                $horas24[] = sprintf('%02d:%02d', $h, $min);
            }
        }
        if (count($horas24) >= 2) {
            return [$horas24[0], $horas24[1]]; // Primera hora = apertura, segunda = cierre
        }
    }
    return ['09:00', '20:00']; // Respaldo si el texto no tiene horas claras
}

$negocio_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($negocio_id <= 0) {
    header("Location: index.php");
    exit();
}

// 1. Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$cliente_id = (int) $_SESSION['usuario_id'];
$mensaje_exito = '';
$mensaje_error = '';

// 2. Consultar datos del negocio
$stmt = $conexion->prepare("SELECT id, usuario_id, nombre_negocio, direccion, horario FROM negocios WHERE id = ?");
$stmt->bind_param("i", $negocio_id);
$stmt->execute();
$res_neg = $stmt->get_result();

if ($res_neg->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$negocio = $res_neg->fetch_assoc();
[$hora_apertura, $hora_cierre] = obtenerRangoHorario($negocio['horario']);

// Evitar que el dueño se reserve a sí mismo
if ((int) $negocio['usuario_id'] === $cliente_id) {
    header("Location: negocio.php?id=" . $negocio_id);
    exit();
}

// 3. Consultar servicios del negocio
$stmt_serv = $conexion->prepare("SELECT id, nombre_servicio, precio, duracion_minutos FROM servicios WHERE negocio_id = ? ORDER BY precio ASC");
$stmt_serv->bind_param("i", $negocio_id);
$stmt_serv->execute();
$servicios = $stmt_serv->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Procesar envío de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servicio_id = (int) ($_POST['servicio_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    if ($servicio_id <= 0 || empty($fecha) || empty($hora)) {
        $mensaje_error = "Por favor selecciona un servicio, una fecha y un horario.";
    } elseif ($fecha < date('Y-m-d')) {
        $mensaje_error = "La fecha seleccionada no puede ser anterior a hoy.";
    } elseif ($hora < $hora_apertura || $hora >= $hora_cierre) {
        $mensaje_error = "El horario debe estar dentro del turno del negocio (" . date('g:i A', strtotime($hora_apertura)) . " a " . date('g:i A', strtotime($hora_cierre)) . ").";
    } elseif ($fecha === date('Y-m-d') && $hora <= date('H:i')) {
        $mensaje_error = "Si reservas para hoy, debes elegir una hora posterior a la actual.";
    } else {
        $stmt_ins = $conexion->prepare("INSERT INTO citas (cliente_id, negocio_id, servicio_id, fecha, hora, notas) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("iiisss", $cliente_id, $negocio_id, $servicio_id, $fecha, $hora, $notas);

        if ($stmt_ins->execute()) {
            $mensaje_exito = "¡Tu cita ha sido solicitada con éxito! El establecimiento la confirmará pronto.";
        } else {
            $mensaje_error = "Ocurrió un error al agendar tu cita. Intenta de nuevo.";
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reservar cita · <?= e($negocio['nombre_negocio']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;background:#F4F2FD;color:#2D3748}
.wrap{max-width:620px;margin:36px auto;padding:0 20px}
.card{background:#fff;border:1px solid #E4E0F2;border-radius:18px;padding:28px;box-shadow:0 4px 16px rgba(112,72,180,.06)}
.back{display:inline-block;margin-bottom:16px;color:#9048DB;font-weight:700;text-decoration:none;font-size:14px}
h1{margin:0 0 6px;font-size:24px}
.sub{color:#64748B;font-size:14px;margin-bottom:22px}
.grp{margin-bottom:18px}
label{display:block;font-size:14px;font-weight:700;margin-bottom:8px}
select,input,textarea{width:100%;padding:12px 14px;border:1px solid #DDD8F0;border-radius:12px;background:#F7F8FC;font:400 15px 'DM Sans',sans-serif;color:#2D3748}
select:focus,input:focus,textarea:focus{outline:none;border-color:#9048DB;background:#fff}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btn{width:100%;height:50px;border:0;border-radius:12px;background:#9048DB;color:#fff;font:700 16px 'DM Sans',sans-serif;cursor:pointer;margin-top:8px}
.btn:hover{background:#7b35c4}
.alert{padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:14px;font-weight:500}
.ok{background:#DCFCE7;color:#166534;border:1px solid #BBF7D0}
.err{background:#FEE2E2;color:#991B1B;border:1px solid #FECACA}
</style>
</head>
<body>
<div class="wrap">
  <a href="negocio.php?id=<?= $negocio_id ?>" class="back">← Volver a <?= e($negocio['nombre_negocio']) ?></a>

  <div class="card">
    <h1>Reservar cita</h1>
    <div class="sub">📍 <?= e($negocio['direccion']) ?> · ⏰ <?= e($negocio['horario'] ?: 'Horario regular') ?></div>

    <?php if ($mensaje_exito): ?>
      <div class="alert ok">
        <?= e($mensaje_exito) ?>
        <div style="margin-top:10px;">
          <a href="index.php" style="color:#166534;font-weight:700;text-decoration:underline;">Volver al inicio</a>
        </div>
      </div>
    <?php elseif ($mensaje_error): ?>
      <div class="alert err"><?= e($mensaje_error) ?></div>
    <?php endif; ?>

    <?php if (empty($servicios)): ?>
      <p style="color:#64748B;">Este establecimiento aún no tiene servicios disponibles para reservar en línea.</p>
    <?php elseif (!$mensaje_exito): ?>
      <form method="POST" action="reservar.php?id=<?= $negocio_id ?>">
        <div class="grp">
          <label for="servicio_id">1. Elige el servicio *</label>
          <select name="servicio_id" id="servicio_id" required>
            <option value="">Selecciona un servicio...</option>
            <?php foreach ($servicios as $s): ?>
              <option value="<?= (int)$s['id'] ?>">
                <?= e($s['nombre_servicio']) ?> — $<?= number_format((float)$s['precio'], 0) ?> (<?= (int)$s['duracion_minutos'] ?> min)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row2">
          <div class="grp">
            <label for="fecha">2. Fecha *</label>
            <input type="date" id="fecha" name="fecha" min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="grp">
            <label for="hora">3. Hora preferida *</label>
            <select id="hora" name="hora" required>
              <option value="">Selecciona hora...</option>
              <?php
              $inicio = strtotime($hora_apertura);
              $fin = strtotime($hora_cierre);

              // Genera bloques cada 30 min parando antes de la hora de cierre
              for ($t = $inicio; $t < $fin; $t += 1800) {
                  $val = date('H:i', $t);
                  $ampm = date('g:i A', $t);
                  echo "<option value=\"$val\">$ampm</option>";
              }
              ?>
            </select>
          </div>
        </div>

        <div class="grp">
          <label for="notas">Notas adicionales (opcional)</label>
          <textarea id="notas" name="notas" rows="2" placeholder="Ej. Prefiero corte con tijera, alergia a algún producto..."></textarea>
        </div>

        <button type="submit" class="btn">Confirmar reservación</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>