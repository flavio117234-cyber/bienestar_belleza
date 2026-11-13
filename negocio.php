<?php
session_start();
require_once 'conexion/bd.php';

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dinero($n): string {
    return '$' . number_format((float) $n, 0, '.', ',');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// 1. Consultar datos reales del negocio y correo del dueño (incluyendo usuario_id)
$stmt = $conexion->prepare("
    SELECT n.id, n.usuario_id, n.nombre_negocio, n.descripcion, n.direccion, n.telefono, n.horario, n.foto_portada, u.correo 
    FROM negocios n
    INNER JOIN usuarios u ON n.usuario_id = u.id
    WHERE n.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(404);
    exit('Negocio no encontrado.');
}

$negocio = $res->fetch_assoc();

// Verificar si el usuario actual es el dueño de este negocio
$es_dueno = (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === (int)$negocio['usuario_id']);

// 2. Consultar servicios reales del negocio
$stmt_serv = $conexion->prepare("SELECT id, nombre_servicio, descripcion, precio, duracion_minutos FROM servicios WHERE negocio_id = ? ORDER BY precio ASC");
$stmt_serv->bind_param("i", $id);
$stmt_serv->execute();
$res_serv = $stmt_serv->get_result();

$servicios = [];
while ($row = $res_serv->fetch_assoc()) {
    $servicios[] = $row;
}

// 3. Preparar rutas y enlaces
$has_portada = !empty($negocio['foto_portada']) && $negocio['foto_portada'] !== 'default_negocio.png' && file_exists('uploads/negocios/' . $negocio['foto_portada']);
$portada_url = $has_portada ? 'uploads/negocios/' . $negocio['foto_portada'] : '';

$iniciales = mb_strtoupper(mb_substr($negocio['nombre_negocio'], 0, 2, 'UTF-8'), 'UTF-8');
$tel_limpio = preg_replace('/\D/', '', $negocio['telefono']);
$wa_num = (strlen($tel_limpio) === 10) ? '52' . $tel_limpio : $tel_limpio;
$wa_link = 'https://wa.me/' . $wa_num;

$direccion_mapa = $negocio['direccion'] . ', Xalapa, Veracruz';
$mapaQ = rawurlencode($direccion_mapa);
$mapaLink = 'https://www.google.com/maps/search/?api=1&query=' . $mapaQ;
$mapaEmbed = 'https://maps.google.com/maps?q=' . $mapaQ . '&output=embed';
$reservar = 'reservar.php?id=' . (int) $negocio['id'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($negocio['nombre_negocio']) ?> · Bienestar &amp; Belleza</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0}
a{color:inherit;text-decoration:none}
.pg{font-family:'DM Sans',sans-serif;color:#2D3748;background:#F4F2FD;min-height:100vh}
.ic{width:20px;height:20px;fill:none;stroke:#9048DB;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.cover{height:200px;background:#E6E1F8;display:flex;align-items:center;justify-content:center;position:relative;font-size:13px;color:#5B4B8A;background-size:cover;background-position:center}
.ib{position:absolute;top:16px;width:44px;height:44px;border-radius:50%;background:#fff;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.ib .ic{stroke:#2D3748}
.owner-pill{position:absolute;top:16px;right:16px;background:#fff;color:#6D28B8;font-size:13px;font-weight:700;padding:8px 14px;border-radius:20px;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.wrap{max-width:1120px;margin:0 auto;padding:0 20px}
.head{margin-top:-36px;position:relative;display:flex;flex-direction:column}
.logo{width:72px;height:72px;border-radius:50%;background:#9048DB;border:4px solid #fff;color:#fff;font-weight:700;font-size:18px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.hinfo{flex:1}
h1{margin:12px 0 2px;font-size:24px;line-height:1.2;font-weight:700}
h2{margin:0 0 12px;font-size:18px;font-weight:700}
.sub{font-size:14px;color:#64748B}
.meta{display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;font-size:14px}
.badge{font-size:13px;font-weight:500;padding:4px 10px;border-radius:12px}
.badge.open{background:#DCFCE7;color:#166534}
.actions{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:20px}
.btn{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 0;border:1px solid #DDD8F0;border-radius:12px;font:500 12px 'DM Sans',sans-serif;color:inherit;min-height:44px;background:#fff;cursor:pointer}
.btn:hover{border-color:#9048DB}
.tabs{display:flex;gap:8px;overflow-x:auto;border-bottom:1px solid #E4E0F2;margin-top:20px}
.tabs a{padding:12px 14px;font-size:14px;font-weight:500;color:#64748B;white-space:nowrap;border-bottom:3px solid transparent;transition:color .2s,border-color .2s}
.tabs a:hover{color:#9048DB}
.tabs a.on{font-weight:700;color:#9048DB;border-bottom-color:#9048DB}
.cols{display:flex;flex-direction:column;gap:24px;padding:24px 0 32px}
.aside{display:flex;flex-direction:column;gap:24px}
.sec-box{border-radius:14px;transition:box-shadow .35s,background-color .35s;padding:4px}
.sec-box.highlight{background-color:#F0E6FC;box-shadow:0 0 0 8px #F0E6FC}
.svcs{display:grid;grid-template-columns:1fr;column-gap:32px}
.svc{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid #E4E0F2}
.svc b{font-size:15px}
.svc span{display:block;font-size:13px;color:#64748B;margin-top:2px}
.hrs{background:#fff;border:1px solid #E4E0F2;border-radius:12px;padding:16px;font-size:14px;line-height:1.5}
.map{height:160px;border-radius:12px;background:#ECE8FA;overflow:hidden}
.map iframe{width:100%;height:100%;border:0}
.addr{margin-top:12px;font-size:15px;line-height:1.4}
.outl{display:flex;align-items:center;justify-content:center;min-height:48px;margin-top:14px;border:1.5px solid #9048DB;border-radius:12px;font-size:15px;font-weight:700;color:#9048DB;background:#fff}
.crow{display:flex;align-items:center;gap:14px;min-height:52px;border-bottom:1px solid #E4E0F2;font-size:15px;word-break:break-word}
.crow:last-child{border-bottom:0}
.cta{position:sticky;bottom:0;background:#fff;border-top:1px solid #E4E0F2;padding:16px 20px}
.cta-btn{display:flex;align-items:center;justify-content:center;height:52px;border-radius:14px;background:#9048DB;color:#fff;font-size:16px;font-weight:700}
.cta-btn.owner-btn{background:#2D3748}
.cta-d{display:none}
@media (min-width:640px){
.cover{height:260px}
.aside{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.aside .full{grid-column:1/-1}
.svcs{grid-template-columns:1fr 1fr}
}
@media (min-width:1024px){
.cover{height:320px}
.head{flex-direction:row;align-items:flex-end;justify-content:space-between;gap:32px}
.actions{flex:0 0 40%;margin-top:0}
.btn{font-size:13px}
h1{font-size:32px}
.cols{display:grid;grid-template-columns:1fr 380px;gap:40px;padding-bottom:56px;align-items:start}
.aside{display:flex;flex-direction:column;position:sticky;top:16px}
.cta{display:none}
.cta-d{display:flex}
.map{height:200px}
}
</style>
</head>
<body>
<div class="pg">

  <div class="cover"<?= $has_portada ? ' style="background-image:url(\'' . e($portada_url) . '\')"' : '' ?>>
    <?php if (!$has_portada): ?><span><?= e($negocio['nombre_negocio']) ?></span><?php endif; ?>
    <a href="index.php" class="ib" style="left:16px" aria-label="Volver al inicio">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </a>
    <?php if ($es_dueno): ?>
      <span class="owner-pill">Vista previa de tu negocio</span>
    <?php endif; ?>
  </div>

  <div class="wrap">
    <div class="head">
      <div class="hinfo">
        <div class="logo"><?= e($iniciales) ?></div>
        <h1><?= e($negocio['nombre_negocio']) ?></h1>
        <div class="sub">Bienestar &amp; Belleza · <?= e($negocio['direccion']) ?>, Xalapa</div>
        <div class="meta">
          <span class="badge open">Disponible en Xalapa</span>
        </div>
      </div>

      <div class="actions">
        <a href="tel:<?= e($tel_limpio) ?>" class="btn">
          <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A15 15 0 013 6a2 2 0 012-2z"/></svg>Llamar
        </a>
        <a href="<?= e($wa_link) ?>" class="btn" target="_blank" rel="noopener">
          <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1112 20a8 8 0 01-4-1l-4 1z"/></svg>WhatsApp
        </a>
        <a href="<?= e($mapaLink) ?>" class="btn" target="_blank" rel="noopener">
          <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.3-6-10a6 6 0 0112 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>Cómo llegar
        </a>
        <button type="button" class="btn" id="compartir">
          <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M8 8l4-4 4 4M5 14v5h14v-5"/></svg>Compartir
        </button>
      </div>
    </div>

    <nav class="tabs" aria-label="Secciones">
      <a href="#servicios" class="on">Servicios</a>
      <a href="#horarios">Horarios</a>
      <a href="#ubicacion">Ubicación</a>
      <a href="#contacto">Contacto</a>
    </nav>

    <div class="cols">

      <div>
        <?php if (!empty($negocio['descripcion'])): ?>
          <section style="margin-bottom: 28px;">
            <h2>Acerca del negocio</h2>
            <p style="color:#4B5563; line-height:1.6; margin:0;"><?= e($negocio['descripcion']) ?></p>
          </section>
        <?php endif; ?>

        <section id="servicios" class="sec-box">
          <h2>Servicios</h2>
          <?php if (empty($servicios)): ?>
            <div style="color:#64748B; background:#fff; padding:20px; border-radius:12px; border:1px solid #E4E0F2;">
              Este negocio aún no ha publicado su lista de servicios.
              <?php if ($es_dueno): ?>
                <br><a href="profesional/servicios.php" style="display:inline-block; margin-top:10px; color:#9048DB; font-weight:700;">+ Agregar mis primeros servicios</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="svcs">
              <?php foreach ($servicios as $s): ?>
                <div class="svc">
                  <div>
                    <b><?= e($s['nombre_servicio']) ?></b>
                    <span><?= (int)$s['duracion_minutos'] ?> min<?= !empty($s['descripcion']) ? ' · ' . e($s['descripcion']) : '' ?></span>
                  </div>
                  <b><?= dinero($s['precio']) ?></b>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>

      <div class="aside">
        <?php if ($es_dueno): ?>
          <a href="profesional/panel.php" class="cta-btn owner-btn cta-d full">⚙️ Administrar mi negocio</a>
        <?php else: ?>
          <a href="<?= e($reservar) ?>" class="cta-btn cta-d full">Reservar cita</a>
        <?php endif; ?>

        <section id="horarios" class="sec-box">
          <h2>Horario de atención</h2>
          <div class="hrs">
            ⏰ <strong><?= e($negocio['horario'] ?: 'Consultar directamente con el negocio') ?></strong>
          </div>
        </section>

        <section id="ubicacion" class="sec-box">
          <h2>Ubicación</h2>
          <div class="map">
            <iframe src="<?= e($mapaEmbed) ?>" loading="lazy" title="Mapa de <?= e($negocio['nombre_negocio']) ?>"></iframe>
          </div>
          <div class="addr">
            <?= e($negocio['direccion']) ?><br>
            Xalapa, Veracruz
          </div>
          <a href="<?= e($mapaLink) ?>" class="outl" target="_blank" rel="noopener">Abrir en mapas</a>
        </section>

        <section id="contacto" class="sec-box full">
          <h2>Contacto</h2>
          <a href="tel:<?= e($tel_limpio) ?>" class="crow">
            <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A15 15 0 013 6a2 2 0 012-2z"/></svg>
            <?= e($negocio['telefono']) ?>
          </a>
          <a href="<?= e($wa_link) ?>" class="crow" target="_blank" rel="noopener">
            <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1112 20a8 8 0 01-4-1l-4 1z"/></svg>
            WhatsApp · <?= e($negocio['telefono']) ?>
          </a>
          <a href="mailto:<?= e($negocio['correo']) ?>" class="crow">
            <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
            <?= e($negocio['correo']) ?>
          </a>
        </section>
      </div>
    </div>
  </div>

  <div class="cta">
    <?php if ($es_dueno): ?>
      <a href="profesional/panel.php" class="cta-btn owner-btn">⚙️ Administrar mi negocio</a>
    <?php else: ?>
      <a href="<?= e($reservar) ?>" class="cta-btn">Reservar cita</a>
    <?php endif; ?>
  </div>
</div>

<script>
// Compartir
document.getElementById('compartir').addEventListener('click', async function () {
  const data = { title: <?= json_encode($negocio['nombre_negocio']) ?>, url: location.href };
  try {
    if (navigator.share) { await navigator.share(data); }
    else { await navigator.clipboard.writeText(location.href); alert('Enlace copiado al portapapeles'); }
  } catch (e) {}
});

// Pestañas interactivas: marcan la pestaña activa, hacen scroll y resaltan el bloque
const tabs = document.querySelectorAll('.tabs a');
tabs.forEach(function (tab) {
  tab.addEventListener('click', function (e) {
    e.preventDefault();
    tabs.forEach(t => t.classList.remove('on'));
    this.classList.add('on');

    const targetId = this.getAttribute('href').substring(1);
    const targetEl = document.getElementById(targetId);
    if (targetEl) {
      targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      targetEl.classList.add('highlight');
      setTimeout(() => targetEl.classList.remove('highlight'), 1200);
    }
  });
});
</script>
</body>
</html>