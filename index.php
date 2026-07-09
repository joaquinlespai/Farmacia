<?php
declare(strict_types=1);

date_default_timezone_set('America/Santiago');
session_start();

const MIN_EXPIRATION_DATE = '2026-01-01';
const MAX_MEDICINE_PRICE = 10000000;

$dataDir = __DIR__ . '/data';
$dbPath = $dataDir . '/farmacia.sqlite';
$isNewDatabase = !file_exists($dbPath);

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
    CREATE TABLE IF NOT EXISTS medicamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        laboratorio TEXT NOT NULL,
        categoria TEXT NOT NULL,
        precio REAL NOT NULL CHECK (precio >= 0 AND precio <= 10000000),
        stock INTEGER NOT NULL CHECK (stock >= 0),
        fecha_vencimiento TEXT NOT NULL CHECK (fecha_vencimiento >= '2026-01-01'),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        rol TEXT NOT NULL DEFAULT 'admin',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS historial (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER,
        usuario_nombre TEXT NOT NULL,
        accion TEXT NOT NULL,
        entidad TEXT NOT NULL,
        registro_id INTEGER,
        detalle TEXT NOT NULL,
        ip_host_cliente TEXT NOT NULL DEFAULT 'No disponible',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )
");
$historialColumns = $pdo->query("PRAGMA table_info(historial)")->fetchAll(PDO::FETCH_ASSOC);
$hasIpHostCliente = false;
foreach ($historialColumns as $column) {
    if (($column['name'] ?? '') === 'ip_host_cliente') {
        $hasIpHostCliente = true;
        break;
    }
}
if (!$hasIpHostCliente) {
    $pdo->exec("ALTER TABLE historial ADD COLUMN ip_host_cliente TEXT NOT NULL DEFAULT 'No disponible'");
}
$pdo->exec("
    CREATE TRIGGER IF NOT EXISTS medicamentos_precio_limite_insert
    BEFORE INSERT ON medicamentos
    WHEN NEW.precio < 0 OR NEW.precio > 10000000
    BEGIN
        SELECT RAISE(ABORT, 'El precio no puede superar los 10.000.000 de pesos.');
    END
");
$pdo->exec("
    CREATE TRIGGER IF NOT EXISTS medicamentos_precio_limite_update
    BEFORE UPDATE ON medicamentos
    WHEN NEW.precio < 0 OR NEW.precio > 10000000
    BEGIN
        SELECT RAISE(ABORT, 'El precio no puede superar los 10.000.000 de pesos.');
    END
");
$pdo->exec("DROP TRIGGER IF EXISTS medicamentos_fecha_vencimiento_insert");
$pdo->exec("DROP TRIGGER IF EXISTS medicamentos_fecha_vencimiento_update");
$pdo->exec("
    CREATE TRIGGER IF NOT EXISTS medicamentos_fecha_vencimiento_insert
    BEFORE INSERT ON medicamentos
    WHEN NEW.fecha_vencimiento < '2026-01-01' OR NEW.fecha_vencimiento > date('now', '+2 years')
    BEGIN
        SELECT RAISE(ABORT, 'La fecha de vencimiento debe estar entre 2026 y máximo 2 años desde hoy.');
    END
");
$pdo->exec("
    CREATE TRIGGER IF NOT EXISTS medicamentos_fecha_vencimiento_update
    BEFORE UPDATE ON medicamentos
    WHEN NEW.fecha_vencimiento < '2026-01-01' OR NEW.fecha_vencimiento > date('now', '+2 years')
    BEGIN
        SELECT RAISE(ABORT, 'La fecha de vencimiento debe estar entre 2026 y máximo 2 años desde hoy.');
    END
");

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($userCount === 0) {
    $seedUser = $pdo->prepare("
        INSERT INTO usuarios (nombre, email, password_hash, rol)
        VALUES (?, ?, ?, ?)
    ");
    $seedUser->execute(['Administrador', 'admin@infin.cl', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    $seedUser->execute(['Joaquín Lespai', 'joaquin@infin.cl', password_hash('123456', PASSWORD_DEFAULT), 'admin']);
}

if ($isNewDatabase) {
    $seed = $pdo->prepare("
        INSERT INTO medicamentos (nombre, laboratorio, categoria, precio, stock, fecha_vencimiento)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $seed->execute(['Paracetamol 500 mg', 'Laboratorio Chile', 'Analgésico', 1990, 45, '2027-04-15']);
    $seed->execute(['Ibuprofeno 400 mg', 'PharmaVida', 'Antiinflamatorio', 2490, 30, '2027-08-20']);
    $seed->execute(['Loratadina 10 mg', 'MediSur', 'Antialérgico', 3290, 22, '2028-01-10']);
}

function cleanText(string $value): string
{
    return trim((string) preg_replace('/\s+/', ' ', $value));
}

function fixCommonSpelling(string $value): string
{
    $corrections = [
        '/\bparacetamol\b/iu' => 'Paracetamol',
        '/\bparasetamol\b/iu' => 'Paracetamol',
        '/\bparacetmol\b/iu' => 'Paracetamol',
        '/\bparacethamol\b/iu' => 'Paracetamol',
        '/\bibuprofeno\b/iu' => 'Ibuprofeno',
        '/\biboprufeno\b/iu' => 'Ibuprofeno',
        '/\bibruprofeno\b/iu' => 'Ibuprofeno',
        '/\bloratadina\b/iu' => 'Loratadina',
        '/\bloratadima\b/iu' => 'Loratadina',
        '/\bloratadyna\b/iu' => 'Loratadina',
        '/\bamoxicilina\b/iu' => 'Amoxicilina',
        '/\bamoxilina\b/iu' => 'Amoxicilina',
        '/\bamoxicilna\b/iu' => 'Amoxicilina',
        '/\bomeprazol\b/iu' => 'Omeprazol',
        '/\bomeprasol\b/iu' => 'Omeprazol',
        '/\baspirina\b/iu' => 'Aspirina',
        '/\baspirna\b/iu' => 'Aspirina',
        '/\bcetirizina\b/iu' => 'Cetirizina',
        '/\bcetirisina\b/iu' => 'Cetirizina',
        '/\bmetformina\b/iu' => 'Metformina',
        '/\bmetformia\b/iu' => 'Metformina',
        '/\blosartan\b/iu' => 'Losartán',
        '/\banalgesico\b/iu' => 'Analgésico',
        '/\banalgesiko\b/iu' => 'Analgésico',
        '/\bantialergico\b/iu' => 'Antialérgico',
        '/\banti alergico\b/iu' => 'Antialérgico',
        '/\bantibiotico\b/iu' => 'Antibiótico',
        '/\bantiinflamatorio\b/iu' => 'Antiinflamatorio',
        '/\bantihistaminico\b/iu' => 'Antihistamínico',
        '/\blaboratorio chile\b/iu' => 'Laboratorio Chile',
        '/\bmedisur\b/iu' => 'MediSur',
        '/\bpharmavida\b/iu' => 'PharmaVida',
    ];

    return preg_replace(array_keys($corrections), array_values($corrections), $value);
}

function money(float $value): string
{
    return '$' . number_format($value, 0, ',', '.');
}

function formatDateMx(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('d-m-Y') : $value;
}

function formatDateTimeMx(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? $date->format('d-m-Y H:i:s') : $value;
}

function maxExpirationDate(): string
{
    return date('Y-m-d', strtotime('+2 years'));
}

function currentUserName(): string
{
    return $_SESSION['user_nombre'] ?? 'Sistema';
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserIsAdmin(): bool
{
    return ($_SESSION['user_rol'] ?? '') === 'admin';
}

function currentClientIpHost(): string
{
    $ip = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'IP no disponible';

    return clientIpOnly($ip);
}

function clientIpOnly(string $value): string
{
    $ip = trim(explode(',', $value)[0]);
    $ip = trim(explode('/', $ip)[0]);

    return $ip !== '' ? $ip : 'IP no disponible';
}

function logAction(PDO $pdo, string $accion, string $entidad, ?int $registroId, string $detalle): void
{
    $log = $pdo->prepare("
        INSERT INTO historial (usuario_id, usuario_nombre, accion, entidad, registro_id, detalle, ip_host_cliente, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $log->execute([
        currentUserId(),
        currentUserName(),
        $accion,
        $entidad,
        $registroId,
        $detalle,
        currentClientIpHost(),
        date('Y-m-d H:i:s'),
    ]);
}

$message = '';
$errors = [];
$loginError = '';
$registerError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $nombre = cleanText($_POST['nombre_usuario'] ?? '');
    $email = cleanText($_POST['email_registro'] ?? '');
    $password = (string) ($_POST['password_registro'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($nombre === '') {
        $registerError = 'El nombre es obligatorio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Ingresa un correo válido.';
    } elseif (strlen($password) < 6) {
        $registerError = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'Las contraseñas no coinciden.';
    } else {
        $emailExists = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
        $emailExists->execute([$email]);

        if ((int) $emailExists->fetchColumn() > 0) {
            $registerError = 'Ya existe un usuario con ese correo.';
        } else {
            $createUser = $pdo->prepare("
                INSERT INTO usuarios (nombre, email, password_hash, rol)
                VALUES (?, ?, ?, ?)
            ");
            $createUser->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
            $newUserId = (int) $pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_nombre'] = $nombre;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_rol'] = 'admin';

            logAction($pdo, 'Registro de usuario', 'usuarios', $newUserId, 'Se registró el usuario: ' . $nombre . '.');
            header('Location: index.php?msg=registered');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = cleanText($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $findUser = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $findUser->execute([$email]);
    $user = $findUser->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_rol'] = $user['rol'];
        logAction($pdo, 'Inicio de sesión', 'usuarios', (int) $user['id'], 'El usuario inició sesión en el sistema.');
        header('Location: index.php?msg=login');
        exit;
    }

    $loginError = 'Correo o contraseña incorrectos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    logAction($pdo, 'Cierre de sesión', 'usuarios', currentUserId(), 'El usuario cerró sesión en el sistema.');
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?msg=logout');
    exit;
}

$isAuthenticated = isset($_SESSION['user_id']);
if ($isAuthenticated && !isset($_SESSION['user_rol'])) {
    $findCurrentUser = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
    $findCurrentUser->execute([currentUserId()]);
    $_SESSION['user_rol'] = (string) ($findCurrentUser->fetchColumn() ?: '');
}
$showClientNetworkInfo = $isAuthenticated && currentUserIsAdmin();

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $findDeleted = $pdo->prepare('SELECT nombre FROM medicamentos WHERE id = ?');
            $findDeleted->execute([$id]);
            $deletedName = (string) ($findDeleted->fetchColumn() ?: 'Registro no encontrado');
            $delete = $pdo->prepare('DELETE FROM medicamentos WHERE id = ?');
            $delete->execute([$id]);
            logAction($pdo, 'Eliminar', 'medicamentos', $id, 'Eliminó el medicamento: ' . $deletedName . '.');
            header('Location: index.php?msg=deleted');
            exit;
        }
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = fixCommonSpelling(cleanText($_POST['nombre'] ?? ''));
        $laboratorio = fixCommonSpelling(cleanText($_POST['laboratorio'] ?? ''));
        $categoria = fixCommonSpelling(cleanText($_POST['categoria'] ?? ''));
        $precio = (float) ($_POST['precio'] ?? -1);
        $stock = (int) ($_POST['stock'] ?? -1);
        $fechaVencimiento = cleanText($_POST['fecha_vencimiento'] ?? '');

        if ($nombre === '') {
            $errors[] = 'El nombre del medicamento es obligatorio.';
        }
        if ($laboratorio === '') {
            $errors[] = 'El laboratorio es obligatorio.';
        }
        if ($categoria === '') {
            $errors[] = 'La categoría es obligatoria.';
        }
        if ($precio < 0) {
            $errors[] = 'El precio debe ser mayor o igual a 0.';
        } elseif ($precio > MAX_MEDICINE_PRICE) {
            $errors[] = 'El precio no puede superar los 10.000.000 de pesos.';
        }
        if ($stock < 0) {
            $errors[] = 'El stock debe ser mayor o igual a 0.';
        }
        if ($fechaVencimiento === '') {
            $errors[] = 'La fecha de vencimiento es obligatoria.';
        } elseif ($fechaVencimiento < MIN_EXPIRATION_DATE) {
            $errors[] = 'La fecha de vencimiento debe ser desde el 1 de enero de 2026.';
        } elseif ($fechaVencimiento > maxExpirationDate()) {
            $errors[] = 'La fecha de vencimiento no puede superar los 2 años desde hoy.';
        }

        if (!$errors) {
            if ($id > 0) {
                $update = $pdo->prepare("
                    UPDATE medicamentos
                    SET nombre = ?, laboratorio = ?, categoria = ?, precio = ?, stock = ?, fecha_vencimiento = ?
                    WHERE id = ?
                ");
                $update->execute([$nombre, $laboratorio, $categoria, $precio, $stock, $fechaVencimiento, $id]);
                logAction($pdo, 'Modificar', 'medicamentos', $id, 'Modificó el medicamento: ' . $nombre . '.');
                header('Location: index.php?msg=updated');
                exit;
            }

            $insert = $pdo->prepare("
                INSERT INTO medicamentos (nombre, laboratorio, categoria, precio, stock, fecha_vencimiento)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$nombre, $laboratorio, $categoria, $precio, $stock, $fechaVencimiento]);
            logAction($pdo, 'Crear', 'medicamentos', (int) $pdo->lastInsertId(), 'Creó el medicamento: ' . $nombre . '.');
            header('Location: index.php?msg=created');
            exit;
        }
    }
}

$messages = [
    'created' => 'Medicamento creado correctamente.',
    'updated' => 'Medicamento modificado correctamente.',
    'deleted' => 'Medicamento eliminado correctamente.',
    'login' => 'Sesión iniciada correctamente.',
    'logout' => 'Sesión cerrada correctamente.',
    'registered' => 'Usuario registrado correctamente.',
];
$message = $messages[$_GET['msg'] ?? ''] ?? '';

$editing = null;
if ($isAuthenticated && isset($_GET['edit'])) {
    $find = $pdo->prepare('SELECT * FROM medicamentos WHERE id = ?');
    $find->execute([(int) $_GET['edit']]);
    $editing = $find->fetch(PDO::FETCH_ASSOC) ?: null;
}

$medicamentos = [];
$historial = [];
if ($isAuthenticated) {
    $medicamentos = $pdo
        ->query('SELECT * FROM medicamentos ORDER BY id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);
    $historial = $pdo
        ->query('SELECT * FROM historial ORDER BY datetime(created_at) DESC, id DESC LIMIT 30')
        ->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In-Fin Pharmacy - CRUD de Medicamentos</title>
    <style>
        :root {
            --blue: #004080;
            --blue-light: #0b6db8;
            --green: #168a57;
            --red: #c43b3b;
            --ink: #1f2937;
            --muted: #64748b;
            --line: #d9e2ec;
            --surface: rgba(255, 255, 255, 0.94);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--ink);
            background:
                linear-gradient(rgba(247, 250, 252, 0.84), rgba(232, 242, 250, 0.88)),
                url('farmacia-fondo.png') center / cover fixed;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 12px 28px;
            background: var(--blue);
            color: white;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.18);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 700;
        }

        .brand img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            background: white;
            border-radius: 8px;
            padding: 4px;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 14px;
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        main {
            width: min(1180px, calc(100% - 32px));
            margin: 28px auto 48px;
        }

        section {
            margin-bottom: 24px;
            padding: 24px;
            background: var(--surface);
            border: 1px solid rgba(217, 226, 236, 0.9);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        h1, h2, h3 {
            margin: 0 0 12px;
            line-height: 1.2;
        }

        h1 {
            font-size: 34px;
        }

        h2 {
            font-size: 23px;
            color: var(--blue);
        }

        p {
            margin: 0 0 12px;
            color: #334155;
            line-height: 1.55;
        }

        ul {
            margin: 0;
            padding-left: 20px;
            line-height: 1.6;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            align-items: center;
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            border: 1px solid #b6d9f2;
            border-radius: 999px;
            padding: 7px 11px;
            background: #edf8ff;
            color: #0f4f7a;
            font-size: 13px;
            font-weight: 700;
        }

        .mockup {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            display: block;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .crud-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
            background: #f8fafc;
        }

        .crud-item strong {
            display: block;
            color: var(--blue);
            margin-bottom: 6px;
        }

        .app-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 20px;
            align-items: start;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #334155;
            font-size: 14px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 11px 12px;
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        input:focus {
            outline: 3px solid rgba(11, 109, 184, 0.18);
            border-color: var(--blue-light);
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .session-name {
            color: white;
            font-size: 14px;
            font-weight: 700;
        }

        .logout-form {
            margin: 0;
        }

        .logout-form button {
            min-height: 34px;
            padding: 7px 10px;
            background: #ef4444;
            font-size: 13px;
        }

        .login-section {
            max-width: 960px;
            margin: 42px auto;
        }

        .auth-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .auth-panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 18px;
            background: #f8fafc;
        }

        .login-help {
            padding: 12px;
            border-radius: 6px;
            background: #edf8ff;
            border: 1px solid #b6d9f2;
            color: #123f63;
            font-size: 14px;
        }

        .audit-table td {
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        button,
        .button {
            border: 0;
            border-radius: 6px;
            padding: 10px 12px;
            background: var(--blue-light);
            color: white;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
        }

        button:hover,
        .button:hover {
            filter: brightness(0.95);
        }

        .button.secondary {
            background: #475569;
        }

        .button.danger,
        button.danger {
            background: var(--red);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 8px;
            background: white;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #e9f4fb;
            color: #123f63;
            font-size: 13px;
            text-transform: uppercase;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .notice {
            padding: 12px 14px;
            margin-bottom: 16px;
            border-radius: 6px;
            background: #e7f8ef;
            color: #10633d;
            border: 1px solid #a8e3c3;
            font-weight: 700;
        }

        .errors {
            padding: 12px 14px;
            margin-bottom: 16px;
            border-radius: 6px;
            background: #fff1f1;
            color: #922929;
            border: 1px solid #f2b7b7;
        }

        .inline-form {
            display: inline;
        }

        footer {
            padding: 24px;
            background: var(--blue);
            color: white;
            text-align: center;
        }

        footer p {
            margin: 0;
            color: white;
        }

        @media (max-width: 900px) {
            header,
            .hero,
            .app-grid,
            .auth-grid {
                grid-template-columns: 1fr;
            }

            header {
                align-items: flex-start;
                flex-direction: column;
            }

            .grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            main {
                width: min(100% - 20px, 1180px);
            }

            section {
                padding: 18px;
            }

            h1 {
                font-size: 28px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <img src="logo-infin-pharmacy1.png" alt="Logo de In-Fin Pharmacy">
            <span>In-Fin Pharmacy</span>
        </div>
        <div class="top-actions">
            <?php if ($isAuthenticated): ?>
                <nav aria-label="Menú principal">
                    <a href="#inicio">Inicio</a>
                    <a href="#crud">CRUD</a>
                    <a href="#mockup">Mockup</a>
                    <a href="#base-datos">Base de datos</a>
                    <a href="#medicamentos">Medicamentos</a>
                    <a href="#historial">Historial</a>
                </nav>
                <span class="session-name"><?php echo htmlspecialchars(currentUserName(), ENT_QUOTES, 'UTF-8'); ?></span>
                <form class="logout-form" method="post">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit">Cerrar sesión</button>
                </form>
            <?php else: ?>
                <span class="session-name">Acceso protegido</span>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <?php if (!$isAuthenticated): ?>
            <section class="login-section">
                <h1>Acceso al sistema</h1>
                <p>Ingresa o registra una cuenta para administrar medicamentos y dejar historial de cambios.</p>

                <?php if ($message): ?>
                    <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <div class="auth-grid">
                    <div class="auth-panel">
                        <h2>Iniciar sesión</h2>

                        <?php if ($loginError): ?>
                            <div class="errors"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="action" value="login">

                            <label for="email">Correo</label>
                            <input id="email" name="email" type="email" required autocomplete="username" value="admin@infin.cl">

                            <label for="password">Contraseña</label>
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="admin123">

                            <button type="submit">Entrar</button>
                        </form>

                        <p class="login-help">Usuario de prueba: <strong>admin@infin.cl</strong> / <strong>admin123</strong></p>
                    </div>

                    <div class="auth-panel">
                        <h2>Registrarse</h2>

                        <?php if ($registerError): ?>
                            <div class="errors"><?php echo htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="action" value="register">

                            <label for="nombre_usuario">Nombre</label>
                            <input id="nombre_usuario" name="nombre_usuario" required autocomplete="name">

                            <label for="email_registro">Correo</label>
                            <input id="email_registro" name="email_registro" type="email" required autocomplete="email">

                            <label for="password_registro">Contraseña</label>
                            <input id="password_registro" name="password_registro" type="password" minlength="6" required autocomplete="new-password">

                            <label for="confirm_password">Confirmar contraseña</label>
                            <input id="confirm_password" name="confirm_password" type="password" minlength="6" required autocomplete="new-password">

                            <button type="submit">Crear cuenta</button>
                        </form>
                    </div>
                </div>
            </section>
        <?php else: ?>
        <section class="hero" id="inicio">
            <div>
                <h1>Sistema de gestión de medicamentos</h1>
                <p>
                    In-Fin Pharmacy es una aplicación web para administrar el inventario de medicamentos de una farmacia.
                    El sistema permite registrar productos, revisar existencias, actualizar datos comerciales y eliminar
                    registros que ya no correspondan al catálogo activo.
                </p>
                <p>
                    La aplicación está construida con PHP y SQLite. La base de datos se crea automáticamente dentro del
                    proyecto al abrir la página en GitHub Codespaces. El acceso queda protegido con inicio de sesión y cada cambio
                    importante queda registrado en un historial de auditoría.
                </p>
                <h2>Integrantes</h2>
                <ul>
                    <li>Tomás Teihuel</li>
                    <li>Gerardo Cerón</li>
                    <li>Joaquín Lespai</li>
                </ul>
                <div class="pill-row">
                    <span class="pill">PHP</span>
                    <span class="pill">SQLite</span>
                    <span class="pill">CRUD</span>
                    <span class="pill">GitHub Codespaces</span>
                </div>
            </div>
            <img class="mockup" src="mockup.png" alt="Mockup de la interfaz principal de In-Fin Pharmacy">
        </section>

        <section id="crud">
            <h2>Descripción de operaciones CRUD</h2>
            <div class="grid">
                <div class="crud-item">
                    <strong>Crear</strong>
                    Registrar un medicamento nuevo con nombre, laboratorio, categoría, precio, stock y fecha de vencimiento.
                    El historial guarda quién lo creó y cuándo.
                </div>
                <div class="crud-item">
                    <strong>Leer</strong>
                    Mostrar en una tabla todos los medicamentos almacenados en la base de datos.
                </div>
                <div class="crud-item">
                    <strong>Modificar</strong>
                    Cargar los datos de un medicamento existente en el formulario y guardar sus cambios.
                    La acción queda asociada al usuario activo.
                </div>
                <div class="crud-item">
                    <strong>Eliminar</strong>
                    Borrar un medicamento del inventario cuando ya no forma parte del catálogo.
                    El sistema registra el nombre del registro eliminado.
                </div>
            </div>
        </section>

        <section id="mockup">
            <h2>Mockup de interfaz principal</h2>
            <p>
                Esta imagen representa la pantalla esperada para el módulo principal de gestión de medicamentos.
            </p>
            <img class="mockup" src="mockup.png" alt="Mockup del CRUD de medicamentos">
        </section>

        <section id="base-datos">
            <h2>Base de datos</h2>
            <p>
                La base de datos seleccionada es SQLite. Se utilizan las tablas <strong>medicamentos</strong>,
                <strong>usuarios</strong> e <strong>historial</strong>, definidas en el archivo <strong>schema.sql</strong>.
                Cuando la página se ejecuta por primera vez, PHP crea el archivo <strong>data/farmacia.sqlite</strong>,
                agrega registros iniciales y deja usuarios de prueba para acceder al sistema.
            </p>
        </section>

        <section id="medicamentos">
            <h2>CRUD funcional de medicamentos</h2>

            <?php if ($message): ?>
                <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="errors">
                    <strong>Revisa los siguientes datos:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="app-grid">
                <form method="post">
                    <h3><?php echo $editing ? 'Modificar medicamento' : 'Nuevo medicamento'; ?></h3>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($editing['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" required value="<?php echo htmlspecialchars((string) ($editing['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="laboratorio">Laboratorio</label>
                    <input id="laboratorio" name="laboratorio" required value="<?php echo htmlspecialchars((string) ($editing['laboratorio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="categoria">Categoría</label>
                    <input id="categoria" name="categoria" required value="<?php echo htmlspecialchars((string) ($editing['categoria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="precio">Precio</label>
                    <input id="precio" name="precio" type="number" min="0" max="<?php echo MAX_MEDICINE_PRICE; ?>" step="1" required data-max-message="El precio no puede superar los 10.000.000 de pesos." value="<?php echo htmlspecialchars((string) ($editing['precio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="stock">Stock</label>
                    <input id="stock" name="stock" type="number" min="0" step="1" required value="<?php echo htmlspecialchars((string) ($editing['stock'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="fecha_vencimiento">Fecha de vencimiento</label>
                    <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" min="<?php echo MIN_EXPIRATION_DATE; ?>" max="<?php echo maxExpirationDate(); ?>" required value="<?php echo htmlspecialchars((string) ($editing['fecha_vencimiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="actions">
                        <button type="submit"><?php echo $editing ? 'Guardar cambios' : 'Crear medicamento'; ?></button>
                        <?php if ($editing): ?>
                            <a class="button secondary" href="index.php#medicamentos">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div>
                    <h3>Listado de medicamentos</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Laboratorio</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Vence</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicamentos as $medicamento): ?>
                                <tr>
                                    <td><?php echo (int) $medicamento['id']; ?></td>
                                    <td><?php echo htmlspecialchars($medicamento['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($medicamento['laboratorio'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($medicamento['categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo money((float) $medicamento['precio']); ?></td>
                                    <td><?php echo (int) $medicamento['stock']; ?></td>
                                    <td><?php echo htmlspecialchars(formatDateMx($medicamento['fecha_vencimiento']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a class="button" href="index.php?edit=<?php echo (int) $medicamento['id']; ?>#medicamentos">Editar</a>
                                            <form class="inline-form" method="post" onsubmit="return confirm('¿Deseas eliminar este medicamento?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int) $medicamento['id']; ?>">
                                                <button class="danger" type="submit">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="historial">
            <h2>Historial de cambios</h2>
            <p>Registro de quién hizo cada acción, cuándo la hizo y sobre qué registro del sistema.</p>
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>ID</th>
                        <?php if ($showClientNetworkInfo): ?>
                            <th>IP/Host cliente</th>
                        <?php endif; ?>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $evento): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatDateTimeMx($evento['created_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($evento['usuario_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($evento['accion'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($evento['entidad'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($evento['registro_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($showClientNetworkInfo): ?>
                                <td><?php echo htmlspecialchars(clientIpOnly($evento['ip_host_cliente'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($evento['detalle'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$historial): ?>
                        <tr>
                            <td colspan="<?php echo $showClientNetworkInfo ? 7 : 6; ?>">Aún no hay acciones registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2026 In-Fin Pharmacy. Proyecto académico CRUD con PHP y SQLite.</p>
    </footer>
    <script>
        const spellingCorrections = [
            [/\bparacetamol\b/gi, 'Paracetamol'],
            [/\bparasetamol\b/gi, 'Paracetamol'],
            [/\bparacetmol\b/gi, 'Paracetamol'],
            [/\bparacethamol\b/gi, 'Paracetamol'],
            [/\bibuprofeno\b/gi, 'Ibuprofeno'],
            [/\biboprufeno\b/gi, 'Ibuprofeno'],
            [/\bibruprofeno\b/gi, 'Ibuprofeno'],
            [/\bloratadina\b/gi, 'Loratadina'],
            [/\bloratadima\b/gi, 'Loratadina'],
            [/\bloratadyna\b/gi, 'Loratadina'],
            [/\bamoxicilina\b/gi, 'Amoxicilina'],
            [/\bamoxilina\b/gi, 'Amoxicilina'],
            [/\bamoxicilna\b/gi, 'Amoxicilina'],
            [/\bomeprazol\b/gi, 'Omeprazol'],
            [/\bomeprasol\b/gi, 'Omeprazol'],
            [/\baspirina\b/gi, 'Aspirina'],
            [/\baspirna\b/gi, 'Aspirina'],
            [/\bcetirizina\b/gi, 'Cetirizina'],
            [/\bcetirisina\b/gi, 'Cetirizina'],
            [/\bmetformina\b/gi, 'Metformina'],
            [/\bmetformia\b/gi, 'Metformina'],
            [/\blosartan\b/gi, 'Losartán'],
            [/\banalgesico\b/gi, 'Analgésico'],
            [/\banalgesiko\b/gi, 'Analgésico'],
            [/\bantialergico\b/gi, 'Antialérgico'],
            [/\banti alergico\b/gi, 'Antialérgico'],
            [/\bantibiotico\b/gi, 'Antibiótico'],
            [/\bantihistaminico\b/gi, 'Antihistamínico'],
            [/\blaboratorio chile\b/gi, 'Laboratorio Chile'],
            [/\bmedisur\b/gi, 'MediSur'],
            [/\bpharmavida\b/gi, 'PharmaVida'],
        ];

        function fixSpelling(value) {
            return spellingCorrections.reduce((text, correction) => text.replace(correction[0], correction[1]), value.replace(/\s+/g, ' ').trim());
        }

        ['nombre', 'laboratorio', 'categoria'].forEach((id) => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('blur', () => {
                    input.value = fixSpelling(input.value);
                });
            }
        });

        const priceInput = document.getElementById('precio');
        if (priceInput) {
            const validatePrice = () => {
                const max = Number(priceInput.max);
                const value = Number(priceInput.value);
                priceInput.setCustomValidity(priceInput.value !== '' && value > max ? priceInput.dataset.maxMessage : '');
            };

            priceInput.addEventListener('input', validatePrice);
            priceInput.addEventListener('invalid', validatePrice);
        }
    </script>
</body>
</html>
