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

            <form action="index.php" method="GET" class="search-box">
                <input type="text" name="q" placeholder="¿Qué servicio buscas? (ej. Corte, Uñas, Masaje)">
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

    <!-- Pie de página 
    <footer class="footer">
        <p>&copy; // <?php echo date('Y');  ?> Servicios Bienestar y Belleza - Xalapa, Veracruz.</p>
    </footer> 
    -->


</body>

</html>