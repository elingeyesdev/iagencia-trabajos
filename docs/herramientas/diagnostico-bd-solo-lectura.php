<?php

/**
 * REDWORK — Diagnóstico de base de datos MySQL en SOLO LECTURA (FreeDB u otra).
 *
 * - Solo ejecuta SELECT / SHOW dentro de una transacción READ ONLY: no crea, modifica ni borra nada.
 * - No muestra host, usuario, contraseña, correos, nombres ni cédulas: solo estructura y conteos.
 * - Usa la conexión de Laravel (config/database.php). Las variables de entorno definidas en la terminal
 *   tienen prioridad sobre .env, así que puede apuntarse a FreeDB sin editar .env:
 *
 *   PowerShell:
 *     $env:DB_HOST="<host de FreeDB>"; $env:DB_PORT="3306"; $env:DB_DATABASE="<base>"
 *     $env:DB_USERNAME="<usuario>"; $env:DB_PASSWORD="<contraseña>"
 *     php docs/herramientas/diagnostico-bd-solo-lectura.php
 *     Remove-Item Env:DB_HOST,Env:DB_PORT,Env:DB_DATABASE,Env:DB_USERNAME,Env:DB_PASSWORD
 *
 * Ver docs/despliegue-render.md.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// Sin efectos secundarios al arrancar Laravel: caché, sesión y colas en memoria (nada se escribe en la BD).
foreach (['CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'] as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $_SERVER[$k] = $v;
}

$base = dirname(__DIR__, 2);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cfg = config('database.connections.' . config('database.default'));
if (($cfg['driver'] ?? '') !== 'mysql' && ($cfg['driver'] ?? '') !== 'mariadb') {
    fwrite(STDERR, "La conexión por defecto no es MySQL.\n");
    exit(1);
}

$host = (string) ($cfg['host'] ?? '');
$opciones = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 10];
$ca = ($cfg['options'] ?? [])[PDO::MYSQL_ATTR_SSL_CA] ?? null;
if ($ca) {
    $opciones[PDO::MYSQL_ATTR_SSL_CA] = $ca;
}

$out = [];
$sec = function (string $t) use (&$out) { $out[] = "\n== $t"; };
$lin = function (string $t) use (&$out) { $out[] = $t; };

$sec('Conexión');
$lin('Destino: ' . (str_contains(strtolower($host), 'freedb') ? 'FreeDB' : (in_array($host, ['127.0.0.1', 'localhost'], true) ? 'MySQL local' : 'servidor remoto')) . ' · puerto ' . ($cfg['port'] ?? '?'));

try {
    $t0 = microtime(true);
    $pdo = new PDO("mysql:host={$host};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4", $cfg['username'], $cfg['password'], $opciones);
    $lin(sprintf('Conexión correcta en %.0f ms', (microtime(true) - $t0) * 1000));
} catch (Throwable $e) {
    // Mensaje del driver sin credenciales (PDO no las incluye en el mensaje de error).
    $lin('ERROR de conexión: ' . preg_replace('/\'[^\']*\'@\'[^\']*\'/', "'***'@'***'", $e->getMessage()));
    echo implode("\n", $out), "\n";
    exit(2);
}

$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->exec('START TRANSACTION READ ONLY');
$q = fn (string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); })();
$v = fn (string $sql, array $p = []) => (function () use ($q, $sql, $p) { $r = $q($sql, $p); return $r ? array_values($r[0])[0] : null; })();
$db = $cfg['database'];

$lin('Servidor: ' . $v('SELECT VERSION()'));
$ssl = $q("SHOW SESSION STATUS LIKE 'Ssl_cipher'")[0]['Value'] ?? '';
$lin('TLS en esta conexión: ' . ($ssl !== '' ? "sí ({$ssl})" : 'no'));
foreach (['require_secure_transport', 'have_ssl', 'max_connections', 'max_user_connections', 'wait_timeout', 'sql_mode', 'character_set_server', 'time_zone'] as $var) {
    $r = $q('SHOW VARIABLES LIKE ?', [$var]);
    if ($r) {
        $lin("  {$var} = " . $r[0]['Value']);
    }
}

// ── Tablas ──
$tablas = array_column($q('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$db]), null, 'TABLE_NAME');
$esperadas = [
    'usuarios', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'personal_access_tokens',
    'publicaciones', 'categorias', 'postulantes', 'postulaciones', 'contrataciones', 'calificaciones',
    'parametros', 'pagos', 'migrations',
];
$sec('Tablas');
foreach ($esperadas as $t) {
    $lin(sprintf('  %-24s %s', $t, isset($tablas[$t]) ? "OK ({$tablas[$t]['ENGINE']}, {$tablas[$t]['TABLE_COLLATION']})" : 'FALTA'));
}
$extra = array_diff(array_keys($tablas), $esperadas);
if ($extra) {
    $lin('  Tablas adicionales (no usadas por las migraciones actuales o heredadas): ' . implode(', ', $extra));
}

// ── Columnas requeridas por el código actual ──
$columnas = [
    'usuarios' => ['cedula', 'nombre', 'telefono', 'email', 'email_verified_at', 'direccion', 'ciudad', 'contrasena', 'fechanac', 'estado', 'fecha_registro'],
    'publicaciones' => ['id_labor', 'tipo', 'titulo', 'descripcion', 'pago', 'lugar', 'fecha_publicacion', 'cedula', 'id_categoria', 'estado'],
    'categorias' => ['id_categoria', 'nombre_categoria', 'descripcion', 'estado'],
    'postulantes' => ['id_postulante', 'cedula', 'habilidades', 'experiencia', 'disponibilidad', 'tarifa', 'estado'],
    'postulaciones' => ['id_postulacion', 'id_labor', 'cedula', 'mensaje', 'fecha_postulacion', 'estado'],
    'contrataciones' => ['id_contratacion', 'id_postulacion', 'fecha_inicio', 'fecha_fin', 'monto_acordado', 'estado'],
    'calificaciones' => ['id_calificacion', 'id_contratacion', 'cedula_calificador', 'cedula_calificado', 'puntuacion', 'comentario', 'fecha'],
    'parametros' => ['id_parametro', 'clave', 'valor', 'descripcion', 'fecha_actualizacion', 'cedula_actualiza'],
    'pagos' => ['id_pago', 'id_contratacion', 'monto', 'porcentaje_comision', 'monto_comision', 'monto_neto', 'fecha_pago', 'metodo', 'estado', 'es_simulado', 'proveedor', 'referencia_externa', 'observacion', 'cedula_registra', 'fecha_registro', 'cedula_confirma', 'fecha_confirmacion', 'cedula_anula', 'fecha_anulacion', 'motivo_anulacion'],
    'sessions' => ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'],
    'cache' => ['key', 'value', 'expiration'],
    'personal_access_tokens' => ['id', 'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at'],
];
$sec('Columnas faltantes');
$faltan = 0;
$tipos = [];
foreach ($columnas as $t => $cols) {
    if (!isset($tablas[$t])) {
        continue;
    }
    $reales = array_column($q('SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$db, $t]), 'COLUMN_TYPE', 'COLUMN_NAME');
    $tipos[$t] = $reales;
    foreach (array_diff($cols, array_keys($reales)) as $c) {
        $lin("  {$t}.{$c} FALTA");
        $faltan++;
    }
}
$lin($faltan ? "  Total: {$faltan}" : '  Ninguna');
$lin('Tipos clave: usuarios.cedula=' . ($tipos['usuarios']['cedula'] ?? '?') . ' · contrataciones.id_contratacion=' . ($tipos['contrataciones']['id_contratacion'] ?? '?')
    . ' · sessions.user_id=' . ($tipos['sessions']['user_id'] ?? '?'));

// ── Índices únicos y claves foráneas ──
$sec('Restricciones');
$unicos = $q("SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ? AND NON_UNIQUE = 0 AND TABLE_NAME IN ('usuarios','postulantes','postulaciones','contrataciones','calificaciones','parametros','pagos')
              GROUP BY TABLE_NAME, INDEX_NAME ORDER BY TABLE_NAME, INDEX_NAME", [$db]);
foreach ($unicos as $u) {
    $lin("  UNIQUE {$u['TABLE_NAME']}.{$u['INDEX_NAME']} ({$u['cols']})");
}
foreach ([['postulaciones', 'id_labor,cedula'], ['contrataciones', 'id_postulacion'], ['calificaciones', 'id_contratacion,cedula_calificador']] as [$t, $cols]) {
    if (isset($tablas[$t]) && !in_array($cols, array_column(array_filter($unicos, fn ($u) => $u['TABLE_NAME'] === $t), 'cols'), true)) {
        $lin("  AVISO: falta UNIQUE {$t}({$cols})");
    }
}
$fks = $q('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME', [$db]);
foreach ($fks as $f) {
    $lin("  FK {$f['TABLE_NAME']}.{$f['COLUMN_NAME']} -> {$f['REFERENCED_TABLE_NAME']}.{$f['REFERENCED_COLUMN_NAME']}");
}

// ── Migraciones ──
$sec('Migraciones');
$archivos = array_map(fn ($f) => basename($f, '.php'), glob($base . '/database/migrations/*.php'));
sort($archivos);
if (isset($tablas['migrations'])) {
    $hechas = array_column($q('SELECT migration, batch FROM migrations ORDER BY id'), 'batch', 'migration');
    $lin('  Registradas: ' . count($hechas) . ' · lote máximo: ' . (max($hechas ?: [0])));
    foreach ($archivos as $m) {
        if (!isset($hechas[$m])) {
            $lin("  PENDIENTE: {$m}");
        }
    }
    foreach (array_diff(array_keys($hechas), $archivos) as $m) {
        $lin("  Registrada sin archivo en el proyecto: {$m}");
    }
} else {
    $lin('  No existe la tabla migrations: todas las migraciones figurarían como pendientes.');
}

// ── Datos (solo conteos) ──
$sec('Conteos');
foreach (['usuarios', 'roles', 'model_has_roles', 'categorias', 'publicaciones', 'postulantes', 'postulaciones', 'contrataciones', 'calificaciones', 'parametros', 'pagos', 'sessions', 'cache', 'personal_access_tokens', 'password_reset_tokens'] as $t) {
    if (isset($tablas[$t])) {
        $lin(sprintf('  %-24s %d', $t, $v("SELECT COUNT(*) FROM `{$t}`")));
    }
}
if (isset($tablas['postulaciones'])) {
    $lin('  postulaciones por estado: ' . json_encode(array_column($q('SELECT estado, COUNT(*) n FROM postulaciones GROUP BY estado'), 'n', 'estado')));
}
if (isset($tablas['contrataciones'])) {
    $lin('  contrataciones por estado: ' . json_encode(array_column($q('SELECT estado, COUNT(*) n FROM contrataciones GROUP BY estado'), 'n', 'estado')));
}
if (isset($tablas['pagos'])) {
    $lin('  pagos por estado: ' . json_encode(array_column($q('SELECT estado, COUNT(*) n FROM pagos GROUP BY estado'), 'n', 'estado'))
        . ' · no simulados: ' . $v('SELECT COUNT(*) FROM pagos WHERE es_simulado = 0'));
}
if (isset($tablas['parametros'])) {
    $lin('  parámetros: ' . json_encode(array_column($q('SELECT clave, valor FROM parametros ORDER BY clave'), 'valor', 'clave')));
}

// ── Roles y verificación de correo ──
$sec('Roles y verificación de correo');
if (isset($tablas['roles'], $tablas['model_has_roles'])) {
    $lin('  Roles: ' . json_encode(array_column($q('SELECT name, guard_name FROM roles ORDER BY id'), 'guard_name', 'name')));
    $lin('  Asignaciones por rol: ' . json_encode(array_column($q('SELECT r.name, COUNT(*) n FROM model_has_roles m JOIN roles r ON r.id = m.role_id GROUP BY r.name'), 'n', 'name')));
    $lin('  model_type usados: ' . json_encode(array_column($q('SELECT model_type, COUNT(*) n FROM model_has_roles GROUP BY model_type'), 'n', 'model_type')));
    $lin('  Usuarios sin rol: ' . $v('SELECT COUNT(*) FROM usuarios u WHERE NOT EXISTS (SELECT 1 FROM model_has_roles m WHERE m.model_id = u.cedula)'));
    $lin('  Asignaciones huérfanas (sin usuario): ' . $v('SELECT COUNT(*) FROM model_has_roles m WHERE NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.cedula = m.model_id)'));
}
$hayVerif = isset($tipos['usuarios']['email_verified_at']);
$lin('  usuarios.email_verified_at: ' . ($hayVerif ? 'EXISTE' : 'NO EXISTE'));
if ($hayVerif) {
    $lin('  Verificados: ' . $v('SELECT COUNT(*) FROM usuarios WHERE email_verified_at IS NOT NULL') . ' · sin verificar: ' . $v('SELECT COUNT(*) FROM usuarios WHERE email_verified_at IS NULL'));
}
if (isset($tablas['roles'], $tablas['model_has_roles'])) {
    $adm = $q("SELECT u.estado" . ($hayVerif ? ', u.email_verified_at IS NOT NULL AS verificado' : '') . "
               FROM usuarios u JOIN model_has_roles m ON m.model_id = u.cedula JOIN roles r ON r.id = m.role_id WHERE r.name = 'administrador'");
    $lin('  Administradores: ' . count($adm) . ' · activos: ' . count(array_filter($adm, fn ($a) => (int) $a['estado'] === 1))
        . ($hayVerif ? ' · verificados: ' . count(array_filter($adm, fn ($a) => (int) $a['verificado'] === 1)) : ' · verificación: columna inexistente'));
}

// ── Integridad de reglas de negocio (solo lectura) ──
$sec('Integridad');
$chk = [
    'postulaciones duplicadas (id_labor, cedula)' => 'SELECT COUNT(*) FROM (SELECT 1 FROM postulaciones GROUP BY id_labor, cedula HAVING COUNT(*) > 1) d',
    'contrataciones con postulación inexistente' => 'SELECT COUNT(*) FROM contrataciones c LEFT JOIN postulaciones p ON p.id_postulacion = c.id_postulacion WHERE p.id_postulacion IS NULL',
    'contrataciones cuya postulación no está aceptada' => "SELECT COUNT(*) FROM contrataciones c JOIN postulaciones p ON p.id_postulacion = c.id_postulacion WHERE p.estado <> 'aceptada'",
    'postulaciones con publicación inexistente' => 'SELECT COUNT(*) FROM postulaciones p LEFT JOIN publicaciones l ON l.id_labor = p.id_labor WHERE l.id_labor IS NULL',
    'calificaciones con contratación inexistente' => 'SELECT COUNT(*) FROM calificaciones k LEFT JOIN contrataciones c ON c.id_contratacion = k.id_contratacion WHERE c.id_contratacion IS NULL',
    'publicaciones con categoría inexistente' => 'SELECT COUNT(*) FROM publicaciones l LEFT JOIN categorias c ON c.id_categoria = l.id_categoria WHERE c.id_categoria IS NULL',
    'usuarios con fechanac nula' => 'SELECT COUNT(*) FROM usuarios WHERE fechanac IS NULL',
];
if (isset($tablas['pagos'])) {
    $chk['contrataciones con pagos no anulados > monto_acordado'] = "SELECT COUNT(*) FROM (SELECT c.id_contratacion FROM contrataciones c JOIN pagos g ON g.id_contratacion = c.id_contratacion AND g.estado <> 'anulado' GROUP BY c.id_contratacion, c.monto_acordado HAVING SUM(g.monto) > c.monto_acordado) x";
}
foreach ($chk as $nombre => $sql) {
    try {
        $lin(sprintf('  %-52s %s', $nombre, $v($sql)));
    } catch (Throwable $e) {
        $lin(sprintf('  %-52s no evaluable (%s)', $nombre, $e->getCode()));
    }
}

$pdo->exec('ROLLBACK');
$lin("\nFin del diagnóstico (solo lectura; no se modificó nada).");
echo implode("\n", $out), "\n";
