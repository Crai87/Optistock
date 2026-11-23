<?php

// Contraseña de emergencia: solo se muestra en el panel izquierdo al pasar el cursor.
// Solo recupera el hash si está en desarrollo — en producción esto siempre queda vacío.
// Contraseña de emergencia — siempre visible al pasar cursor en el login
$admin_pass_hint = 'Optometría en la UAL, generación 26, con símbolo';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../config/database.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, password_hash, nombre, apellido_paterno, apellido_materno, username, rol_id AS rol FROM usuarios WHERE username = :user AND activo = 1");
        $stmt->execute(['user' => $username]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash']) && $usuario['username'] === $username) {
            $_SESSION['usuario_id']        = $usuario['id'];
            $_SESSION['nombre_completo']   = trim($usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']);
            $_SESSION['rol']              = $usuario['rol'];

            // Cargar permisos del usuario en sesión para el middleware
            $stmtPerms = $pdo->prepare("
                SELECT p.descripcion
                FROM usuario_permisos up
                JOIN permisos p ON p.id = up.permiso_id
                WHERE up.usuario_id = :uid
            ");
            $stmtPerms->execute(['uid' => $usuario['id']]);
            $permsBase = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

            // Aplicar herencia de permisos UNA SOLA VEZ al hacer login.
            // Así quedan guardados en sesión y no se recalculan en cada request.
            // Editar/Agregar/Reporte implica Ver en el mismo módulo.
            if (in_array('Editar armazones', $permsBase) || in_array('Agregar armazones', $permsBase) || in_array('Generar reporte armazones', $permsBase)) {
                if (!in_array('Ver armazones', $permsBase)) $permsBase[] = 'Ver armazones';
            }
            if (in_array('Editar equipos', $permsBase) || in_array('Agregar equipos', $permsBase) || in_array('Generar reporte equipos', $permsBase)) {
                if (!in_array('Ver equipos', $permsBase)) $permsBase[] = 'Ver equipos';
            }
            if (in_array('Agregar garantias', $permsBase) || in_array('Editar garantias', $permsBase)) {
                if (!in_array('Ver garantias', $permsBase)) $permsBase[] = 'Ver garantias';
            }

            $_SESSION['permisos'] = $permsBase;

            // Admin → inventario, invitado → dashboard
            if ((int)$usuario['rol'] === 1) {
                header('Location: /optometria-inventario/public/index.php?view=inventario');
            } else {
                header('Location: /optometria-inventario/public/index.php?view=dashboard');
            }
            exit;
        } else {
            $error = 'Credenciales incorrectas. Verifica el usuario y la contraseña.';
        }
    } else {
        $error = 'Debes ingresar usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OptiStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/style.css">
    <link rel="stylesheet" href="/optometria-inventario/public/assets/css/index.css">
</head>
<body>
<div class="container-fluid h-100">
    <div class="row h-100">

        <div class="col-md-6 d-none d-md-flex flex-column justify-content-center align-items-center text-center left-panel">
            <h1 class="fw-bold display-5">OptiStock</h1>
            <p class="fs-5">Sistema de Gestión de Inventario</p>
        </div>

        <div class="col-md-6 d-flex justify-content-center align-items-center right-side">
            <div class="card login-card p-5">
                    <div class="text-center mb-3">
                        <img src="/optometria-inventario/public/assets/img/Logo.png" 
                            alt="Logo" 
                            class="img_logo">
                    </div>
                <h2 class="text-center fw-bold mb-2">Iniciar Sesión</h2>
                <p class="text-center text-muted mb-4">Ingresa tus credenciales para acceder al sistema</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger text-center p-2 mb-3" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" class="form-control custom-input" name="username" placeholder="Ingresa tu usuario" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock text-muted"></i>
                            </span>

                            <input type="password" class="form-control custom-input" id="passInput" name="password">

                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                                <svg id="iconoOjoAbierto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>

                                <svg id="iconoOjoCerrado" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" style="display:none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login btn-primary w-100 text-white mt-3">
                        Iniciar Sesión
                    </button>
                </form>

                <div class="text-center mt-5 text-muted small">
                    © 2026 OptiStock - Sistema de Inventario de Armazones<br>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass() {

    const input = document.getElementById("passInput");
    const ojoAbierto = document.getElementById("iconoOjoAbierto");
    const ojoCerrado = document.getElementById("iconoOjoCerrado");

    if (input.type === "password") {
        input.type = "text";
        ojoAbierto.style.display = "none";
        ojoCerrado.style.display = "block";
    } else {
        input.type = "password";
        ojoAbierto.style.display = "block";
        ojoCerrado.style.display = "none";
    }
}

let hintTimer = null;

function startHint() {
    hintTimer = setTimeout(() => {
        const el = document.getElementById('adminPassHint');
        el.style.color = '#333';
        el.style.background = '#f0f0f0';
    }, 3000); // 3 segundos
}

function cancelHint() {
    clearTimeout(hintTimer);
    const el = document.getElementById('adminPassHint');
    el.style.color = 'transparent';
    el.style.background = 'transparent';
}
</script>
</body>
</html>