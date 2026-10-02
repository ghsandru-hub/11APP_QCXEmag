<?php
/**
 * EcR Deconturi eMag — User CLI
 * ------------------------------------------------------------------
 * Admin-only utility. Run from shell on the server:
 *
 *   php cli_user.php create   <email> [password]    [--admin]
 *   php cli_user.php list
 *   php cli_user.php passwd   <email> [new-password]
 *   php cli_user.php delete   <email>
 *
 * If [password] is omitted, the script prompts interactively (input hidden).
 *
 * SECURITY: do NOT expose this file via the web. It runs only under SAPI = cli.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden — CLI only.\n");
}

// Reuse schema setup from api.php by including it in a non-dispatching way.
// We can't include api.php directly because it dispatches on $_GET. Instead
// we duplicate the small bootstrap.

const DB_DIR  = __DIR__ . '/../private_mkpemag/data';
const DB_FILE = __DIR__ . '/../private_mkpemag/data/emag.db';

function err(string $msg, int $code = 1): never {
    fwrite(STDERR, "ERROR: $msg\n");
    exit($code);
}
function info(string $msg): void {
    fwrite(STDOUT, $msg . "\n");
}

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (!is_dir(DB_DIR)) {
        if (!@mkdir(DB_DIR, 0755, true) && !is_dir(DB_DIR)) err('Cannot create data dir');
    }
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    ensureSchema($pdo);
    return $pdo;
}

function ensureSchema(PDO $pdo): void {
    // Same schema as api.php — kept in sync deliberately.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            email         TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            is_admin      INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
    // Note: full schema is created by api.php on first request. The CLI
    // only needs the users table to do its job. Other tables are created
    // lazily.
}

function promptPassword(string $label = 'Password: '): string {
    if (function_exists('readline') && stream_isatty(STDIN)) {
        // Disable echo via stty if available
        $hidden = false;
        if (DIRECTORY_SEPARATOR !== '\\') {
            @system('stty -echo 2>/dev/null', $rc);
            $hidden = ($rc === 0);
        }
        fwrite(STDOUT, $label);
        $line = fgets(STDIN);
        if ($hidden) {
            @system('stty echo 2>/dev/null');
            fwrite(STDOUT, "\n");
        }
        return rtrim($line === false ? '' : $line, "\r\n");
    }
    fwrite(STDOUT, $label);
    return rtrim((string)fgets(STDIN), "\r\n");
}

// ---------- Commands ----------
function cmdCreate(array $args): void {
    $isAdmin = false;
    $args = array_values(array_filter($args, function ($a) use (&$isAdmin) {
        if ($a === '--admin') { $isAdmin = true; return false; }
        return true;
    }));
    $email = $args[0] ?? null;
    $pass  = $args[1] ?? null;

    if (!$email) err("Usage: cli_user.php create <email> [password] [--admin]");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err("Invalid email: $email");

    if ($pass === null) {
        $pass = promptPassword('Password: ');
        $confirm = promptPassword('Confirm:  ');
        if ($pass !== $confirm) err('Passwords do not match');
    }
    if (strlen($pass) < 8) err('Password must be at least 8 characters');

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $pdo = db();
    try {
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, is_admin) VALUES (?, ?, ?)');
        $stmt->execute([$email, $hash, $isAdmin ? 1 : 0]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) err("User already exists: $email");
        throw $e;
    }
    info(sprintf('OK — created user #%d <%s>%s', $pdo->lastInsertId(), $email, $isAdmin ? ' (admin)' : ''));
}

function cmdList(): void {
    $stmt = db()->query('SELECT id, email, is_admin, created_at FROM users ORDER BY id');
    $rows = $stmt->fetchAll();
    if (!$rows) { info('(no users)'); return; }
    printf("%-4s  %-32s  %-6s  %s\n", 'ID', 'EMAIL', 'ADMIN', 'CREATED AT');
    printf("%s\n", str_repeat('-', 78));
    foreach ($rows as $r) {
        printf("%-4d  %-32s  %-6s  %s\n",
            $r['id'], $r['email'],
            $r['is_admin'] ? 'yes' : 'no',
            $r['created_at']);
    }
}

function cmdPasswd(array $args): void {
    $email = $args[0] ?? null;
    $pass  = $args[1] ?? null;
    if (!$email) err("Usage: cli_user.php passwd <email> [new-password]");

    if ($pass === null) {
        $pass = promptPassword('New password: ');
        $confirm = promptPassword('Confirm:      ');
        if ($pass !== $confirm) err('Passwords do not match');
    }
    if (strlen($pass) < 8) err('Password must be at least 8 characters');

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $email]);
    if ($stmt->rowCount() === 0) err("No user with email: $email");
    info("OK — password updated for $email");
}

function cmdDelete(array $args): void {
    $email = $args[0] ?? null;
    if (!$email) err("Usage: cli_user.php delete <email>");
    $stmt = db()->prepare('DELETE FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->rowCount() === 0) err("No user with email: $email");
    info("OK — deleted user $email (and all their data via CASCADE)");
}

// ---------- Dispatch ----------
$argv0 = array_shift($argv); // script name
$cmd = array_shift($argv);

switch ($cmd) {
    case 'create': cmdCreate($argv);  break;
    case 'list':   cmdList();         break;
    case 'passwd': cmdPasswd($argv);  break;
    case 'delete': cmdDelete($argv);  break;
    default:
        info("EcR Deconturi eMag — User CLI");
        info("");
        info("Usage:");
        info("  php cli_user.php create <email> [password] [--admin]");
        info("  php cli_user.php list");
        info("  php cli_user.php passwd <email> [new-password]");
        info("  php cli_user.php delete <email>");
        info("");
        info("If [password] is omitted, you'll be prompted interactively.");
        exit($cmd === null ? 0 : 1);
}
