<?php
session_start();
require_once '../conexion/bd.php';

// Verificar autenticación y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'profesional') {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Consultar datos completos del negocio
$stmt = $conexion->prepare("SELECT id, nombre_negocio, descripcion, direccion, telefono, horario, foto_portada FROM negocios WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$negocio = $res->fetch_assoc();

$tiene_negocio = ($res->num_rows > 0);

// Ruta de la foto de portada del negocio
if ($tiene_negocio) {
    $ruta_portada = "../uploads/negocios/" . $negocio['foto_portada'];
    if (!file_exists($ruta_portada) || empty($negocio['foto_portada'])) {
        $ruta_portada = "https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop"; // Imagen por defecto limpia
    }
}

// Foto de perfil para la navbar
$ruta_foto_user = "../uploads/perfiles/" . $_SESSION['usuario_foto'];
if (!file_exists($ruta_foto_user) || empty($_SESSION['usuario_foto'])) {
    $ruta_foto_user = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['usuario_nombre']) . "&background=9b51e0&color=ffffff";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Profesional - Servicios Bienestar y Belleza</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>

<body>

    <!-- Navbar -->
    <header class="navbar">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo">✨ Bienestar & Belleza</a>
            <nav class="nav-menu">
                <a href="../index.php" class="nav-link">Inicio</a>
                <a href="panel.php" class="nav-link active">Panel</a>
                <div class="user-menu">
                    <img src="<?php echo htmlspecialchars($ruta_foto_user); ?>" alt="Foto de perfil" class="avatar-img">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <div class="dropdown-content">
                        <a href="../perfil.php">Ver / Editar Perfil</a>
                        <a href="panel.php">Mi Panel de Negocio</a>
                        <a href="../cerrar_sesion.php" class="logout-link">Cerrar Sesión</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="main-container">
        <h2 class="section-title">Panel de Control del Profesional</h2>

        <?php if (!$tiene_negocio): ?>
            <!-- Alerta si aún no registra su negocio -->
            <div class="alert alert-info" style="background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 25px; border-radius: 12px; margin-bottom: 25px;">
                <h3 style="margin-bottom: 8px;">¡Bienvenido/a, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>!</h3>
                <p>Para comenzar a publicar tus servicios y recibir citas, primero debes registrar la información de tu establecimiento o perfil profesional.</p>
                <a href="mi_negocio.php" class="btn btn-primary" style="display: inline-block; width: auto; margin-top: 15px; text-decoration: none; padding: 10px 20px;">Registrar Mi Negocio Ahora</a>
            </div>
        <?php else: ?>

            <!-- Tarjeta con Detalles Completos del Negocio -->
            <div style="background: #ffffff; border-radius: 12px; border: 1px solid #edf2f7; overflow: hidden; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">

                <!-- Imagen de Portada -->
                <div style="height: 180px; width: 100%; overflow: hidden; position: relative; background-color: #f7f9fc;">
                    <img src="<?php echo htmlspecialchars($ruta_portada); ?>" alt="Portada de negocio" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <!-- Información del Negocio -->
                <div style="padding: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h3 style="font-size: 24px; color: #2d3748; margin-bottom: 6px;"><?php echo htmlspecialchars($negocio['nombre_negocio']); ?></h3>
                            <p style="color: #718096; font-size: 15px; margin-bottom: 12px; font-style: italic;">
                                <?php echo !empty($negocio['descripcion']) ? htmlspecialchars($negocio['descripcion']) : 'Sin descripción registrada.'; ?>
                            </p>
                        </div>
                        <a href="mi_negocio.php" class="btn-nav btn-outline" style="padding: 10px 18px;">✏️ Editar Datos del Negocio</a>
                    </div>

                    <hr style="border: 0; border-top: 1px solid #edf2f7; margin: 15px 0;">

                    <!-- Detalles de Contacto y Ubicación -->
                    <!-- Detalles de Contacto, Ubicación y Horario -->
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; color: #4a5568; font-size: 14px;">
                        <div>📍 <strong>Dirección:</strong> <?php echo htmlspecialchars($negocio['direccion']); ?></div>
                        <div>📞 <strong>Teléfono:</strong> <?php echo htmlspecialchars($negocio['telefono']); ?></div>
                        <div>⏰ <strong>Horario:</strong> <?php echo htmlspecialchars($negocio['horario'] ?? 'No especificado'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Accesos a los Módulos del Negocio -->
            <h3 style="font-size: 18px; color: #2d3748; margin-bottom: 15px;">Módulos de Gestión</h3>

            <div class="categories-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                <a href="servicios.php" style="text-decoration: none; color: inherit;">
                    <div class="category-card" style="cursor: pointer; text-align: left; padding: 20px;">
                        <span class="category-icon" style="margin-bottom: 5px;">✂️</span>
                        <h3 style="font-size: 17px; margin-bottom: 4px;">Mis Servicios y Precios</h3>
                        <p style="font-size: 13px; color: #718096;">Agrega, edita o elimina cortes, pestañas, uñas, masajes, etc.</p>
                    </div>
                </a>

                <a href="reservas.php" style="text-decoration: none; color: inherit;">
                    <div class="category-card" style="cursor: pointer; text-align: left; padding: 20px;">
                        <span class="category-icon" style="margin-bottom: 5px;">📅</span>
                        <h3 style="font-size: 17px; margin-bottom: 4px;">Gestionar Citas</h3>
                        <p style="font-size: 13px; color: #718096;">Consulta, confirma o cancela las reservaciones hechas por clientes.</p>
                    </div>
                </a>
            </div>

        <?php endif; ?>
    </main>

</body>

</html>