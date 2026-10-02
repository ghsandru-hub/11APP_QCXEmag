<?php
/**
 * EcR Deconturi eMag — API Backend
 * ------------------------------------------------------------------
 * Single-file front-controller. Mirrors the IndexedDB wrapper API
 * (put / bulkPut / get / getAll / count / clear / delete) plus auth.
 *
 * Schema: 7 tables identical 1:1 to the IndexedDB stores.
 * Auth:   PHP sessions (cookie-based, until browser closes).
 * Tenant: cursValutar is global (BNR public data); everything else
 *         is filtered by user_id from the session.
 *
 * Endpoint protocol (all POST except where noted):
 *   ?action=login        body: { email, password }
 *   ?action=logout
 *   ?action=me           (GET ok)
 *   ?action=put          body: { store, value }
 *   ?action=bulkPut      body: { store, values: [...] }
 *   ?action=get          body: { store, key }
 *   ?action=getAll       body: { store }
 *   ?action=count        body: { store }
 *   ?action=clear        body: { store }
 *   ?action=delete       body: { store, key }
 *
 * Response: { ok: true, data: ... } | { ok: false, error: "..." }
 */

declare(strict_types=1);

// ---------- Hardening ----------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Session: until-browser-closes cookie (lifetime=0), httponly + samesite=lax
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('emag_sess');
session_start();

// ---------- Helpers ----------
function out($payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $status = 400): void {
    out(['ok' => false, 'error' => $msg], $status);
}
function readBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

// ---------- DB ----------
const DB_DIR  = __DIR__ . '/../private_mkpemag/data';
const DB_FILE = __DIR__ . '/../private_mkpemag/data/emag.db';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!is_dir(DB_DIR)) {
        if (!@mkdir(DB_DIR, 0755, true) && !is_dir(DB_DIR)) {
            fail('Cannot create data directory', 500);
        }
    }
    if (!is_writable(DB_DIR)) {
        fail('Data directory is not writable', 500);
    }

    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
    } catch (Throwable $e) {
        fail('SQLite connection failed: ' . $e->getMessage(), 500);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    ensureSchema($pdo);
    return $pdo;
}

function ensureSchema(PDO $pdo): void {
    // Run any one-time migrations from previous schema versions before
    // creating the canonical tables. Migrations are idempotent — safe to
    // re-run on every request.
    runMigrations($pdo);

    // Canonical schema (global tables — data is shared across all users).
    // Authentication still uses the users table, but data is no longer
    // tenant-isolated. Non-admin users have read-only access; admin users
    // have full read+write+delete (enforced in the action handlers).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            email         TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            name          TEXT,
            is_admin      INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS nomenclator (
            cod  TEXT PRIMARY KEY,
            data TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS marketplaces (
            id_seller TEXT PRIMARY KEY,
            data      TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS curs_valutar (
            key       TEXT PRIMARY KEY,
            data_curs TEXT,
            valuta    TEXT,
            data      TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_curs_data   ON curs_valutar(data_curs);
        CREATE INDEX IF NOT EXISTS idx_curs_valuta ON curs_valutar(valuta);

        CREATE TABLE IF NOT EXISTS raw_data (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            canal         TEXT,
            data_emitere  TEXT,
            moneda        TEXT,
            serie_numar   TEXT,
            batch_id      TEXT,
            data          TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_raw_canal  ON raw_data(canal);
        CREATE INDEX IF NOT EXISTS idx_raw_data   ON raw_data(data_emitere);
        CREATE INDEX IF NOT EXISTS idx_raw_moneda ON raw_data(moneda);
        CREATE INDEX IF NOT EXISTS idx_raw_serie  ON raw_data(serie_numar);
        CREATE INDEX IF NOT EXISTS idx_raw_batch  ON raw_data(batch_id);

        CREATE TABLE IF NOT EXISTS data_set (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            canal TEXT,
            an    INTEGER,
            luna  INTEGER,
            data  TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_ds_canal   ON data_set(canal);
        CREATE INDEX IF NOT EXISTS idx_ds_an_luna ON data_set(an, luna);

        CREATE TABLE IF NOT EXISTS settings (
            key  TEXT PRIMARY KEY,
            data TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS import_log (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            data TEXT NOT NULL
        );
    ");
}

/**
 * Schema migration: from per-user tenant tables (with user_id columns) to
 * global shared tables. Detects old schema by presence of the user_id
 * column and rebuilds tables atomically. Conflicts on natural keys are
 * resolved with INSERT OR IGNORE (first-row-wins, in id order).
 *
 * Safe to run on every request — only does work if migration is needed.
 */
function runMigrations(PDO $pdo): void {
    // Migration: add `name` column to users table if missing (older installs).
    ensureUserNameColumn($pdo);

    $tables = [
        // table => [new schema, copied columns, [extra index DDL, ...]]
        'nomenclator' => [
            'cod TEXT PRIMARY KEY, data TEXT NOT NULL',
            'cod, data',
            []
        ],
        'marketplaces' => [
            'id_seller TEXT PRIMARY KEY, data TEXT NOT NULL',
            'id_seller, data',
            []
        ],
        'raw_data' => [
            'id INTEGER PRIMARY KEY AUTOINCREMENT, canal TEXT, data_emitere TEXT, moneda TEXT, serie_numar TEXT, batch_id TEXT, data TEXT NOT NULL',
            'id, canal, data_emitere, moneda, serie_numar, batch_id, data',
            [
                'CREATE INDEX IF NOT EXISTS idx_raw_canal  ON raw_data(canal)',
                'CREATE INDEX IF NOT EXISTS idx_raw_data   ON raw_data(data_emitere)',
                'CREATE INDEX IF NOT EXISTS idx_raw_moneda ON raw_data(moneda)',
                'CREATE INDEX IF NOT EXISTS idx_raw_serie  ON raw_data(serie_numar)',
                'CREATE INDEX IF NOT EXISTS idx_raw_batch  ON raw_data(batch_id)',
            ]
        ],
        'data_set' => [
            'id INTEGER PRIMARY KEY AUTOINCREMENT, canal TEXT, an INTEGER, luna INTEGER, data TEXT NOT NULL',
            'id, canal, an, luna, data',
            [
                'CREATE INDEX IF NOT EXISTS idx_ds_canal   ON data_set(canal)',
                'CREATE INDEX IF NOT EXISTS idx_ds_an_luna ON data_set(an, luna)',
            ]
        ],
        'settings' => [
            'key TEXT PRIMARY KEY, data TEXT NOT NULL',
            'key, data',
            []
        ],
        'import_log' => [
            'id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT NOT NULL',
            'id, data',
            []
        ],
    ];

    foreach ($tables as $table => $spec) {
        if (!hasUserIdColumn($pdo, $table)) continue;

        [$newSchema, $columns, $indexes] = $spec;

        // Foreign keys must be off during table rebuild (otherwise the
        // CASCADE we previously had on user_id triggers spurious deletes).
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $pdo->exec("CREATE TABLE {$table}_new ({$newSchema})");
            // Dedup on natural key. If two users had the same nomenclator
            // code with different content, the lowest-id row wins.
            $pdo->exec("INSERT OR IGNORE INTO {$table}_new ({$columns}) SELECT {$columns} FROM {$table}");
            $pdo->exec("DROP TABLE {$table}");
            $pdo->exec("ALTER TABLE {$table}_new RENAME TO {$table}");
            foreach ($indexes as $ddl) $pdo->exec($ddl);
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            try { $pdo->exec('ROLLBACK'); } catch (Throwable $_) {}
            $pdo->exec('PRAGMA foreign_keys = ON');
            error_log("[emag-api] migration failed for $table: " . $e->getMessage());
            throw $e;
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

function hasUserIdColumn(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->query("PRAGMA table_info(" . $table . ")");
        if (!$stmt) return false;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (($col['name'] ?? '') === 'user_id') return true;
        }
    } catch (Throwable $e) {
        // Table doesn't exist yet — nothing to migrate
    }
    return false;
}

/**
 * Add `name` column to users table if missing. Older installs created
 * the table without it. SQLite supports ALTER TABLE ADD COLUMN directly,
 * so no rebuild is needed.
 */
function ensureUserNameColumn(PDO $pdo): void {
    // Skip if users table doesn't exist yet — the canonical CREATE TABLE
    // statement (run after migrations) will create it with all columns.
    try {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        if (!$exists) return;
    } catch (Throwable $e) { return; }

    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'name') return; // already there
        }
        $pdo->exec("ALTER TABLE users ADD COLUMN name TEXT");
    } catch (Throwable $e) {
        error_log('[emag-api] ensureUserNameColumn failed: ' . $e->getMessage());
    }
}

// ---------- Store metadata ----------
// Maps frontend store name → table + key field + indexable columns + autoinc?
// All tables are global (shared across users). Read access is open to any
// authenticated user; write access is enforced as admin-only in the action
// handlers below.
const STORES = [
    'nomenclator' => [
        'table'   => 'nomenclator',
        'key_col' => 'cod',
        'key_src' => 'Cod',
        'autoinc' => false,
        'extra'   => [],
    ],
    'marketplaces' => [
        'table'   => 'marketplaces',
        'key_col' => 'id_seller',
        'key_src' => 'IdSeller',
        'autoinc' => false,
        'extra'   => [],
    ],
    'cursValutar' => [
        'table'   => 'curs_valutar',
        'key_col' => 'key',
        'key_src' => 'key',
        'autoinc' => false,
        'extra'   => [
            'Data'   => 'data_curs',
            'Valuta' => 'valuta',
        ],
    ],
    'rawData' => [
        'table'   => 'raw_data',
        'key_col' => 'id',
        'key_src' => 'id',
        'autoinc' => true,
        'extra'   => [
            'Canal'        => 'canal',
            'DataEmitere'  => 'data_emitere',
            'Moneda'       => 'moneda',
            'SerieNumar'   => 'serie_numar',
            '_batchId'     => 'batch_id',
        ],
    ],
    'dataSet' => [
        'table'   => 'data_set',
        'key_col' => 'id',
        'key_src' => 'id',
        'autoinc' => true,
        'extra'   => [
            'Canal' => 'canal',
            'An'    => 'an',
            'Luna'  => 'luna',
        ],
    ],
    'settings' => [
        'table'   => 'settings',
        'key_col' => 'key',
        'key_src' => 'key',
        'autoinc' => false,
        'extra'   => [],
    ],
    'importLog' => [
        'table'   => 'import_log',
        'key_col' => 'id',
        'key_src' => 'id',
        'autoinc' => true,
        'extra'   => [],
    ],
];

function storeMeta(string $name): array {
    if (!isset(STORES[$name])) fail("Unknown store: $name", 400);
    return STORES[$name];
}

// ---------- Auth ----------
function currentUserId(): ?int {
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}
function requireAuth(): int {
    $uid = currentUserId();
    if ($uid === null) fail('Not authenticated', 401);
    return $uid;
}

function requireAdmin(): int {
    $uid = requireAuth();
    if (empty($_SESSION['is_admin'])) fail('Privilegii insuficiente — necesită admin', 403);
    return $uid;
}

function actionLogin(): void {
    $b = readBody();
    $email = trim((string)($b['email'] ?? ''));
    $pass  = (string)($b['password'] ?? '');
    if ($email === '' || $pass === '') fail('Email and password required', 400);

    $stmt = db()->prepare('SELECT id, email, password_hash, is_admin, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pass, $u['password_hash'])) {
        // generic message — don't leak whether email exists
        fail('Email sau parolă incorectă', 401);
    }

    session_regenerate_id(true);
    $_SESSION['uid']      = (int)$u['id'];
    $_SESSION['email']    = $u['email'];
    $_SESSION['is_admin'] = (int)$u['is_admin'];
    $_SESSION['name']     = $u['name'] ?? null;

    out(['ok' => true, 'data' => [
        'id'       => (int)$u['id'],
        'email'    => $u['email'],
        'name'     => $u['name'] ?? null,
        'is_admin' => (bool)$u['is_admin'],
    ]]);
}

function actionLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    out(['ok' => true]);
}

function actionMe(): void {
    $uid = currentUserId();
    if ($uid === null) {
        out(['ok' => true, 'data' => null]);
    }
    out(['ok' => true, 'data' => [
        'id'       => $uid,
        'email'    => $_SESSION['email'] ?? '',
        'name'     => $_SESSION['name']  ?? null,
        'is_admin' => (bool)($_SESSION['is_admin'] ?? false),
    ]]);
}

/**
 * Public status endpoint. Tells the frontend whether the database is
 * uninitialized (zero users) — in which case the UI offers a one-time
 * "first admin setup" form instead of a regular login form.
 *
 * Auto-disables as soon as any user exists in the DB.
 */
function actionStatus(): void {
    $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    out(['ok' => true, 'data' => [
        'needs_bootstrap' => ($count === 0),
        'has_users'       => ($count > 0),
    ]]);
}

/**
 * One-time first-admin bootstrap. Allowed only while the users table
 * is empty. Creates the first user as admin and immediately logs them
 * in (session set), so the frontend can proceed straight to the app.
 *
 * After the first user is created, this endpoint refuses all calls.
 */
function actionBootstrap(): void {
    $b = readBody();
    $email = trim((string)($b['email'] ?? ''));
    $pass  = (string)($b['password'] ?? '');
    $name  = isset($b['name']) ? trim((string)$b['name']) : null;
    if ($name === '') $name = null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Email invalid', 400);
    if (strlen($pass) < 8) fail('Parola trebuie să aibă cel puțin 8 caractere', 400);

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $pdo = db();

    // Use an immediate transaction so the count + insert is atomic and
    // a parallel bootstrap call cannot win the race.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            $pdo->exec('ROLLBACK');
            fail('Bootstrap nu mai e permis — există deja conturi. Folosește login-ul.', 409);
        }
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, is_admin) VALUES (?, ?, ?, 1)');
        $stmt->execute([$email, $hash, $name]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $_) {}
        fail('Eroare la creare: ' . $e->getMessage(), 500);
    }

    // Auto-login the freshly created admin.
    session_regenerate_id(true);
    $_SESSION['uid']      = $newId;
    $_SESSION['email']    = $email;
    $_SESSION['is_admin'] = 1;
    $_SESSION['name']     = $name;

    out(['ok' => true, 'data' => [
        'id'       => $newId,
        'email'    => $email,
        'name'     => $name,
        'is_admin' => true,
    ]]);
}

// ---------- Store ops ----------
function buildInsertColumns(array $meta, array $value): array {
    // Build column => value pairs for INSERT/UPSERT.
    $cols = [];

    // Extra indexed columns extracted from JSON
    foreach ($meta['extra'] as $jsonField => $col) {
        $v = $value[$jsonField] ?? null;
        if (is_bool($v)) $v = $v ? 1 : 0;
        $cols[$col] = $v;
    }

    // Key column (unless autoinc + missing)
    $keyCol = $meta['key_col'];
    $keySrc = $meta['key_src'];
    if ($meta['autoinc']) {
        if (isset($value[$keySrc])) {
            $cols[$keyCol] = (int)$value[$keySrc];
        }
        // else: leave it out → SQLite assigns
    } else {
        if (!isset($value[$keySrc]) || $value[$keySrc] === '' || $value[$keySrc] === null) {
            fail("Missing key '$keySrc' for store", 400);
        }
        $cols[$keyCol] = (string)$value[$keySrc];
    }

    // The full JSON payload
    $cols['data'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $cols;
}

function upsertOne(PDO $pdo, array $meta, array $value): int|string {
    $cols = buildInsertColumns($meta, $value);
    $names = array_keys($cols);
    $placeholders = array_map(fn($n) => ':' . $n, $names);

    $sql = sprintf(
        'INSERT OR REPLACE INTO %s (%s) VALUES (%s)',
        $meta['table'],
        implode(',', $names),
        implode(',', $placeholders)
    );
    $stmt = $pdo->prepare($sql);
    foreach ($cols as $n => $v) {
        $stmt->bindValue(':' . $n, $v, is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    $stmt->execute();

    if ($meta['autoinc']) {
        return isset($cols[$meta['key_col']]) ? (int)$cols[$meta['key_col']] : (int)$pdo->lastInsertId();
    }
    return (string)$cols[$meta['key_col']];
}

// ---------- WRITE actions — admin only ----------

function actionPut(): void {
    requireAdmin();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    $value = $b['value'] ?? null;
    if (!is_array($value)) fail('value must be an object', 400);
    $meta = storeMeta($store);

    $key = upsertOne(db(), $meta, $value);
    out(['ok' => true, 'data' => $key]);
}

function actionBulkPut(): void {
    requireAdmin();
    $b = readBody();
    $store  = (string)($b['store'] ?? '');
    $values = $b['values'] ?? [];
    if (!is_array($values)) fail('values must be an array', 400);
    $meta = storeMeta($store);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $count = 0;
        foreach ($values as $v) {
            if (!is_array($v)) continue;
            upsertOne($pdo, $meta, $v);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fail('bulkPut failed: ' . $e->getMessage(), 500);
    }
    out(['ok' => true, 'data' => $count]);
}

function actionClear(): void {
    requireAdmin();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    $meta = storeMeta($store);

    db()->prepare("DELETE FROM {$meta['table']}")->execute();
    out(['ok' => true]);
}

/**
 * Sterge selectiv din data_set doar perioadele (an, luna) indicate.
 * Body: { store: 'dataSet', periods: [ {an: 2026, luna: 1}, ... ] }
 * Foloseste indexul idx_ds_an_luna.
 */
function actionClearPeriods(): void {
    requireAdmin();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    if ($store !== 'dataSet') fail('clearPeriods este permis doar pentru dataSet', 400);
    $meta = storeMeta($store);

    $periods = $b['periods'] ?? null;
    if (!is_array($periods) || count($periods) === 0) fail('periods (lista {an, luna}) este obligatoriu', 400);
    if (count($periods) > 240) fail('prea multe perioade', 400);

    $conds = [];
    $params = [];
    foreach ($periods as $i => $p) {
        $an   = (int)($p['an'] ?? 0);
        $luna = (int)($p['luna'] ?? 0);
        if ($an < 2000 || $an > 2100 || $luna < 1 || $luna > 12) fail("perioada invalida la pozitia {$i}", 400);
        $conds[] = "(an = :an{$i} AND luna = :lu{$i})";
        $params[":an{$i}"] = $an;
        $params[":lu{$i}"] = $luna;
    }

    $sql = "DELETE FROM {$meta['table']} WHERE " . implode(' OR ', $conds);
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
    $stmt->execute();
    out(['ok' => true, 'deleted' => $stmt->rowCount()]);
}

function actionDelete(): void {
    requireAdmin();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    $key   = $b['key'] ?? null;
    if ($key === null || $key === '') fail('key required', 400);
    $meta = storeMeta($store);

    $stmt = db()->prepare("DELETE FROM {$meta['table']} WHERE {$meta['key_col']} = :k");
    $stmt->bindValue(':k', $meta['autoinc'] ? (int)$key : (string)$key,
                     $meta['autoinc'] ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    out(['ok' => true]);
}

// ---------- READ actions — any authenticated user ----------

function actionGet(): void {
    requireAuth();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    $key   = $b['key'] ?? null;
    if ($key === null || $key === '') fail('key required', 400);
    $meta = storeMeta($store);

    $stmt = db()->prepare("SELECT data FROM {$meta['table']} WHERE {$meta['key_col']} = :k LIMIT 1");
    $stmt->bindValue(':k', $meta['autoinc'] ? (int)$key : (string)$key,
                     $meta['autoinc'] ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        out(['ok' => true, 'data' => null]);
    }
    out(['ok' => true, 'data' => json_decode($row['data'], true)]);
}

function actionGetAll(): void {
    requireAuth();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    $meta = storeMeta($store);

    $stmt = db()->query("SELECT data FROM {$meta['table']}");
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = json_decode($r['data'], true);
    }
    out(['ok' => true, 'data' => $rows]);
}

function actionCount(): void {
    requireAuth();
    $b = readBody();
    $store = (string)($b['store'] ?? '');
    $meta = storeMeta($store);

    $row = db()->query("SELECT COUNT(*) AS c FROM {$meta['table']}")->fetch();
    out(['ok' => true, 'data' => (int)($row['c'] ?? 0)]);
}

// ---------- Admin: user management ----------
// All admin endpoints require an authenticated admin user. They operate on
// the global users table (not tenant-scoped). Last-admin and self-action
// protections prevent locking out the system.

function actionUsersList(): void {
    requireAdmin();
    $stmt = db()->query('SELECT id, email, name, is_admin, created_at FROM users ORDER BY id');
    $rows = [];
    while ($r = $stmt->fetch()) {
        $rows[] = [
            'id'         => (int)$r['id'],
            'email'      => $r['email'],
            'name'       => $r['name'] ?? null,
            'is_admin'   => (bool)$r['is_admin'],
            'created_at' => $r['created_at'],
        ];
    }
    out(['ok' => true, 'data' => $rows]);
}

function actionUsersCreate(): void {
    requireAdmin();
    $b = readBody();
    $email   = trim((string)($b['email'] ?? ''));
    $pass    = (string)($b['password'] ?? '');
    $name    = isset($b['name']) ? trim((string)$b['name']) : null;
    if ($name === '') $name = null;
    $isAdmin = !empty($b['is_admin']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Email invalid', 400);
    if (strlen($pass) < 8) fail('Parola trebuie să aibă cel puțin 8 caractere', 400);

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    try {
        $stmt = db()->prepare('INSERT INTO users (email, password_hash, name, is_admin) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $hash, $name, $isAdmin ? 1 : 0]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) fail('Există deja un cont cu acest email', 409);
        throw $e;
    }
    out(['ok' => true, 'data' => [
        'id'       => (int)db()->lastInsertId(),
        'email'    => $email,
        'name'     => $name,
        'is_admin' => $isAdmin,
    ]]);
}

function actionUsersUpdate(): void {
    $me = requireAdmin();
    $b = readBody();
    $id      = (int)($b['id'] ?? 0);
    $email   = isset($b['email'])    ? trim((string)$b['email']) : null;
    $name    = array_key_exists('name', $b) ? (is_string($b['name']) ? trim($b['name']) : '') : null;
    $isAdmin = isset($b['is_admin']) ? !empty($b['is_admin'])    : null;
    if ($id <= 0) fail('id invalid', 400);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email, name, is_admin FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $cur = $stmt->fetch();
    if (!$cur) fail('Utilizator inexistent', 404);

    // Self-demote protection
    if ($id === $me && $isAdmin === false) {
        fail('Nu te poți retrograda pe tine însuți. Cere altui admin să o facă.', 409);
    }
    // Last-admin protection (when demoting an admin)
    if ($isAdmin === false && (int)$cur['is_admin'] === 1) {
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        if ($adminCount <= 1) {
            fail('Nu poți retrograda ultimul administrator. Promovează întâi alt utilizator.', 409);
        }
    }

    $updates = [];
    $params  = [];
    if ($email !== null && $email !== $cur['email']) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Email invalid', 400);
        $updates[] = 'email = ?';
        $params[]  = $email;
    }
    if ($name !== null) {
        $newName = ($name === '') ? null : $name;
        if ($newName !== $cur['name']) {
            $updates[] = 'name = ?';
            $params[]  = $newName;
        }
    }
    if ($isAdmin !== null && (int)$isAdmin !== (int)$cur['is_admin']) {
        $updates[] = 'is_admin = ?';
        $params[]  = $isAdmin ? 1 : 0;
    }

    if (empty($updates)) {
        out(['ok' => true, 'data' => null]);
    }

    $params[] = $id;
    try {
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) fail('Există deja un cont cu acest email', 409);
        throw $e;
    }

    // Sync session if the admin updated their own data
    if ($id === $me) {
        if ($email   !== null) $_SESSION['email']    = $email;
        if ($name    !== null) $_SESSION['name']     = ($name === '') ? null : $name;
        if ($isAdmin !== null) $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
    }

    out(['ok' => true]);
}

function actionUsersPasswd(): void {
    requireAdmin();
    $b = readBody();
    $id   = (int)($b['id'] ?? 0);
    $pass = (string)($b['password'] ?? '');
    if ($id <= 0) fail('id invalid', 400);
    if (strlen($pass) < 8) fail('Parola trebuie să aibă cel puțin 8 caractere', 400);

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $id]);
    if ($stmt->rowCount() === 0) fail('Utilizator inexistent', 404);
    out(['ok' => true]);
}

function actionUsersDelete(): void {
    $me = requireAdmin();
    $b = readBody();
    $id = (int)($b['id'] ?? 0);
    if ($id <= 0) fail('id invalid', 400);

    if ($id === $me) {
        fail('Nu te poți șterge pe tine însuți.', 409);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $cur = $stmt->fetch();
    if (!$cur) fail('Utilizator inexistent', 404);

    if ((int)$cur['is_admin'] === 1) {
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        if ($adminCount <= 1) fail('Nu poți șterge ultimul administrator.', 409);
    }

    // FK CASCADE removes all data owned by this user across the 6 tenant tables.
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    out(['ok' => true]);
}

// ---------- Dispatch ----------
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':         actionLogin();        break;
        case 'logout':        actionLogout();       break;
        case 'me':            actionMe();           break;
        case 'status':        actionStatus();       break;
        case 'bootstrap':     actionBootstrap();    break;
        case 'put':           actionPut();          break;
        case 'bulkPut':       actionBulkPut();      break;
        case 'get':           actionGet();          break;
        case 'getAll':        actionGetAll();       break;
        case 'count':         actionCount();        break;
        case 'clear':         actionClear();        break;
        case 'clearPeriods':  actionClearPeriods(); break;
        case 'delete':        actionDelete();       break;
        case 'users.list':    actionUsersList();    break;
        case 'users.create':  actionUsersCreate();  break;
        case 'users.update':  actionUsersUpdate();  break;
        case 'users.passwd':  actionUsersPasswd();  break;
        case 'users.delete':  actionUsersDelete();  break;
        default:              fail('Unknown action', 404);
    }
} catch (Throwable $e) {
    error_log('[emag-api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    fail('Server error: ' . $e->getMessage(), 500);
}
