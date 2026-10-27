<?php
session_start();
require_once 'conexion/bd.php';
 
// Obtener datos del usuario en sesión si está autenticado
$usuario_logueado = isset($_SESSION['usuario_id']);
$nombre_usuario = $usuario_logueado ? $_SESSION['usuario_nombre'] : '';
$tipo_usuario = $usuario_logueado ? $_SESSION['usuario_tipo'] : '';
$foto_perfil = $usuario_logueado ? $_SESSION['usuario_foto'] : 'default_avatar.png';
 
// Definir la ruta relativa de la foto de perfil
$ruta_foto = "uploads/perfiles/" . $foto_perfil;
if (!file_exists($ruta_foto) || empty($foto_perfil)) {
    $ruta_foto = "https://ui-avatars.com/api/?name=" . urlencode($nombre_usuario) . "&background=9b51e0&color=ffffff";
}
 
// Filtrado de negocios: texto (nombre, descripción o servicio), rango de precio y calificación mínima
$q = trim($_GET['q'] ?? '');
$precio_min = (isset($_GET['precio_min']) && is_numeric($_GET['precio_min'])) ? (float) $_GET['precio_min'] : '';
$precio_max = (isset($_GET['precio_max']) && is_numeric($_GET['precio_max'])) ? (float) $_GET['precio_max'] : '';
$calif_min = (isset($_GET['calif_min']) && is_numeric($_GET['calif_min'])) ? (int) $_GET['calif_min'] : 0;
 
$sql = "SELECT n.id, n.nombre_negocio, n.descripcion, n.direccion, n.telefono, n.horario, n.foto_portada,
        (SELECT AVG(r.calificacion) FROM resenas r WHERE r.negocio_id = n.id) AS promedio,
        (SELECT MIN(s.precio) FROM servicios s WHERE s.negocio_id = n.id) AS precio_desde
        FROM negocios n WHERE 1=1";
$tipos = "";
$params = [];
 
if ($q !== '') {
    $sql .= " AND (n.nombre_negocio LIKE ? OR n.descripcion LIKE ? OR EXISTS (SELECT 1 FROM servicios s WHERE s.negocio_id = n.id AND (s.nombre_servicio LIKE ? OR s.descripcion LIKE ?)))";
    $like = "%" . $q . "%";
    $tipos .= "ssss";
    array_push($params, $like, $like, $like, $like);
}
 
if ($precio_min !== '' || $precio_max !== '') {
    $sql .= " AND EXISTS (SELECT 1 FROM servicios s WHERE s.negocio_id = n.id";
    if ($precio_min !== '') {
        $sql .= " AND s.precio >= ?";
        $tipos .= "d";
        $params[] = $precio_min;
    }
    if ($precio_max !== '') {
        $sql .= " AND s.precio <= ?";
        $tipos .= "d";
        $params[] = $precio_max;
    }
    $sql .= ")";
}
 
if ($calif_min > 0) {
    $sql .= " HAVING promedio >= ?";
    $tipos .= "i";
    $params[] = $calif_min;
}
 
$sql .= " ORDER BY promedio DESC, n.nombre_negocio ASC";
 
$stmt_negocios = $conexion->prepare($sql);
if ($tipos !== "") {
    $stmt_negocios->bind_param($tipos, ...$params);
}
$stmt_negocios->execute();
$negocios = $stmt_negocios->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios Bienestar y Belleza - Xalapa</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
 
<body>
 
    <!-- Encabezado y Navegación -->
    <header class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand-logo">
                ✨ Bienestar & Belleza
            </a>
 
            <nav class="nav-menu">
                <a href="index.php" class="nav-link active">Inicio</a>
                <a href="#categorias" class="nav-link">Categorías</a>
                <a href="#destacados" class="nav-link">Destacados</a>
 
                <?php if ($usuario_logueado): ?>
                    <!-- Menú Usuario Autenticado -->
                    <div class="user-menu">
                        <img src="<?php echo htmlspecialchars($ruta_foto); ?>" alt="Foto de perfil" class="avatar-img">
                        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
                        <div class="dropdown-content">
                            <a href="perfil.php">Ver / Editar Perfil</a>
                            <?php if ($tipo_usuario === 'profesional'): ?>
                                <a href="profesional/panel.php">Mi Panel de Negocio</a>
                            <?php else: ?>
                                <a href="cliente/mis_reservas.php">Mis Reservas</a>
                            <?php endif; ?>
                            <a href="cerrar_sesion.php" class="logout-link">Cerrar Sesión</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Opciones para invitados -->
                    <a href="login.php" class="btn-nav btn-outline">Iniciar Sesión</a>
                    <a href="registro.php" class="btn-nav btn-primary-nav">Registrarse</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
 
    <!-- Sección Hero / Buscador -->
    <section class="hero">
        <div class="hero-content">
            <h1>Encuentra y reserva los mejores servicios de atención personal en Xalapa</h1>
            <p>Barberías, estéticas, spas, manicure y más en un solo lugar.</p>
 
            <form action="index.php" method="GET" class="search-box" autocomplete="off" style="flex-wrap: wrap; max-width: 700px;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="¿Qué servicio buscas? (ej. Corte, Uñas, Masaje)" style="flex: 1 1 100%;">
                <input type="number" name="precio_min" min="0" step="any" value="<?php echo $precio_min; ?>" placeholder="Precio mín." style="flex: 1 1 120px;">
                <input type="number" name="precio_max" min="0" step="any" value="<?php echo $precio_max; ?>" placeholder="Precio máx." style="flex: 1 1 120px;">
                <select name="calif_min" style="flex: 1 1 150px; padding: 14px 18px; border-radius: 8px; border: none; font-size: 15px; background: #ffffff;">
                    <option value="0">Cualquier calificación</option>
                    <?php for ($i = 4; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $calif_min === $i ? 'selected' : ''; ?>><?php echo $i; ?>★ o más</option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-search">Buscar</button>
            </form>
        </div>
    </section>
 
    <!-- Sección de Categorías -->
    <section id="categorias" class="main-container">
        <h2 class="section-title">Categorías Principales</h2>
        <div class="categories-grid">
            <div class="category-card">
                <span class="category-icon">💈</span>
                <h3>Barbería</h3>
            </div>
            <div class="category-card">
                <span class="category-icon">💇‍♀️</span>
                <h3>Estética & Salón</h3>
            </div>
            <div class="category-card">
                <span class="category-icon">💅</span>
                <h3>Uñas & Pestañas</h3>
            </div>
            <div class="category-card">
                <span class="category-icon">🧖</span>
                <h3>Spa & Masajes</h3>
            </div>
            <div class="category-card">
                <span class="category-icon">🧴</span>
                <h3>Cuidado Facial</h3>
            </div>
        </div>
    </section>
 
    <!-- Sección de Negocios (resultado del filtrado) -->
    <section id="destacados" class="main-container">
        <h2 class="section-title">Negocios en Xalapa</h2>
        <?php if (empty($negocios)): ?>
            <p style="color: #718096;">No se encontraron negocios con los filtros seleccionados.</p>
        <?php else: ?>
            <div class="categories-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
                <?php foreach ($negocios as $negocio): ?>
                    <?php
                    $ruta_portada = "uploads/negocios/" . $negocio['foto_portada'];
                    if (empty($negocio['foto_portada']) || !file_exists($ruta_portada)) {
                        $ruta_portada = "https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop";
                    }
                    ?>
                    <div class="category-card" style="text-align: left; padding: 0; overflow: hidden;">
                        <img src="<?php echo htmlspecialchars($ruta_portada); ?>" alt="Portada de negocio" style="width: 100%; height: 140px; object-fit: cover;">
                        <div style="padding: 15px; font-size: 13px; color: #4a5568;">
                            <h3 style="font-size: 17px; color: #2d3748; margin-bottom: 4px;"><?php echo htmlspecialchars($negocio['nombre_negocio']); ?></h3>
                            <p style="color: #718096; margin-bottom: 8px;"><?php echo !empty($negocio['descripcion']) ? htmlspecialchars($negocio['descripcion']) : 'Sin descripción registrada.'; ?></p>
                            <p>⭐ <?php echo $negocio['promedio'] !== null ? number_format($negocio['promedio'], 1) : 'Sin calificaciones'; ?></p>
                            <?php if ($negocio['precio_desde'] !== null): ?>
                                <p>💲 Desde $<?php echo number_format($negocio['precio_desde'], 2); ?></p>
                            <?php endif; ?>
                            <p>📍 <?php echo htmlspecialchars($negocio['direccion']); ?></p>
                            <p>📞 <?php echo htmlspecialchars($negocio['telefono'] ?? ''); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
 
    <!-- Pie de página 
    <footer class="footer">
        <p>&copy; // <?php echo date('Y');  ?> Servicios Bienestar y Belleza - Xalapa, Veracruz.</p>
    </footer> 
    -->
 
 
</body>
 
</html>
