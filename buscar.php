<?php
session_start();
require_once 'conexion/bd.php';

function e($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dinero($n): string {
    return '$' . number_format((float) $n, 0, '.', ',');
}

function soloNumero($s, int $def): int {
    $n = preg_replace('/\D/', '', (string) $s);
    return $n === '' ? $def : (int) $n;
}

// 1. Leer filtros sencillos de la URL
$q      = trim((string) ($_GET['q'] ?? ''));
$ciudad = trim((string) ($_GET['ciudad'] ?? 'Xalapa, Veracruz'));
$pmin   = isset($_GET['pmin']) && $_GET['pmin'] !== '' ? soloNumero($_GET['pmin'], 0) : null;
$pmax   = isset($_GET['pmax']) && $_GET['pmax'] !== '' ? soloNumero($_GET['pmax'], 2000) : null;
$orden  = in_array($_GET['orden'] ?? '', ['recientes', 'precio'], true) ? $_GET['orden'] : 'recientes';

// 2. Consultar todos los negocios y el precio mínimo de sus servicios
$sql = "
    SELECT 
        n.id, 
        n.nombre_negocio AS nombre, 
        n.descripcion, 
        n.direccion, 
        n.horario, 
        n.foto_portada AS foto,
        COALESCE(MIN(s.precio), 0) AS desde,
        GROUP_CONCAT(s.nombre_servicio SEPARATOR ' ') AS lista_servicios
    FROM negocios n
    LEFT JOIN servicios s ON n.id = s.negocio_id
    GROUP BY n.id
";
$res = $conexion->query($sql);

$todos = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $todos[] = $row;
    }
}

// 3. Aplicar filtros en PHP de forma limpia
$resultados = array_values(array_filter($todos, function ($n) use ($q, $pmin, $pmax) {
    if ($q !== '') {
        $texto_busqueda = $n['nombre'] . ' ' . $n['descripcion'] . ' ' . $n['direccion'] . ' ' . $n['lista_servicios'];
        if (mb_stripos($texto_busqueda, $q) === false) {
            return false;
        }
    }
    if ($pmin !== null && $n['desde'] < $pmin) return false;
    if ($pmax !== null && $n['desde'] > $pmax) return false;
    return true;
}));

// Ordenar
usort($resultados, function ($a, $b) use ($orden) {
    if ($orden === 'precio') {
        return $a['desde'] <=> $b['desde'];
    }
    return $b['id'] <=> $a['id'];
});

$total = count($resultados);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buscar negocios · Bienestar &amp; Belleza</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0}
a{color:inherit;text-decoration:none}
.pg{font-family:'DM Sans',sans-serif;color:#2D3748;background:#F4F2FD;min-height:100vh}
.ic{width:20px;height:20px;fill:none;stroke:#9048DB;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.tg{position:absolute;opacity:0;pointer-events:none}
.top{background:#fff;border-bottom:1px solid #E4E0F2;padding:16px 20px}
.bar{max-width:1120px;margin:0 auto;display:flex;flex-direction:column;gap:10px;align-items:center}
.brand-mini{font-weight:700;font-size:16px;color:#9048DB;margin-right:10px;white-space:nowrap}
.fld{display:flex;align-items:center;gap:10px;height:48px;padding:0 14px;background:#F7F8FC;border:1px solid #E2E0F0;border-radius:12px;flex:1;width:100%}
.fld input{flex:1;min-width:0;border:0;background:transparent;font:400 15px 'DM Sans',sans-serif;color:#2D3748;outline:0}
.fld:focus-within{border-color:#9048DB}
.go{display:flex;align-items:center;justify-content:center;height:48px;padding:0 28px;border:0;border-radius:12px;background:#9048DB;color:#fff;font:700 15px 'DM Sans',sans-serif;cursor:pointer;width:100%}
.wrap{max-width:1120px;margin:0 auto;padding:24px 20px 40px}
.chips{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px}
.chip{display:flex;align-items:center;gap:6px;height:40px;padding:0 14px;border:1px solid #DDD8F0;border-radius:20px;background:#fff;font-size:14px;font-weight:500;white-space:nowrap;cursor:pointer}
.fbtn{display:none;background:#9048DB;border-color:#9048DB;color:#fff;font-weight:700}
.fbtn .ic{stroke:#fff}
.main{display:flex;flex-direction:column;gap:20px;margin-top:16px}
.ov{display:none}
.panel{background:#fff;border:1px solid #E4E0F2;border-radius:16px;padding:20px}
.ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.ph h2{margin:0;font-size:18px;font-weight:700}
.lnk{font-size:14px;font-weight:700;color:#9048DB;cursor:pointer}
.sec{padding:16px 0;border-bottom:1px solid #E4E0F2}
.sec h3{margin:0 0 12px;font-size:14px;font-weight:700}
input[type=range]{accent-color:#9048DB;width:100%;margin:6px 0}
.two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.num{height:44px;padding:0 12px;border:1px solid #E2E0F0;border-radius:10px;background:#F7F8FC;font:400 14px 'DM Sans',sans-serif;color:#2D3748;width:100%}
.hint{font-size:13px;color:#64748B;margin-top:4px;display:flex;justify-content:space-between}
.pf{display:flex;gap:10px;padding-top:16px}
.pf .go{flex:1;padding:0}
.ghost{display:flex;align-items:center;justify-content:center;height:48px;padding:0 20px;border:1.5px solid #9048DB;border-radius:12px;color:#9048DB;font-size:15px;font-weight:700;background:#fff}
.res{flex:1;min-width:0}
.rh{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.rh h1{margin:0;font-size:20px;font-weight:700}
.sort{height:40px;padding:0 12px;border:1px solid #DDD8F0;border-radius:10px;background:#fff;font:500 14px 'DM Sans',sans-serif;color:#2D3748}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.card{display:block;background:#fff;border:1px solid #E4E0F2;border-radius:16px;overflow:hidden}
.card:hover{border-color:#9048DB}
.ph-img{height:140px;background:#E6E1F8 center/cover no-repeat;display:flex;align-items:center;justify-content:center;font-size:13px;color:#5B4B8A}
.cb{padding:14px 16px 16px}
.cb b{font-size:16px}
.cs{font-size:13px;color:#64748B;margin-top:4px}
.cm{display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:14px}
.ok{font-size:12px;font-weight:500;padding:3px 8px;border-radius:10px;background:#DCFCE7;color:#166534}
.vacio{background:#fff;border:1px solid #E4E0F2;border-radius:16px;padding:40px 20px;text-align:center;color:#64748B}
.vacio b{display:block;font-size:17px;color:#2D3748;margin-bottom:6px}
.vacio a{display:inline-block;margin-top:14px;color:#9048DB;font-weight:700}
.zonas-mini{display:flex;flex-wrap:wrap;gap:8px}
.zonas-mini a{padding:6px 12px;border-radius:16px;background:#F7F8FC;border:1px solid #E2E0F0;font-size:13px;color:#4B5563}
.zonas-mini a:hover{border-color:#9048DB;color:#9048DB}
@media (max-width:1023px){
.fbtn{display:flex}
.panel{position:fixed;left:0;right:0;bottom:0;max-height:88vh;overflow:auto;border-radius:20px 20px 0 0;z-index:20;transform:translateY(105%);visibility:hidden;transition:transform .25s,visibility .25s}
.tg:checked ~ .wrap .panel{transform:none;visibility:visible}
.tg:checked ~ .wrap .ov{display:block;position:fixed;inset:0;background:rgba(45,55,72,.45);z-index:10}
.pf{position:sticky;bottom:-20px;background:#fff;padding:16px 0 4px}
}
@media (min-width:640px){
.bar{flex-direction:row}
.bar .loc{flex:0 0 26%}
.go{width:auto}
}
@media (min-width:1024px){
.main{flex-direction:row;align-items:flex-start;gap:32px}
.panel{flex:0 0 290px;position:sticky;top:16px}
}
</style>
</head>
<body>
<form method="get" action="buscar.php" class="pg" id="f">
<input type="checkbox" id="fl" class="tg" aria-label="Mostrar filtros">

<div class="top">
  <div class="bar">
    <a href="index.php" class="brand-mini">← Inicio</a>
    <label class="fld">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="¿Qué servicio, negocio o zona buscas?" aria-label="Servicio">
    </label>
    <label class="fld loc">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.3-6-10a6 6 0 0112 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
      <input type="text" name="ciudad" value="<?= e($ciudad) ?>" aria-label="Ciudad" readonly>
    </label>
    <button type="submit" class="go">Buscar</button>
  </div>
</div>

<div class="wrap">
  <div class="chips">
    <label for="fl" class="chip fbtn">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
      Filtros
    </label>
  </div>

  <div class="main">
    <label for="fl" class="ov" aria-label="Cerrar filtros"></label>

    <aside class="panel" aria-label="Filtros de búsqueda">
      <div class="ph"><h2>Filtros</h2><a href="buscar.php" class="lnk">Limpiar todo</a></div>

      <div class="sec">
        <h3>Búsquedas rápidas</h3>
        <div class="zonas-mini">
          <a href="buscar.php?q=Masaje">Masajes</a>
          <a href="buscar.php?q=Corte">Corte</a>
          <a href="buscar.php?q=Uñas">Uñas</a>
          <a href="buscar.php?q=Barba">Barba</a>
          <a href="buscar.php?q=Centro">Centro</a>
          <a href="buscar.php?q=Ánimas">Las Ánimas</a>
        </div>
      </div>

      <div class="sec" style="border-bottom:0">
        <h3>Rango de precio</h3>
        <div class="two">
          <input class="num" type="text" inputmode="numeric" name="pmin" value="<?= $pmin !== null ? e(dinero($pmin)) : '' ?>" placeholder="$0" aria-label="Precio mínimo">
          <input class="num" type="text" inputmode="numeric" name="pmax" id="pmax" value="<?= $pmax !== null ? e(dinero($pmax)) : '' ?>" placeholder="$2,000+" aria-label="Precio máximo">
        </div>
        <input type="range" id="prange" min="0" max="2000" step="50" value="<?= (int) ($pmax ?? 2000) ?>" aria-label="Precio máximo">
        <div class="hint"><span>$0</span><span>$2,000+</span></div>
      </div>

      <div class="pf">
        <a href="buscar.php" class="ghost">Limpiar</a>
        <button type="submit" class="go">Ver <?= $total ?> resultado<?= $total === 1 ? '' : 's' ?></button>
      </div>
    </aside>

    <section class="res">
      <div class="rh">
        <h1><?= $total ?> resultado<?= $total === 1 ? '' : 's' ?> en Xalapa</h1>
        <select class="sort" name="orden" aria-label="Ordenar por" onchange="this.form.submit()">
          <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
          <option value="precio" <?= $orden === 'precio' ? 'selected' : '' ?>>Menor precio</option>
        </select>
      </div>

      <?php if ($resultados): ?>
        <div class="grid">
          <?php foreach ($resultados as $n): 
              $has_foto = !empty($n['foto']) && $n['foto'] !== 'default_negocio.png' && file_exists('uploads/negocios/' . $n['foto']);
              $foto_url = $has_foto ? 'uploads/negocios/' . $n['foto'] : '';
          ?>
            <a class="card" href="negocio.php?id=<?= (int) $n['id'] ?>">
              <div class="ph-img"<?= $has_foto ? ' style="background-image:url(\'' . e($foto_url) . '\')"' : '' ?>>
                <?= $has_foto ? '' : 'Sin Imagen' ?>
              </div>
              <div class="cb">
                <b><?= e($n['nombre']) ?></b>
                <div class="cs">📍 <?= e($n['direccion']) ?></div>
                <div class="cm">
                  <span style="font-size:12px; color:#64748B;">⏰ <?= e($n['horario'] ?: 'Consultar horario') ?></span>
                  <span class="ok">Activo</span>
                </div>
                <div class="cs" style="margin-top:10px; font-weight:700; color:#9048DB;">
                  <?= $n['desde'] > 0 ? 'Desde ' . dinero($n['desde']) : 'Ver servicios' ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="vacio">
          <b>No encontramos negocios con ese criterio</b>
          Intenta buscar otra palabra o limpia los filtros de precio.
          <br><a href="buscar.php">Ver todos los negocios</a>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
</form>

<script>
var pr = document.getElementById('prange'), pm = document.getElementById('pmax');
pr.addEventListener('input', function () {
  pm.value = pr.value >= 2000 ? '' : '$' + Number(pr.value).toLocaleString('en-US');
});
</script>
</body>
</html>