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
$total_servicios = 0;

// Ruta de la foto de portada y conteo de servicios
if ($tiene_negocio) {
    $ruta_portada = "../uploads/negocios/" . $negocio['foto_portada'];
    if (!file_exists($ruta_portada) || empty($negocio['foto_portada']) || $negocio['foto_portada'] === 'default_negocio.png') {
        $ruta_portada = "https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop";
    }

    $stmt_cnt = $conexion->prepare("SELECT COUNT(*) AS total FROM servicios WHERE negocio_id = ?");
    $stmt_cnt->bind_param("i", $negocio['id']);
    $stmt_cnt->execute();
    $total_servicios = (int) $stmt_cnt->get_result()->fetch_assoc()['total'];
}

// Foto de perfil para la navbar
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
    <title>Panel Profesional · Bienestar &amp; Belleza</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/estilos.css">
    <style>
        body { font-family: 'DM Sans', sans-serif; background-color: #F4F2FD; color: #2D3748; }
        .btn-purple { background-color: #9048DB; color: #fff; border: none; border-radius: 10px; padding: 10px 18px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-purple:hover { background-color: #7b35c4; }
        .btn-ghost-purple { background-color: #fff; color: #9048DB; border: 1.5px solid #9048DB; border-radius: 10px; padding: 9px 16px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-ghost-purple:hover { background-color: #F0E6FC; }
        .mod-card { background: #fff; border: 1px solid #E4E0F2; border-radius: 16px; padding: 22px; transition: border-color .2s, transform .2s; }
        .mod-card:hover { border-color: #9048DB; transform: translateY(-2px); }
    </style>
</head>
<body>

    <!-- Navbar -->
    <header class="navbar" style="background:#fff; border-bottom:1px solid #E4E0F2;">
        <div class="navbar-container">
            <a href="../index.php" class="brand-logo" style="color:#2D3748;">✨ Bienestar &amp; Belleza</a>
            <nav class="nav-menu">
                <a href="../index.php" class="nav-link">Inicio</a>
                <a href="panel.php" class="nav-link active" style="color:#9048DB;">Panel</a>
                <div class="user-menu">
                    <img src="<?php echo htmlspecialchars($ruta_foto_user); ?>" alt="Foto de perfil" class="avatar-img" style="border-color:#9048DB;">
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
            <div style="background-color: #fff; border: 1px solid #E4E0F2; padding: 28px; border-radius: 16px; margin-bottom: 25px;">
                <h3 style="margin-bottom: 8px; color:#2D3748;">¡Bienvenido/a, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>!</h3>
                <p style="color:#64748B; margin-bottom:16px;">Para comenzar a publicar tus servicios y recibir citas, primero registra la información de tu establecimiento.</p>
                <a href="mi_negocio.php" class="btn-purple">Registrar Mi Negocio Ahora</a>
            </div>
        <?php else: ?>

            <!-- Tarjeta con Detalles Completos del Negocio -->
            <div style="background: #ffffff; border-radius: 16px; border: 1px solid #E4E0F2; overflow: hidden; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(112,72,180,0.05);">

                <!-- Imagen de Portada -->
                <div style="height: 190px; width: 100%; overflow: hidden; position: relative; background-color: #E6E1F8;">
                    <img src="<?php echo htmlspecialchars($ruta_portada); ?>" alt="Portada de negocio" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <!-- Información del Negocio -->
                <div style="padding: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h3 style="font-size: 24px; color: #2D3748; margin: 0 0 6px;"><?php echo htmlspecialchars($negocio['nombre_negocio']); ?></h3>
                            <p style="color: #64748B; font-size: 15px; margin: 0;">
                                <?php echo !empty($negocio['descripcion']) ? htmlspecialchars($negocio['descripcion']) : 'Sin descripción registrada.'; ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="../negocio.php?id=<?php echo (int) $negocio['id']; ?>" class="btn-ghost-purple">👁️ Ver perfil público</a>
                            <a href="mi_negocio.php" class="btn-purple">✏️ Editar Datos</a>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid #E4E0F2; margin: 18px 0;">

                    <!-- Detalles de Contacto, Ubicación y Horario -->
                    <div style="display: flex; gap: 24px; flex-wrap: wrap; color: #4B5563; font-size: 14px;">
                        <div>📍 <strong>Dirección:</strong> <?php echo htmlspecialchars($negocio['direccion']); ?></div>
                        <div>📞 <strong>Teléfono:</strong> <?php echo htmlspecialchars($negocio['telefono']); ?></div>
                        <div>⏰ <strong>Horario:</strong> <?php echo htmlspecialchars($negocio['horario'] ?? 'No especificado'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Accesos a los Módulos del Negocio -->
            <h3 style="font-size: 18px; color: #2D3748; margin-bottom: 15px;">Módulos de Gestión</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                <a href="servicios.php" style="text-decoration: none; color: inherit;">
                    <div class="mod-card">
                        <div style="font-size: 28px; margin-bottom: 8px;">✂️</div>
                        <h3 style="font-size: 17px; margin: 0 0 6px; color:#2D3748;">Mis Servicios y Precios</h3>
                        <p style="font-size: 13px; color: #64748B; margin: 0 0 10px;">Agrega, edita o elimina cortes, pestañas, uñas, masajes, etc.</p>
                        <span style="font-size: 12px; font-weight: 700; color: #9048DB; background: #F0E6FC; padding: 4px 10px; border-radius: 10px;">
                            <?php echo $total_servicios; ?> servicio<?php echo $total_servicios === 1 ? '' : 's'; ?> publicado<?php echo $total_servicios === 1 ? '' : 's'; ?>
                        </span>
                    </div>
                </a>

                <a href="reservas.php" style="text-decoration: none; color: inherit;">
                    <div class="mod-card">
                        <div style="font-size: 28px; margin-bottom: 8px;">📅</div>
                        <h3 style="font-size: 17px; margin: 0 0 6px; color:#2D3748;">Gestionar Citas</h3>
                        <p style="font-size: 13px; color: #64748B; margin: 0 0 10px;">Consulta, confirma o cancela las reservaciones hechas por clientes.</p>
                        <span style="font-size: 12px; font-weight: 700; color: #64748B; background: #F1F5F9; padding: 4px 10px; border-radius: 10px;">
                            Agenda de clientes
                        </span>
                    </div>
                </a>
            </div>

        <?php endif; ?>
    </main>

</body>
</html>