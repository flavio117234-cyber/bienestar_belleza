<?php
/**
 * index.php — Directorio principal de negocios en Xalapa
 * Integrado con BD MySQL (bd_bienestar_belleza) y Sesiones de Usuario.
 */

session_start();
require_once 'conexion/bd.php';

date_default_timezone_set('America/Mexico_City');

/* ------------------------------------------------------------------
 * 1. CONFIGURACIÓN GENERAL
 * ------------------------------------------------------------------ */
const NOMBRE_SITIO = 'Bienestar & Belleza';
const CIUDAD       = 'Xalapa';
const CIUDAD_LARGA = 'Xalapa, Veracruz';

// Categorías del sistema con íconos SVG
$CATEGORIAS = [
    'spa'        => ['Spa y masajes', '<path d="M12 21c-5 0-8-3.5-8-8 5 0 8 3 8 8zM12 21c5 0 8-3.5 8-8-5 0-8 3-8 8zM12 12c-2-2-2-6 0-9 2 3 2 7 0 9z"/>'],
    'peluqueria' => ['Peluquería',    '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><path d="M8 7.5L20 18M8 16.5L20 6"/>'],
    'unas'       => ['Uñas',          '<rect x="8" y="3" width="8" height="18" rx="4"/><path d="M8 9h8"/>'],
    'barberia'   => ['Barbería',      '<rect x="9" y="3" width="6" height="18" rx="3"/><path d="M9 8l6 3M9 13l6 3"/>'],
    'maquillaje' => ['Maquillaje',    '<rect x="9" y="11" width="6" height="10" rx="1"/><path d="M10 11V6l4-3v8"/>'],
];

$POPULARES = ['Masajes', 'Corte de cabello', 'Uñas acrílicas', 'Barba'];

/* ------------------------------------------------------------------
 * 2. AUTENTICACIÓN Y ESTADO DEL NEGOCIO
 * ------------------------------------------------------------------ */
$usuario_logueado = isset($_SESSION['usuario_id']);
$nombre_usuario   = $usuario_logueado ? $_SESSION['usuario_nombre'] : '';
$tipo_usuario     = $usuario_logueado ? $_SESSION['usuario_tipo'] : '';
$foto_perfil      = $usuario_logueado ? $_SESSION['usuario_foto'] : 'default_avatar.png';

$ruta_foto_user = "uploads/perfiles/" . $foto_perfil;
if (!file_exists($ruta_foto_user) || empty($foto_perfil)) {
    $ruta_foto_user = "https://ui-avatars.com/api/?name=" . urlencode($nombre_usuario) . "&background=9048DB&color=ffffff";
}

// Verificar si el usuario en sesión ya tiene un negocio registrado
$tiene_negocio_registrado = false;
if ($usuario_logueado && $tipo_usuario === 'profesional') {
    $stmt_check_neg = $conexion->prepare("SELECT id FROM negocios WHERE usuario_id = ?");
    $stmt_check_neg->bind_param("i", $_SESSION['usuario_id']);
    $stmt_check_neg->execute();
    $res_check_neg = $stmt_check_neg->get_result();
    if ($res_check_neg->num_rows > 0) {
        $tiene_negocio_registrado = true;
    }
}

/* ------------------------------------------------------------------
 * 3. CONSULTAS A LA BASE DE DATOS REAL
 * ------------------------------------------------------------------ */
$query_negocios = "
    SELECT 
        n.id, 
        n.nombre_negocio AS nombre, 
        n.descripcion, 
        n.direccion AS colonia, 
        n.telefono, 
        n.horario, 
        n.foto_portada AS foto,
        COALESCE(MIN(s.precio), 0) AS desde
    FROM negocios n
    LEFT JOIN servicios s ON n.id = s.negocio_id
    GROUP BY n.id
    ORDER BY n.id DESC
    LIMIT 8
";
$resultado_negocios = $conexion->query($query_negocios);

$destacados = [];
if ($resultado_negocios && $resultado_negocios->num_rows > 0) {
    while ($row = $resultado_negocios->fetch_assoc()) {
        $row['cat'] = 'spa'; 
        $row['rating'] = 4.8;
        $row['resenas'] = 12;
        $destacados[] = $row;
    }
}

/* ------------------------------------------------------------------
 * 4. FUNCIONES AUXILIARES
 * ------------------------------------------------------------------ */
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dinero($n): string
{
    return '$' . number_format((float) $n, 0, '.', ',');
}

$anio = date('Y');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(NOMBRE_SITIO) ?> · Directorio de negocios en <?= e(CIUDAD) ?></title>
<meta name="description" content="Encuentra y reserva spas, peluquerías, uñas y más en <?= e(CIUDAD) ?>. Compara horarios, precios y reseñas.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0}
a{color:inherit;text-decoration:none}
.pg{font-family:'DM Sans',sans-serif;color:#2D3748;background:#F4F2FD;min-height:100vh}
.ic{width:24px;height:24px;fill:none;stroke:#9048DB;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.sm{width:20px;height:20px}
.wrap{max-width:1120px;margin:0 auto;padding:0 20px}
.nav{background:#fff;border-bottom:1px solid #E4E0F2;position:relative}
.nav .wrap{display:flex;align-items:center;justify-content:space-between;height:68px;gap:16px}
.brand{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:700}
.mark{width:36px;height:36px;border-radius:50%;background:#9048DB;color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center}
.links{display:none;gap:28px;font-size:15px;font-weight:500;color:#64748B}
.links a:hover{color:#9048DB}
.right{display:flex;align-items:center;gap:10px}
.gh{display:none;align-items:center;height:44px;padding:0 18px;border:1.5px solid #9048DB;border-radius:12px;color:#9048DB;font-size:14px;font-weight:700;background:#fff}
.pb{display:flex;align-items:center;justify-content:center;height:44px;padding:0 18px;border-radius:12px;background:#9048DB;color:#fff;font-size:14px;font-weight:700}

/* Estilos del Menú de Usuario con Foto */
.user-menu{position:relative;display:flex;align-items:center;gap:10px;cursor:pointer;padding:4px}
.avatar-img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #9048DB}
.user-name{font-weight:600;font-size:14px;color:#2D3748}
.dropdown-menu-user{display:none;position:absolute;right:0;top:50px;min-width:190px;background:#fff;border:1px solid #E4E0F2;border-radius:14px;padding:8px;box-shadow:0 8px 24px rgba(112,72,180,.15);z-index:100}
.dropdown-menu-user a{display:block;padding:10px 14px;border-radius:8px;font-size:14px;font-weight:500;color:#2D3748}
.dropdown-menu-user a:hover{background:#F0E6FC;color:#9048DB}
.dropdown-menu-user a.logout{color:#e53e3e;border-top:1px solid #edf2f7;margin-top:4px}
.user-menu:hover .dropdown-menu-user{display:block}

.mm{position:relative}
.mm summary{list-style:none;width:44px;height:44px;border:1px solid #DDD8F0;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:pointer}
.mm summary::-webkit-details-marker{display:none}
.mm .menu{position:absolute;right:0;top:52px;min-width:200px;background:#fff;border:1px solid #E4E0F2;border-radius:14px;padding:8px;box-shadow:0 8px 24px rgba(112,72,180,.15);z-index:30}
.mm .menu a{display:block;padding:12px 14px;border-radius:10px;font-size:15px;font-weight:500}
.mm .menu a:hover{background:#F0E6FC}

.hero{background:#E6E1F8;padding:40px 0 48px}
.hero h1{margin:0;font-size:30px;line-height:1.15;font-weight:700;max-width:640px}
.hero p{margin:10px 0 24px;font-size:16px;color:#4B5563;max-width:560px}
.sbar{background:#fff;border-radius:16px;padding:10px;display:flex;flex-direction:column;gap:8px;box-shadow:0 6px 24px rgba(112,72,180,.12)}
.fld{display:flex;align-items:center;gap:10px;height:48px;padding:0 14px;background:#F7F8FC;border:1px solid #E2E0F0;border-radius:12px;flex:1}
.fld input{flex:1;min-width:0;border:0;background:transparent;font:400 15px 'DM Sans',sans-serif;color:#2D3748;outline:0}
.fld:focus-within{border-color:#9048DB}
.go{display:flex;align-items:center;justify-content:center;height:48px;padding:0 32px;border:0;border-radius:12px;background:#9048DB;color:#fff;font:700 15px 'DM Sans',sans-serif;cursor:pointer}
.pop{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;font-size:14px;align-items:center}
.pop .l{color:#4B5563}
.pop a{height:34px;display:flex;align-items:center;padding:0 12px;border-radius:17px;background:rgba(255,255,255,.7);font-weight:500}
.pop a:hover{background:#fff}
section{padding:36px 0 0}
.sh{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:16px}
.sh h2{margin:0;font-size:22px;font-weight:700}
.sh a{font-size:14px;font-weight:700;color:#9048DB}
.cats{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.cat{display:flex;flex-direction:column;align-items:center;gap:8px;padding:18px 8px;background:#fff;border:1px solid #E4E0F2;border-radius:16px;text-align:center}
.cat:hover,.card:hover{border-color:#9048DB}
.cat i{width:52px;height:52px;border-radius:50%;background:#F0E6FC;display:flex;align-items:center;justify-content:center}
.cat b{font-size:14px}
.cat span{font-size:12px;color:#64748B}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.card{display:block;background:#fff;border:1px solid #E4E0F2;border-radius:16px;overflow:hidden;position:relative}
.img{height:150px;background:#E6E1F8 center/cover no-repeat;display:flex;align-items:center;justify-content:center;font-size:13px;color:#5B4B8A}
.cb{padding:14px 16px 16px}
.cb b{font-size:16px}
.cs{font-size:13px;color:#64748B;margin-top:2px}
.cm{display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:14px}
.ok{font-size:12px;font-weight:500;background:#DCFCE7;color:#166534;padding:3px 8px;border-radius:10px}
.zonas{display:flex;flex-wrap:wrap;gap:10px}
.zonas a{display:flex;align-items:center;gap:6px;height:44px;padding:0 16px;border:1px solid #DDD8F0;border-radius:22px;background:#fff;font-size:14px;font-weight:500}
.zonas a:hover{border-color:#9048DB;color:#6D28B8}
.cta{margin-top:40px;background:#9048DB;border-radius:20px;padding:32px 24px;color:#fff;display:flex;flex-direction:column;gap:16px;align-items:flex-start}
.cta h2{margin:0 0 6px;font-size:24px;line-height:1.2}
.cta p{margin:0;font-size:15px;opacity:.92;max-width:520px}
.cta a{display:flex;align-items:center;height:48px;padding:0 24px;border-radius:12px;background:#fff;color:#6D28B8;font-size:15px;font-weight:700;white-space:nowrap}
.foot{margin-top:48px;background:#fff;border-top:1px solid #E4E0F2;padding:32px 0}
.fc{display:grid;grid-template-columns:1fr 1fr;gap:24px;font-size:14px}
.fc b{display:block;margin-bottom:10px;font-size:14px}
.fc a{display:block;padding:6px 0;color:#64748B}
.fc a:hover{color:#9048DB}
.fc .about{grid-column:1/-1;color:#64748B;line-height:1.5}
.cp{margin-top:20px;padding-top:16px;border-top:1px solid #E4E0F2;font-size:13px;color:#64748B}
@media (min-width:640px){
.cats{grid-template-columns:repeat(3,1fr)}
.sbar{flex-direction:row}
.sbar .loc{flex:0 0 28%}
.gh{display:flex}
.hero h1{font-size:38px}
.fc{grid-template-columns:2fr 1fr 1fr 1fr}
.fc .about{grid-column:auto}
.cta{flex-direction:row;align-items:center;justify-content:space-between;padding:36px 40px}
}
@media (min-width:768px){.links{display:flex}.mm{display:none}}
@media (min-width:1024px){
.cats{grid-template-columns:repeat(6,1fr)}
.hero{padding:64px 0 72px}
.hero h1{font-size:46px}
}
</style>
</head>
<body>
<div class="pg">

<header class="nav">
  <div class="wrap">
    <a href="index.php" class="brand"><span class="mark">B&amp;B</span><?= e(NOMBRE_SITIO) ?></a>

    <nav class="links" aria-label="Principal">
      <a href="#categorias">Categorías</a>
      <a href="#destacados">Destacados</a>
      <a href="#zonas">Zonas</a>
    </nav>

    <div class="right">
      <?php if ($usuario_logueado): ?>
        <!-- Usuario con sesión iniciada -->
        <div class="user-menu">
          <img src="<?= htmlspecialchars($ruta_foto_user); ?>" alt="Perfil" class="avatar-img">
          <span class="user-name"><?= htmlspecialchars($nombre_usuario); ?></span>
          <div class="dropdown-menu-user">
            <a href="perfil.php">Ver / Editar Perfil</a>
            <?php if ($tipo_usuario === 'profesional'): ?>
              <a href="profesional/panel.php">Mi Panel de Negocio</a>
            <?php else: ?>
              <a href="cliente/mis_reservas.php">Mis Reservas</a>
            <?php endif; ?>
            <a href="cerrar_sesion.php" class="logout">Cerrar Sesión</a>
          </div>
        </div>
      <?php else: ?>
        <!-- Botón cambiado a "Regístrate" -->
        <a href="login.php" class="gh">Iniciar sesión</a>
        <a href="registro.php" class="pb">Regístrate</a>
      <?php endif; ?>

      <details class="mm">
        <summary aria-label="Abrir menú"><svg class="ic sm" viewBox="0 0 24 24" aria-hidden="true" style="stroke:#2D3748"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary>
        <div class="menu">
          <a href="#categorias">Categorías</a>
          <a href="#destacados">Destacados</a>
          <a href="#zonas">Zonas</a>
          <?php if ($usuario_logueado): ?>
            <a href="perfil.php">Ver / Editar Perfil</a>
            <a href="cerrar_sesion.php" style="color:#e53e3e;">Cerrar Sesión</a>
          <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
            <a href="registro.php">Regístrate</a>
          <?php endif; ?>
        </div>
      </details>
    </div>
  </div>
</header>

<div class="hero">
  <div class="wrap">
    <h1>Encuentra y reserva tu próximo cuidado en <?= e(CIUDAD) ?></h1>
    <p>Spas, peluquerías, uñas y más. Compara horarios, precios y reseñas de negocios locales.</p>

    <form class="sbar" action="buscar.php" method="get" role="search" autoComplete="off">
      <label class="fld">
        <svg class="ic sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
        <input type="text" name="q" placeholder="¿Qué servicio buscas?" aria-label="Servicio" value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>">
      </label>
      <label class="fld loc">
        <svg class="ic sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.3-6-10a6 6 0 0112 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
        <input type="text" name="ciudad" value="<?= e(CIUDAD_LARGA) ?>" aria-label="Ciudad">
      </label>
      <button type="submit" class="go">Buscar</button>
    </form>

    <div class="pop">
      <span class="l">Populares:</span>
      <?php foreach ($POPULARES as $p): ?>
        <a href="buscar.php?q=<?= urlencode($p) ?>"><?= e($p) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="wrap">

  <section id="categorias">
    <div class="sh"><h2>Explora por categoría</h2></div>
    <div class="cats">
      <?php foreach ($CATEGORIAS as $slug => [$nombre, $icono]): ?>
        <a href="buscar.php?cat=<?= e($slug) ?>" class="cat">
          <i><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><?= $icono ?></svg></i>
          <b><?= e($nombre) ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="destacados">
    <div class="sh"><h2>Establecimientos en <?= e(CIUDAD) ?></h2></div>
    
    <?php if (empty($destacados)): ?>
      <p style="background:#fff; padding:25px; border-radius:16px; border:1px solid #E4E0F2; color:#64748B;">Aún no hay negocios registrados en la plataforma. ¡Registra el tuyo ahora!</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($destacados as $n): 
            $has_foto = !empty($n['foto']) && file_exists('uploads/negocios/' . $n['foto']);
            $foto_url = $has_foto ? 'uploads/negocios/' . $n['foto'] : '';
        ?>
          <div class="card">
           <a href="negocio.php?id=<?= (int) $n['id'] ?>" aria-label="Ver <?= e($n['nombre']) ?>">
              <div class="img"<?= $has_foto ? ' style="background-image:url(\'' . e($foto_url) . '\')"' : '' ?>>
                <?= $has_foto ? '' : 'Sin Imagen' ?>
              </div>
              <div class="cb">
                <b><?= e($n['nombre']) ?></b>
                <div class="cs">📍 <?= e($n['colonia']) ?></div>
                <div class="cm">
                  <span>★ <?= number_format($n['rating'], 1) ?> (<?= (int) $n['resenas'] ?>)</span>
                  <span class="ok">Abierto</span>
                </div>
                <div class="cs" style="margin-top:8px; font-weight:600; color:#9048DB;">
                  <?= $n['desde'] > 0 ? 'Desde ' . dinero($n['desde']) : 'Servicios por consultar' ?>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section id="zonas">
    <div class="sh"><h2>Busca por zona en Xalapa</h2></div>
    <div class="zonas">
      <a href="buscar.php?q=Centro">Centro</a>
      <a href="buscar.php?q=Las+Ánimas">Las Ánimas</a>
      <a href="buscar.php?q=Araucarias">Araucarias</a>
      <a href="buscar.php?q=Badillo">Badillo</a>
    </div>
  </section>

  <!-- Banner Dinámico del Pie de Página (CTA) -->
  <div class="cta">
    <?php if ($tiene_negocio_registrado): ?>
      <!-- Si el profesional ya tiene su negocio registrado -->
      <div>
        <h2>Gestiona tu establecimiento en <?= e(CIUDAD) ?></h2>
        <p>Actualiza tus servicios, ajusta tus precios y revisa las citas de tus clientes desde tu panel de administración.</p>
      </div>
      <a href="profesional/panel.php">Ir a mi Panel de Negocio</a>
    <?php else: ?>
      <!-- Si no ha iniciado sesión o no tiene negocio registrado -->
      <div>
        <h2>¿Tienes un negocio en <?= e(CIUDAD) ?>?</h2>
        <p>Súmate al directorio, muestra tus servicios y horarios, y recibe reservas y reseñas de nuevos clientes.</p>
      </div>
      <a href="profesional/mi_negocio.php">Registrar mi negocio</a>
    <?php endif; ?>
  </div>

</div>

<footer class="foot">
  <div class="wrap">
    <div class="fc">
      <div class="about"><b><?= e(NOMBRE_SITIO) ?> · <?= e(CIUDAD) ?></b>El directorio de servicios de cuidado personal de la ciudad.</div>
      <div><b>Explorar</b><a href="#categorias">Categorías</a><a href="#zonas">Zonas</a><a href="#destacados">Destacados</a></div>
      <div><b>Negocios</b><a href="registro.php">Registrar mi negocio</a><a href="login.php">Iniciar sesión</a></div>
      <div><b>Cuenta</b><a href="perfil.php">Mi Perfil</a><a href="cerrar_sesion.php">Cerrar Sesión</a></div>
    </div>
    <div class="cp">© <?= $anio ?> <?= e(NOMBRE_SITIO) ?> · <?= e(CIUDAD_LARGA) ?></div>
  </div>
</footer>

</div>
</body>
</html>