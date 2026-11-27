<?php
session_start();
require_once 'conexion/bd.php';
date_default_timezone_set('America/Mexico_City');

// 1. Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

const MIN_CARACTERES = 20;
const MAX_CARACTERES = 500;
const MAX_FOTOS      = 3;
const MAX_BYTES_FOTO = 3 * 1024 * 1024;
const CARPETA_FOTOS  = __DIR__ . '/uploads/resenas';

$ETIQUETAS = ['Atención', 'Limpieza', 'Puntualidad', 'Precio justo', 'Resultado'];
$TEXTOS_ESTRELLAS = [1 => 'Malo', 2 => 'Regular', 3 => 'Bien', 4 => 'Muy bueno', 5 => 'Excelente'];

$usuarioId = (int) $_SESSION['usuario_id'];
$negocioId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['negocio_id'] ?? 0);

if ($negocioId <= 0) {
    header("Location: index.php");
    exit();
}

function e($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 2. Obtener datos reales del negocio y la última cita del cliente (si existe)
$stmt_neg = $conexion->prepare("SELECT id, usuario_id, nombre_negocio, foto_portada FROM negocios WHERE id = ?");
$stmt_neg->bind_param("i", $negocioId);
$stmt_neg->execute();
$res_neg = $stmt_neg->get_result();

if ($res_neg->num_rows === 0) {
    http_response_code(404);
    exit('Negocio no encontrado.');
}

$negocioRow = $res_neg->fetch_assoc();

// Evitar que el dueño reseñe su propio negocio
if ((int) $negocioRow['usuario_id'] === $usuarioId) {
    header("Location: negocio.php?id=" . $negocioId);
    exit();
}

// Buscar si tiene alguna cita previa en este negocio para mostrar servicio y fecha reales
$stmt_cita = $conexion->prepare("
    SELECT s.nombre_servicio, c.fecha 
    FROM citas c 
    INNER JOIN servicios s ON c.servicio_id = s.id 
    WHERE c.cliente_id = ? AND c.negocio_id = ? AND c.estado != 'cancelada'
    ORDER BY c.fecha DESC LIMIT 1
");
$stmt_cita->bind_param("ii", $usuarioId, $negocioId);
$stmt_cita->execute();
$citaInfo = $stmt_cita->get_result()->fetch_assoc();

$subtituloNegocio = $citaInfo
    ? $citaInfo['nombre_servicio'] . ' · ' . date('d/m/Y', strtotime($citaInfo['fecha']))
    : 'Experiencia en el establecimiento · Xalapa';

$logoUrl = (!empty($negocioRow['foto_portada']) && $negocioRow['foto_portada'] !== 'default_negocio.png' && file_exists('uploads/negocios/' . $negocioRow['foto_portada']))
    ? 'uploads/negocios/' . $negocioRow['foto_portada']
    : '';

function guardarFoto(array $f, ?string &$error): ?string {
    if ($f['error'] !== UPLOAD_ERR_OK) { $error = 'No se pudo subir una de las fotos.'; return null; }
    if ($f['size'] > MAX_BYTES_FOTO)   { $error = 'Cada foto debe pesar máximo 3 MB.'; return null; }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($f['tmp_name']);
    $ext   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || !getimagesize($f['tmp_name'])) { $error = 'Solo se aceptan fotos JPG, PNG o WebP.'; return null; }

    if (!is_dir(CARPETA_FOTOS) && !mkdir(CARPETA_FOTOS, 0755, true)) { $error = 'No se pudo guardar la foto.'; return null; }
    $nombre = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], CARPETA_FOTOS . '/' . $nombre)) { $error = 'No se pudo guardar la foto.'; return null; }
    return $nombre;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$errores       = [];
$estrellas     = 0;
$tags          = [];
$comentario    = '';
$mostrarNombre = true;

// 3. Procesar envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errores[] = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    }

    $estrellas     = (int) ($_POST['estrellas'] ?? 0);
    $tags          = array_values(array_intersect($ETIQUETAS, (array) ($_POST['etiquetas'] ?? [])));
    $comentario    = trim((string) ($_POST['comentario'] ?? ''));
    $mostrarNombre = !empty($_POST['mostrar_nombre']);

    if ($estrellas < 1 || $estrellas > 5) {
        $errores[] = 'Elige una calificación de 1 a 5 estrellas.';
    }
    $largo = mb_strlen($comentario, 'UTF-8');
    if ($largo < MIN_CARACTERES) {
        $errores[] = 'Tu reseña debe tener al menos ' . MIN_CARACTERES . ' caracteres.';
    } elseif ($largo > MAX_CARACTERES) {
        $errores[] = 'Tu reseña no puede pasar de ' . MAX_CARACTERES . ' caracteres.';
    }

    $fotosGuardadas = [];
    if (!$errores && !empty($_FILES['fotos']['name'][0])) {
        $total = count($_FILES['fotos']['name']);
        if ($total > MAX_FOTOS) {
            $errores[] = 'Puedes subir máximo ' . MAX_FOTOS . ' fotos.';
        } else {
            for ($i = 0; $i < $total; $i++) {
                $archivo = [
                    'name'     => $_FILES['fotos']['name'][$i],
                    'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                    'error'    => $_FILES['fotos']['error'][$i],
                    'size'     => $_FILES['fotos']['size'][$i],
                ];
                $err = null;
                $nombre = guardarFoto($archivo, $err);
                if ($nombre) { $fotosGuardadas[] = $nombre; } else { $errores[] = $err; break; }
            }
        }
    }

    if (!$errores) {
        $conexion->begin_transaction();
        try {
            $etiquetasStr = implode(',', $tags);
            $mostrarInt   = $mostrarNombre ? 1 : 0;

            $st = $conexion->prepare("
                INSERT INTO resenas (negocio_id, usuario_id, estrellas, etiquetas, comentario, mostrar_nombre, creada_en)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $st->bind_param("iiissi", $negocioId, $usuarioId, $estrellas, $etiquetasStr, $comentario, $mostrarInt);
            $st->execute();
            $rid = (int) $conexion->insert_id;

            if (!empty($fotosGuardadas)) {
                $sf = $conexion->prepare("INSERT INTO resena_fotos (resena_id, archivo) VALUES (?, ?)");
                foreach ($fotosGuardadas as $archivo) {
                    $sf->bind_param("is", $rid, $archivo);
                    $sf->execute();
                }
            }

            $conexion->commit();
            unset($_SESSION['csrf']);
            header('Location: negocio.php?id=' . $negocioId . '&resena=ok#resenas');
            exit();
        } catch (Throwable $ex) {
            $conexion->rollback();
            $errores[] = 'No pudimos guardar tu reseña. Es posible que ya hayas calificado este negocio anteriormente.';
        }
    }
}

$iniciales = mb_strtoupper(mb_substr($negocioRow['nombre_negocio'], 0, 2, 'UTF-8'), 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Escribir reseña · <?= e($negocioRow['nombre_negocio']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0}
a{color:inherit;text-decoration:none}
.pg{font-family:'DM Sans',sans-serif;color:#2D3748;background:#F4F2FD;min-height:100vh;padding:16px}
.card{max-width:560px;margin:0 auto;background:#fff;border:1px solid #E4E0F2;border-radius:20px;padding:24px}
.ic{width:20px;height:20px;fill:none;stroke:#9048DB;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.back{width:44px;height:44px;border-radius:50%;border:1px solid #DDD8F0;display:flex;align-items:center;justify-content:center;background:#fff}
.back .ic{stroke:#2D3748}
.top h1{margin:0;font-size:20px;font-weight:700}
.biz{display:flex;align-items:center;gap:14px;padding:14px;background:#F7F8FC;border:1px solid #E4E0F2;border-radius:14px}
.logo{width:52px;height:52px;border-radius:50%;background:#9048DB;color:#fff;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;flex:none;overflow:hidden}
.logo img{width:100%;height:100%;object-fit:cover}
.biz b{font-size:16px}
.sub{font-size:13px;color:#64748B;margin-top:2px}
h2{margin:24px 0 4px;font-size:16px;font-weight:700}
.lead{font-size:14px;color:#64748B;margin:0 0 12px}
.rate{position:relative;display:flex;flex-direction:row-reverse;justify-content:center;padding-bottom:30px}
.rate input{position:absolute;opacity:0;pointer-events:none}
.rate label{width:56px;height:56px;display:flex;align-items:center;justify-content:center;font-size:44px;line-height:1;color:#D5D0E6;cursor:pointer}
.rate input:checked ~ label,.rate label:hover,.rate label:hover ~ label{color:#F5A623}
.rate input:focus-visible + label{outline:2px solid #9048DB;border-radius:10px}
.rate .t{position:absolute;left:0;right:0;bottom:0;text-align:center;font-size:15px;font-weight:700;color:#6D28B8;display:none}
#s1:checked ~ .t1,#s2:checked ~ .t2,#s3:checked ~ .t3,#s4:checked ~ .t4,#s5:checked ~ .t5{display:block}
.vh{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.tags{display:flex;flex-wrap:wrap;gap:8px}
.tg{position:relative}
.tg input{position:absolute;opacity:0;pointer-events:none}
.tg span{display:flex;align-items:center;height:40px;padding:0 14px;border:1px solid #DDD8F0;border-radius:20px;background:#fff;font-size:14px;font-weight:500;cursor:pointer}
.tg input:checked + span{background:#F0E6FC;border-color:#9048DB;color:#6D28B8;font-weight:700}
.tg input:focus-visible + span{outline:2px solid #9048DB}
textarea{display:block;width:100%;min-height:150px;padding:14px;border:1px solid #E2E0F0;border-radius:12px;background:#F7F8FC;font:400 15px/1.5 'DM Sans',sans-serif;color:#2D3748;resize:vertical}
textarea:focus{outline:0;border-color:#9048DB;background:#fff}
.cnt{display:flex;justify-content:space-between;font-size:13px;color:#64748B;margin-top:6px}
.cnt .bad{color:#B91C1C}
.up{display:flex;align-items:center;justify-content:center;gap:10px;height:72px;border:1.5px dashed #C9BFEA;border-radius:12px;background:#FAF8FE;font-size:14px;font-weight:500;color:#6D28B8;cursor:pointer;text-align:center;padding:0 12px}
.chk{display:flex;align-items:center;gap:10px;min-height:44px;margin-top:16px;font-size:14px;cursor:pointer}
.chk input{width:18px;height:18px;margin:0;accent-color:#9048DB}
.btns{display:flex;flex-direction:column;gap:10px;margin-top:20px}
.go{display:flex;align-items:center;justify-content:center;height:52px;border:0;border-radius:14px;background:#9048DB;color:#fff;font:700 16px 'DM Sans',sans-serif;cursor:pointer}
.go:disabled{opacity:.6;cursor:wait}
.ghost{display:flex;align-items:center;justify-content:center;height:48px;border-radius:12px;color:#64748B;font-size:15px;font-weight:700}
.alert{margin-bottom:16px;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.5}
.alert.err{background:#FEE2E2;color:#991B1B}
.alert ul{margin:0;padding-left:18px}
@media (max-width:480px){.pg{padding:0}.card{border-radius:0;border:0;padding:20px}}
</style>
</head>
<body>
<div class="pg">
<form class="card" method="post" action="resena.php?id=<?= $negocioId ?>" enctype="multipart/form-data" id="f" novalidate>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <input type="hidden" name="negocio_id" value="<?= $negocioId ?>">

  <div class="top">
    <a href="negocio.php?id=<?= $negocioId ?>" class="back" aria-label="Volver">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </a>
    <h1>Escribir reseña</h1>
  </div>

  <?php if ($errores): ?>
    <div class="alert err" role="alert">
      <ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="biz">
    <div class="logo">
      <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt=""><?php else: ?><?= e($iniciales) ?><?php endif; ?>
    </div>
    <div>
      <b><?= e($negocioRow['nombre_negocio']) ?></b>
      <div class="sub"><?= e($subtituloNegocio) ?></div>
    </div>
  </div>

  <h2>¿Cómo fue tu experiencia?</h2>
  <p class="lead">Toca una estrella para calificar.</p>
  <div class="rate" role="radiogroup" aria-label="Calificación">
    <?php for ($i = 5; $i >= 1; $i--): ?>
      <input type="radio" name="estrellas" id="s<?= $i ?>" value="<?= $i ?>" <?= $estrellas === $i ? 'checked' : '' ?>>
      <label for="s<?= $i ?>" title="<?= $i ?> estrella<?= $i > 1 ? 's' : '' ?>">★<span class="vh"><?= $i ?> estrella<?= $i > 1 ? 's' : '' ?></span></label>
    <?php endfor; ?>
    <?php foreach ($TEXTOS_ESTRELLAS as $n => $t): ?><span class="t t<?= $n ?>"><?= e($t) ?></span><?php endforeach; ?>
  </div>

  <h2>¿Qué te gustó?</h2>
  <p class="lead">Opcional. Puedes elegir varias.</p>
  <div class="tags">
    <?php foreach ($ETIQUETAS as $t): ?>
      <label class="tg"><input type="checkbox" name="etiquetas[]" value="<?= e($t) ?>" <?= in_array($t, $tags, true) ? 'checked' : '' ?>><span><?= e($t) ?></span></label>
    <?php endforeach; ?>
  </div>

  <h2>Cuéntanos más</h2>
  <p class="lead">Tu opinión ayuda a otras personas a elegir.</p>
  <textarea name="comentario" id="coment" maxlength="<?= MAX_CARACTERES ?>" placeholder="Describe tu experiencia: el servicio, la atención, el lugar…" aria-label="Tu reseña"><?= e($comentario) ?></textarea>
  <div class="cnt"><span id="min">Mínimo <?= MIN_CARACTERES ?> caracteres</span><span id="cont">0 / <?= MAX_CARACTERES ?></span></div>

  <h2>Fotos</h2>
  <p class="lead">Opcional. Máximo <?= MAX_FOTOS ?> fotos de hasta 3 MB.</p>
  <label class="up">
    <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
    <span id="upTxt">Agregar fotos</span>
    <input type="file" name="fotos[]" id="fotos" accept="image/jpeg,image/png,image/webp" multiple class="vh">
  </label>

  <label class="chk"><input type="checkbox" name="mostrar_nombre" value="1" <?= $mostrarNombre ? 'checked' : '' ?>>Publicar con mi nombre</label>

  <div class="btns">
    <button type="submit" class="go" id="enviar">Publicar reseña</button>
    <a href="negocio.php?id=<?= $negocioId ?>" class="ghost">Cancelar</a>
  </div>
</form>
</div>

<script>
(function () {
  var MIN = <?= MIN_CARACTERES ?>, MAX = <?= MAX_CARACTERES ?>, FOTOS = <?= MAX_FOTOS ?>;
  var c = document.getElementById('coment'), cont = document.getElementById('cont'), min = document.getElementById('min');

  function contar() {
    var n = c.value.length;
    cont.textContent = n + ' / ' + MAX;
    min.className = (n > 0 && n < MIN) ? 'bad' : '';
    min.textContent = n >= MIN ? 'Listo para publicar' : 'Mínimo ' + MIN + ' caracteres';
  }
  c.addEventListener('input', contar); contar();

  var inp = document.getElementById('fotos'), txt = document.getElementById('upTxt');
  inp.addEventListener('change', function () {
    var n = inp.files.length;
    if (n > FOTOS) { alert('Máximo ' + FOTOS + ' fotos.'); inp.value = ''; n = 0; }
    txt.textContent = n ? n + ' foto' + (n > 1 ? 's' : '') + ' seleccionada' + (n > 1 ? 's' : '') : 'Agregar fotos';
  });

  document.getElementById('f').addEventListener('submit', function () {
    var b = document.getElementById('enviar'); b.disabled = true; b.textContent = 'Publicando…';
  });
})();
</script>
</body>
</html>