<?php
/**
 * EcR Deconturi eMag — Front Controller
 * Build: 2026.10.04.1 — tabele detaliate configurabile
 * ------------------------------------------------------------------
 * Session gate. Unauthenticated visitors only see a tiny login (or
 * first-time setup) page. The full application HTML is served only
 * to authenticated users.
 *
 * Session cookie config MUST stay in sync with api.php so they share
 * the same session.
 */

declare(strict_types=1);

// ---------- Session config (must match api.php) ----------
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

$loggedIn = isset($_SESSION['uid']);

// ---------- Auth gate: serve full app or mini login ----------
if (!$loggedIn) {
    // Decide whether to render the bootstrap (first-admin setup) form
    // or the regular login form. This check is server-side and read-only;
    // the actual user creation / login still goes through api.php.
    $needsBootstrap = false;
    $dbFile = __DIR__ . '/../private_mkpemag/data/emag.db';

    if (file_exists($dbFile)) {
        try {
            $pdo = new PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            if ($stmt && $stmt->fetch()) {
                $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $needsBootstrap = ($count === 0);
            } else {
                // Schema not yet created on this DB file
                $needsBootstrap = true;
            }
            $pdo = null;
        } catch (Throwable $e) {
            // Can't read DB — fall back to login mode; api.php will surface real errors
            $needsBootstrap = false;
        }
    } else {
        // Fresh deploy — DB will be created by api.php on first call
        $needsBootstrap = true;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    $title    = $needsBootstrap ? 'Configurare inițială' : 'Autentificare';
    $btnText  = $needsBootstrap ? 'Creează cont admin'   : 'Intră în cont';
    $autoCpw  = $needsBootstrap ? 'new-password'         : 'current-password';
    $jsSetup  = $needsBootstrap ? 'true'                 : 'false';
    ?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>EcR Deconturi · <?= htmlspecialchars($title) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #f5f6f7; --card: #ffffff;
  --text: #1F4788; --muted: #64748b;
  --border: #e2e5e8; --field-bg: #f8fafc;
  --brand: #1F4788; --brand-hover: #2A5899;
  --teal: #2D8B8E;
  --err-bg: rgba(220,38,38,.08);
  --err-fg: #b91c1c;
  --err-bd: rgba(220,38,38,.25);
  --warn-bg: rgba(245,158,11,.10);
  --warn-fg: #92400e;
  --warn-bd: rgba(245,158,11,.35);
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #0d1117; --card: #1e293b;
    --text: #e2e8f0; --muted: #94a3b8;
    --border: #334155; --field-bg: #172033;
    --err-fg: #fca5a5; --err-bd: rgba(248,113,113,.30);
    --warn-fg: #fcd34d; --warn-bd: rgba(245,158,11,.35);
  }
}
html, body { min-height: 100vh; background: var(--bg); color: var(--text); }
body {
  font-family: 'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.card {
  width: 100%; max-width: 380px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 28px 26px 22px;
  box-shadow: 0 10px 25px rgba(15,23,42,.10), 0 4px 10px rgba(15,23,42,.04);
}
.brand {
  font-size: 13px; font-weight: 700;
  margin-bottom: 18px; letter-spacing: -0.01em;
}
.brand span { color: var(--teal); }
h1 {
  font-size: 20px; font-weight: 700;
  margin: 0 0 4px; letter-spacing: -0.01em;
}
.sub { font-size: 13px; color: var(--muted); margin: 0 0 22px; }
.banner {
  background: var(--warn-bg);
  border: 1px solid var(--warn-bd);
  color: var(--warn-fg);
  padding: 10px 12px; border-radius: 6px;
  font-size: 12px; line-height: 1.5;
  margin-bottom: 14px;
}
.banner strong { font-weight: 700; }
.err {
  background: var(--err-bg);
  border: 1px solid var(--err-bd);
  color: var(--err-fg);
  padding: 9px 12px; border-radius: 6px;
  font-size: 12px;
  margin-bottom: 14px;
  display: none;
}
.err.show { display: block; }
.field { margin-bottom: 14px; }
label {
  display: block;
  font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--muted); margin-bottom: 6px;
}
input {
  width: 100%; padding: 10px 12px;
  font: 14px inherit; color: var(--text);
  background: var(--field-bg);
  border: 1px solid var(--border);
  border-radius: 8px; outline: none;
  transition: border-color .15s, box-shadow .15s;
}
input:focus {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(45,139,142,.18);
}
button {
  width: 100%; padding: 11px 14px;
  font: 600 14px inherit; color: #fff;
  background: var(--brand);
  border: none; border-radius: 8px;
  cursor: pointer;
  transition: background .15s;
  margin-top: 4px;
}
button:hover:not(:disabled) { background: var(--brand-hover); }
button:disabled { opacity: 0.6; cursor: not-allowed; }
.foot {
  margin-top: 18px; padding-top: 14px;
  border-top: 1px solid var(--border);
  font-size: 11px; color: var(--muted);
  text-align: center;
}
</style>
</head>
<body>
<form class="card" id="f" autocomplete="on" novalidate>
  <div class="brand"><span>smart</span>BIZ Copilot</div>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p class="sub">EcR Deconturi · eMag Marketplace</p>

  <?php if ($needsBootstrap): ?>
    <div class="banner">
      <strong>Configurare inițială.</strong> Acesta e primul cont și va fi
      <b>administrator</b>. Folosește un email și o parolă pe care le poți
      reține — nu există recuperare automată.
    </div>
  <?php endif; ?>

  <div class="err" id="err" role="alert"></div>

  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username" required spellcheck="false">
  </div>
  <div class="field">
    <label for="pw">Parolă</label>
    <input id="pw" name="password" type="password" autocomplete="<?= $autoCpw ?>" required>
  </div>
  <?php if ($needsBootstrap): ?>
    <div class="field">
      <label for="pw2">Confirmă parola</label>
      <input id="pw2" name="confirm" type="password" autocomplete="new-password" required>
    </div>
  <?php endif; ?>

  <button type="submit" id="btn"><?= htmlspecialchars($btnText) ?></button>

  <?php if (!$needsBootstrap): ?>
    <div class="foot">Nu ai cont? Conturile sunt create de administrator.</div>
  <?php else: ?>
    <div class="foot">După creare vei fi autentificat automat.</div>
  <?php endif; ?>
</form>

<script>
(function () {
  var SETUP = <?= $jsSetup ?>;
  var f = document.getElementById('f');
  var err = document.getElementById('err');
  var btn = document.getElementById('btn');
  var origText = btn.textContent.trim();

  function showErr(msg) {
    err.textContent = msg;
    err.classList.add('show');
  }

  f.addEventListener('submit', async function (e) {
    e.preventDefault();
    var email = document.getElementById('email').value.trim();
    var pw = document.getElementById('pw').value;
    if (!email || !pw) return;

    if (SETUP) {
      var pw2 = document.getElementById('pw2').value;
      if (pw !== pw2) { showErr('Parolele nu coincid'); return; }
      if (pw.length < 8) { showErr('Parola trebuie să aibă cel puțin 8 caractere'); return; }
    }

    btn.disabled = true;
    btn.textContent = SETUP ? 'Se creează contul...' : 'Se autentifică...';
    err.classList.remove('show');

    try {
      var res = await fetch('api.php?action=' + (SETUP ? 'bootstrap' : 'login'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: pw })
      });
      var j;
      try { j = await res.json(); }
      catch (_) { throw new Error('Răspuns invalid de la server (HTTP ' + res.status + ')'); }
      if (!j.ok) throw new Error(j.error || ('HTTP ' + res.status));
      // Session cookie set — reload to enter the full application
      window.location.replace(window.location.pathname);
    } catch (ex) {
      showErr(ex.message || 'Eroare');
      btn.disabled = false;
      btn.textContent = origText;
    }
  });

  setTimeout(function () {
    var emailEl = document.getElementById('email');
    if (emailEl) emailEl.focus();
  }, 30);
})();
</script>
</body>
</html>
    <?php
    exit;
}

// ---------- Authenticated: serve full application HTML below ----------
// Send a few defensive headers; the rest is a static HTML payload.
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>smartBIZ Copilot · EcR Deconturi eMag · 2026.10.04.1</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<link rel="stylesheet" href="configurable-data-table.css?v=2026.10.04.1">
<script src="configurable-data-table.js?v=2026.10.04.1"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  /* ====== AiAll Design System tokens (smartBIZ) ====== */
  /* Brand colors */
  --sb-brand-navy:    #1F4788;
  --sb-brand-navy-2:  #2A5899;
  --sb-brand-navy-3:  #4A90B5;
  --sb-brand-teal:    #2D8B8E;
  --sb-brand-amber:   #F59E0B;
  /* Surfaces — light mode default */
  --sb-bg-page:    #f5f6f7;
  --sb-bg-card:    #ffffff;
  --sb-bg-subtle:  #eef0f2;
  --sb-bg-header:  #ffffff;
  /* Text */
  --sb-text:       #1F4788;
  --sb-text-muted: #64748b;
  --sb-text-on-brand: #ffffff;
  /* Borders */
  --sb-border: #e2e5e8;
  --sb-border-subtle: #eef1f4;
  /* Tabs / segments */
  --sb-tab-bg: #f1f3f5;
  /* Shadows */
  --sb-shadow-card: 0 1px 3px rgba(15,23,42,.08), 0 1px 2px rgba(15,23,42,.04);
  --sb-shadow-elevated: 0 10px 25px rgba(15,23,42,.10), 0 4px 10px rgba(15,23,42,.04);
  /* Radius */
  --sb-radius-sm: 6px;
  --sb-radius-md: 10px;
  --sb-radius-lg: 14px;
  --sb-radius-xl: 20px;
  /* Typography */
  --sb-font-sans: 'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --sb-font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* ====== smartBIZ Copilot brand palette (legacy — used in document templates) ====== */
  --brand: #0891b2;
  --brand-dark: #0e7490;
  --brand-darker: #155e75;
  --brand-light: #cffafe;
  --brand-50: #ecfeff;
  --brand-accent: #14b8a6;
  --brand-accent-dark: #0d9488;
  --bg: #f1f5f9;
  --surface: #ffffff;
  --surface-2: #f8fafc;
  --surface-paper: #ffffff;     /* document template background (centralizator/decont) */
  --border: #e2e8f0;
  --border-strong: #cbd5e1;
  --text: #0f172a;
  --text-muted: #64748b;
  --text-subtle: #94a3b8;
  --primary: var(--brand);
  --primary-hover: var(--brand-dark);
  --primary-light: var(--brand-50);
  --success: #059669;
  --success-bg: #d1fae5;
  --error: #dc2626;
  --error-bg: #fee2e2;
  --warn: #d97706;
  --warn-bg: #fef3c7;
  --shadow: 0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
  --shadow-md: 0 4px 6px -1px rgba(15,23,42,.08), 0 2px 4px -1px rgba(15,23,42,.04);
  --shadow-lg: 0 10px 15px -3px rgba(15,23,42,.08), 0 4px 6px -4px rgba(15,23,42,.04);
}

/* ====== Dark theme overrides — applies to entire application ======
   Palette taken from analiza_smartbiz_mobility (smartBIZ Mobility report):
   page-bg #0d1117 · body-bg #111827 · body-soft #172033 · card #1e293b · line #334155 */
[data-theme="dark"] {
  /* SmartBIZ shell tokens */
  --sb-bg-page:    #0d1117;
  --sb-bg-card:    #1e293b;
  --sb-bg-subtle:  #172033;
  --sb-bg-header:  #111827;
  --sb-text:       #e2e8f0;
  --sb-text-muted: #94a3b8;
  --sb-border: #334155;
  --sb-border-subtle: #28324a;
  --sb-tab-bg: #172033;
  --sb-shadow-card: 0 1px 3px rgba(0,0,0,.4), 0 1px 2px rgba(0,0,0,.2);

  /* Legacy app tokens — dark variants */
  --bg: #0d1117;                 /* page background */
  --surface: #1e293b;            /* card / panel background */
  --surface-2: #243047;          /* slightly lighter surface for nested elements */
  --surface-paper: #1e293b;      /* "paper" surface for document templates */
  --border: #334155;
  --border-strong: #475569;
  --text: #e2e8f0;
  --text-muted: #94a3b8;
  --text-subtle: #64748b;
  --shadow:    0 1px 3px rgba(0,0,0,.4), 0 1px 2px rgba(0,0,0,.2);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,.5), 0 2px 4px -1px rgba(0,0,0,.3);
  --shadow-lg: 0 10px 25px rgba(0,0,0,.6), 0 4px 10px rgba(0,0,0,.3);

  /* Status backgrounds — desaturated for dark UI */
  --success-bg: #064e3b;
  --error-bg:   #7f1d1d;
  --warn-bg:    #78350f;
}
[data-theme="light"] {
  /* Provide explicit values that mirror :root so theme=light is also valid */
  --surface-2: #f8fafc;
  --surface-paper: #ffffff;
  --border-strong: #cbd5e1;
}

/* Light theme — discreet header background (only the background; rest of the
   header rule is unchanged). */
[data-theme="light"] .sb-header {
  background: linear-gradient(180deg, #fbfcfe 0%, #eef2f8 100%);
}

/* Light theme — vertical menu (sidebar) re-toned to match the light theme.
   Background changes from dark navy to a soft light surface; text/state
   colors are re-mapped so the menu remains legible on the new background.
   Dark theme is untouched. */
[data-theme="light"] #sidebar {
  background: linear-gradient(180deg, #eef2f8 0%, #e1e8f1 100%);
  border-right: 1px solid #d3dbe5;
}
[data-theme="light"] .brand { border-bottom: 1px solid rgba(31, 71, 136, 0.12); }
[data-theme="light"] .brand-title { color: #1F4788; }
[data-theme="light"] .brand-sub   { color: #64748b; }
[data-theme="light"] .nav-section-label { color: #64748b; }
[data-theme="light"] .nav-item { color: #475569; }
[data-theme="light"] .nav-item:hover {
  background: rgba(45, 139, 142, 0.10);
  color: #2D8B8E;
}
[data-theme="light"] .nav-item.active {
  background: linear-gradient(90deg, rgba(45, 139, 142, 0.15), rgba(31, 71, 136, 0.08));
  color: #1F4788;
  box-shadow: inset 3px 0 0 #2D8B8E;
}
[data-theme="light"] .sidebar-toggle {
  background: rgba(31, 71, 136, 0.06);
  border: 1px solid rgba(31, 71, 136, 0.12);
  color: #1F4788;
}
[data-theme="light"] .sidebar-toggle:hover {
  background: rgba(31, 71, 136, 0.12);
  color: #1F4788;
}

/* ====== Dark theme overrides for document templates (centralizator/decont) ======
   These keep the structural design intact while making text readable on dark paper. */
[data-theme="dark"] .centralizator,
[data-theme="dark"] .decont-page {
  color: #e2e8f0;
}
/* Document body internal text — visible on dark paper */
[data-theme="dark"] .cz-num-deconturi .lbl,
[data-theme="dark"] .cz-num-deconturi .val,
[data-theme="dark"] .cz-fil-lbl,
[data-theme="dark"] .cz-fil-val select { color: #1f2937; }   /* stay dark on yellow filter pill */

[data-theme="dark"] .cz-foot-cell .lbl { color: #e2e8f0; }
[data-theme="dark"] .cz-currency-tag { color: #e2e8f0; }
[data-theme="dark"] .cz-doc-ref { color: #94a3b8; }
[data-theme="dark"] .cz-anexa-col { color: #93c5fd; }

/* Titles in document — lighter navy for dark bg readability */
[data-theme="dark"] .cz-doc-title h2,
[data-theme="dark"] .cz-doc-title-period,
[data-theme="dark"] .decont-doc-title h2,
[data-theme="dark"] .cz-title-l h1,
[data-theme="dark"] .cz-title-r h1,
[data-theme="dark"] .cz-pivot tfoot .cz-total-yellow .cz-total-label,
[data-theme="dark"] .cz-pivot-2 tfoot .cz-grand-total td { color: #93c5fd; }
[data-theme="dark"] .decont-grand-total .lbl,
[data-theme="dark"] .decont-grand-total .val { color: #5eead4; }
[data-theme="dark"] .decont-grand-total {
  background: linear-gradient(90deg, transparent, rgba(45, 139, 142, 0.22));
  border-top-color: #2D8B8E;
  border-bottom-color: #2D8B8E;
}

/* Hide print buttons in dark theme (printing assumes light surfaces) */
[data-theme="dark"] #czPrint,
[data-theme="dark"] #dPrint,
[data-theme="dark"] #jPrint { display: none !important; }

/* Section dividers — lighter blue */
[data-theme="dark"] .cz-section-divider { border-top-color: #4A90B5; }

/* Yellow totals stay yellow (decorative brand color works on dark bg too) */
[data-theme="dark"] .cz-pivot tfoot .cz-total-yellow td { background: #422006; }
[data-theme="dark"] .cz-pivot tfoot .cz-total-yellow td,
[data-theme="dark"] .cz-pivot tfoot .cz-total-yellow .cz-total-label { color: #fef3c7; }
[data-theme="dark"] .cz-pivot tfoot .cz-total-emph td {
  background: #422006;
  border-top-color: #fbbf24;
  border-bottom-color: #fbbf24;
}

/* Tan subtotals adapt to dark */
[data-theme="dark"] .cz-pivot-2 .cz-subtotal td { background: #3e3520; color: #d6c79b; border-color: #5a4f30; }
[data-theme="dark"] .cz-pivot-2 .cz-subtotal td:first-child { color: #d6c79b; }
[data-theme="dark"] .cz-pivot-2 .cz-subtotal:hover td { background: #3e3520; }

/* Issuer name stays brand teal */
[data-theme="dark"] .cz-foot-cell .cz-issuer-name,
[data-theme="dark"] .cz-foot-cell .cz-line { border-bottom-color: #4a5568; }
html, body { height: 100%; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background: var(--bg);
  color: var(--text);
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
a { color: var(--primary); text-decoration: none; }
a:hover { text-decoration: underline; }

/* ========== App-level layout ========== */
#app {
  display: grid;
  grid-template-columns: 240px 1fr;
  grid-template-rows: 64px 1fr;
  grid-template-areas:
    "appbar appbar"
    "sidebar main";
  min-height: 100vh;
  transition: grid-template-columns .22s ease;
}
#app.sidebar-collapsed {
  grid-template-columns: 60px 1fr;
}

/* ========== SmartBIZ Enterprise Header ========== */
.sb-header {
  grid-area: appbar;
  background: var(--sb-bg-header);
  border-bottom: 1px solid var(--sb-border);
  padding: 8px 18px 8px 9px;
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  font-family: var(--sb-font-sans);
  color: var(--sb-text);
}
.sb-headerInner {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.sb-headerLeft { display: flex; align-items: center; gap: 12px; }

.sb-logo {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #1F4788, #2A5899);
  display: flex; align-items: center; justify-content: center;
  position: relative; flex-shrink: 0;
}
.sb-logoText { font-size: 14px; font-weight: 600; color: #fff; font-family: var(--sb-font-sans); }

.sb-brandTitle {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -.02em;
  line-height: 1.2;
  font-family: var(--sb-font-sans);
}
.sb-brandSmart   { color: #2D8B8E; }
.sb-brandBiz     { color: #1F4788; }
[data-theme="dark"] .sb-brandBiz { color: #4A90B5; }   /* lighter navy for readability on dark bg */
.sb-brandCopilot {
  color: var(--sb-text);
  font-weight: 600;
  margin-left: 6px;
  font-size: 16px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.sb-iconBtn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0; margin-left: 2px;
  background: transparent; border: none;
  color: var(--sb-text-muted);
  cursor: pointer; line-height: 0;
}
.sb-iconBtn:hover { color: #2D8B8E; }

.sb-moduleMeta {
  display: flex; align-items: center; gap: 6px; margin-top: 2px;
}
.sb-moduleDot {
  width: 4px; height: 4px;
  border-radius: 2px;
  background: #2D8B8E;
  flex-shrink: 0;
}
.sb-moduleName {
  font-size: 12px;
  color: var(--sb-text-muted);
  font-weight: 600;
}
.sb-moduleVersion {
  font-size: 9px;
  color: var(--sb-text-muted);
  font-family: var(--sb-font-mono);
}

.sb-headerRight {
  display: flex; flex-direction: row;
  align-items: center; gap: 14px;
  flex-shrink: 0;
}
.sb-headerRightCol {
  display: flex; flex-direction: column;
  align-items: flex-end; gap: 4px;
}
.sb-themeToggle {
  display: flex;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--sb-border);
  cursor: pointer;
  padding: 0;
  background: none;
}
.sb-themeSeg {
  padding: 6px 10px;
  display: flex; align-items: center;
  background: transparent;
}
[data-theme="light"] .sb-themeSeg.sb-light-seg { background: #1F4788; }
[data-theme="light"] .sb-themeSeg.sb-light-seg svg { stroke: #fff; }
[data-theme="light"] .sb-themeSeg.sb-dark-seg svg { stroke: var(--sb-text-muted); fill: none; }
[data-theme="dark"] .sb-themeSeg.sb-dark-seg { background: #1F4788; }
[data-theme="dark"] .sb-themeSeg.sb-dark-seg svg { fill: #F59E0B; stroke: #F59E0B; }
[data-theme="dark"] .sb-themeSeg.sb-light-seg svg { stroke: var(--sb-text-muted); }

.sb-copyright { font-size: 9px; color: var(--sb-text-muted); font-weight: 600; }
.sb-copyright strong { font-weight: 600; color: #1F4788; }

/* ========== User menu (avatar + dropdown) in header right ========== */
.sb-userMenu {
  position: relative;
  display: inline-block;
}
.sb-userMenu[hidden] { display: none !important; }

.sb-userAvatar {
  position: relative;
  display: inline-flex; align-items: center; justify-content: center;
  width: 38px; height: 38px;
  padding: 0; border: none;
  border-radius: 50%;
  background: linear-gradient(135deg, #1F4788 0%, #2D8B8E 100%);
  color: #fff;
  font: 700 13px/1 var(--sb-font-sans);
  letter-spacing: .02em;
  cursor: pointer;
  transition: transform .15s, box-shadow .15s;
  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 0 0 0 rgba(45,139,142,.4);
}
.sb-userAvatar:hover {
  transform: scale(1.04);
  box-shadow: 0 2px 8px rgba(15,23,42,.18), 0 0 0 4px rgba(45,139,142,.18);
}
.sb-userAvatar[aria-expanded="true"] {
  box-shadow: 0 2px 8px rgba(15,23,42,.18), 0 0 0 4px rgba(45,139,142,.28);
}
.sb-userAvatar-initials { user-select: none; }
.sb-userAvatar-dot {
  position: absolute; right: -1px; bottom: -1px;
  width: 12px; height: 12px;
  border-radius: 50%;
  background: #F59E0B;
  border: 2px solid var(--sb-bg-header);
}
.sb-userAvatar-dot[hidden] { display: none !important; }

.sb-userDropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  min-width: 280px;
  max-width: 320px;
  background: var(--sb-bg-card);
  border: 1px solid var(--sb-border);
  border-radius: var(--sb-radius-lg);
  box-shadow: var(--sb-shadow-elevated);
  padding: 14px 14px 8px;
  z-index: 100;
  animation: sb-fadein .13s ease-out;
}
.sb-userDropdown[hidden] { display: none !important; }
@keyframes sb-fadein {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}

.sb-userDropdown-head {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 10px;
}
.sb-userAvatar-large {
  flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  width: 48px; height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1F4788 0%, #2D8B8E 100%);
  color: #fff;
  font: 700 17px/1 var(--sb-font-sans);
  letter-spacing: .02em;
  user-select: none;
}
.sb-userDropdown-id {
  min-width: 0; flex: 1;
}
.sb-userDropdown-name {
  font-size: 14px; font-weight: 700;
  color: var(--sb-text);
  letter-spacing: -.01em;
  line-height: 1.2;
  margin-bottom: 2px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sb-userDropdown-name[hidden] { display: none !important; }
.sb-userDropdown-email {
  font-size: 12px;
  color: var(--sb-text-muted);
  font-weight: 500;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  line-height: 1.3;
}
.sb-userDropdown-roles {
  display: flex; flex-wrap: wrap; gap: 4px;
  margin-top: 5px;
}
.sb-userRole {
  display: inline-flex; align-items: center;
  padding: 2px 7px;
  font-size: 10px; font-weight: 700;
  border-radius: 999px;
  letter-spacing: .02em;
  white-space: nowrap;
}
.sb-userRole[hidden] { display: none !important; }
.sb-userRole-admin {
  background: rgba(31,71,136,.10);
  color: #1F4788;
  border: 1px solid rgba(31,71,136,.25);
}
.sb-userRole-readonly {
  background: rgba(245,158,11,.14);
  color: #92400e;
  border: 1px solid rgba(245,158,11,.35);
}
[data-theme="dark"] .sb-userRole-admin {
  background: rgba(74,144,181,.18);
  color: #93c5fd;
  border-color: rgba(74,144,181,.35);
}
[data-theme="dark"] .sb-userRole-readonly {
  color: #fcd34d;
  background: rgba(245,158,11,.18);
  border-color: rgba(245,158,11,.35);
}

.sb-userDropdown-divider {
  height: 1px;
  background: var(--sb-border-subtle);
  margin: 8px -14px;
}

.sb-userDropdown-item {
  display: flex; align-items: center; gap: 10px;
  width: 100%;
  padding: 9px 10px;
  border: none; background: transparent;
  border-radius: var(--sb-radius-sm);
  font: 500 13px var(--sb-font-sans);
  color: var(--sb-text);
  cursor: pointer;
  text-align: left;
  transition: background .12s, color .12s;
}
.sb-userDropdown-item:hover {
  background: rgba(220, 38, 38, .08);
  color: #dc2626;
}
[data-theme="dark"] .sb-userDropdown-item:hover {
  background: rgba(248, 113, 113, .12);
  color: #fca5a5;
}
.sb-userDropdown-item svg {
  flex-shrink: 0;
}

/* ========== Login overlay ========== */
.sb-loginOverlay {
  position: fixed; inset: 0; z-index: 1000;
  display: none;
  align-items: center; justify-content: center;
  background: rgba(3, 6, 12, .82);
  backdrop-filter: blur(10px);
  padding: 20px;
}
.sb-loginOverlay.open { display: flex; }
.sb-loginCard {
  width: 100%; max-width: 380px;
  background: var(--sb-bg-card);
  border: 1px solid var(--sb-border);
  border-radius: 16px;
  box-shadow: var(--sb-shadow-elevated);
  padding: 28px 26px 22px;
  font-family: var(--sb-font-sans);
  color: var(--sb-text);
}
.sb-loginBrand {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 18px;
}
.sb-loginBrand .sb-loginBrandText {
  font-size: 13px; font-weight: 700; color: var(--sb-text);
  letter-spacing: -0.01em;
}
.sb-loginBrand .sb-loginBrandText span { color: #2D8B8E; font-weight: 700; }
.sb-loginTitle {
  font-size: 20px; font-weight: 700; margin: 0 0 4px;
  color: var(--sb-text); letter-spacing: -0.01em;
}
.sb-loginSubtitle {
  font-size: 13px; color: var(--sb-text-muted); margin: 0 0 22px;
}
.sb-loginField { margin-bottom: 14px; }
.sb-loginField label {
  display: block;
  font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--sb-text-muted);
  margin-bottom: 6px;
}
.sb-loginField input {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  font-family: var(--sb-font-sans);
  color: var(--sb-text);
  background: var(--sb-bg-page);
  border: 1px solid var(--sb-border);
  border-radius: 8px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.sb-loginField input:focus {
  border-color: #2D8B8E;
  box-shadow: 0 0 0 3px rgba(45, 139, 142, 0.18);
}
.sb-loginBtn {
  width: 100%;
  padding: 11px 14px;
  font-size: 14px; font-weight: 600;
  font-family: var(--sb-font-sans);
  color: #fff;
  background: #1F4788;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background .15s;
  margin-top: 4px;
}
.sb-loginBtn:hover:not(:disabled) { background: #2A5899; }
.sb-loginBtn:disabled { opacity: 0.6; cursor: not-allowed; }
.sb-loginError {
  background: rgba(220, 38, 38, .08);
  color: #b91c1c;
  border: 1px solid rgba(220, 38, 38, .25);
  padding: 9px 12px;
  border-radius: 6px;
  font-size: 12px;
  margin-bottom: 14px;
  display: none;
}
.sb-loginError.show { display: block; }
[data-theme="dark"] .sb-loginError { background: rgba(248, 113, 113, .12); color: #fca5a5; border-color: rgba(248, 113, 113, .3); }

.sb-setupBanner {
  background: rgba(245, 158, 11, .10);
  border: 1px solid rgba(245, 158, 11, .35);
  color: #92400e;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 12px; line-height: 1.5;
  margin-bottom: 14px;
}
.sb-setupBanner[hidden] { display: none; }
.sb-setupBanner strong { font-weight: 700; }
[data-theme="dark"] .sb-setupBanner { background: rgba(245, 158, 11, .14); color: #fcd34d; border-color: rgba(245, 158, 11, .35); }
.sb-loginFoot {
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px solid var(--sb-border-subtle);
  font-size: 11px; color: var(--sb-text-muted);
  text-align: center;
}

/* ========== Info modal "Despre EcR Deconturi" ========== */
.sb-infoOverlay {
  position: fixed; inset: 0;
  background: rgba(3,6,12,.78);
  backdrop-filter: blur(10px);
  display: none; align-items: center; justify-content: center;
  padding: 16px; z-index: 2000;
  font-family: var(--sb-font-sans);
}
.sb-infoOverlay.open { display: flex; }
.sb-infoModal {
  width: min(920px, 100%);
  max-height: 90vh; overflow: auto;
  border-radius: 22px;
  border: 1px solid var(--sb-border);
  background: linear-gradient(180deg, #ffffff, #f4f6f9);
  box-shadow: 0 22px 70px rgba(0,0,0,.12);
}
[data-theme="dark"] .sb-infoModal {
  border: 1px solid rgba(255,255,255,.10);
  background: linear-gradient(180deg, rgba(20,30,50,.96), rgba(14,20,34,.92));
  box-shadow: 0 34px 100px rgba(0,0,0,.65);
}
.sb-infoTop { padding: 18px 20px 14px; border-bottom: 1px solid var(--sb-border); }
.sb-infoTopRow { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.sb-infoKicker { font-size: 12px; letter-spacing: .14em; text-transform: uppercase; font-weight: 600; color: #2D8B8E; }
.sb-infoTitle  { margin: 6px 0 0; font-size: 1.6rem; font-weight: 600; color: var(--sb-text); }
.sb-infoCloseBtn {
  border: 1px solid var(--sb-border);
  background: rgba(0,0,0,.03);
  color: var(--sb-text);
  border-radius: 999px;
  padding: 8px 12px;
  cursor: pointer;
  font-weight: 600; font-size: .82rem;
  display: inline-flex; align-items: center; gap: 8px;
  font-family: var(--sb-font-sans);
}
[data-theme="dark"] .sb-infoCloseBtn {
  border: 1px solid rgba(255,255,255,.14);
  background: rgba(255,255,255,.06);
}
.sb-infoBody { padding: 16px 20px 20px; }
.sb-infoText { margin: 0 0 14px; color: var(--sb-text); opacity: .92; font-weight: 400; font-size: .95rem; line-height: 1.7; }
.sb-infoBullets { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
.sb-infoLi { display: flex; gap: 12px; align-items: flex-start; color: var(--sb-text-muted); font-weight: 400; line-height: 1.65; }
.sb-dot { width: 8px; height: 8px; border-radius: 50%; background: #2D8B8E; margin-top: 9px; flex-shrink: 0; }
.sb-infoResult {
  margin-top: 18px;
  background: #ffffff;
  border: 1px solid var(--sb-border);
  border-radius: 16px;
  padding: 16px;
}
[data-theme="dark"] .sb-infoResult {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.10);
}
.sb-infoResultKicker { font-size: .75rem; letter-spacing: .14em; text-transform: uppercase; font-weight: 600; color: var(--sb-text-muted); margin-bottom: 10px; }
.sb-infoResultText { color: var(--sb-text); font-weight: 400; line-height: 1.7; }
@media (max-width: 520px) {
  .sb-infoModal { border-radius: 18px; }
  .sb-infoTitle { font-size: 1.35rem; }
  .sb-infoTopRow { flex-direction: column; }
  .sb-infoCloseBtn { align-self: flex-start; }
}

#sidebar {
  grid-area: sidebar;
  background: linear-gradient(180deg, #0f172a 0%, #020617 100%);
  color: #e2e8f0;
  padding: 1rem 0;
  border-right: 1px solid #1e293b;
  position: sticky; top: 64px; height: calc(100vh - 64px); overflow-y: auto; overflow-x: hidden;
  transition: width .22s ease;
  display: flex;
  flex-direction: column;
}

/* Sidebar collapse chevron — pinned at the bottom of sidebar inside .sidebar-footer.
   The horizontal line above the chevron is the .sidebar-footer border-top;
   the 10px visual padding-bottom is the bottom value of .sidebar-footer padding. */
.sidebar-footer {
  margin-top: auto;
  border-top: 1px solid rgba(255,255,255,.07);
  padding: 10px .5rem 0;
  display: flex;
  justify-content: flex-end;
  align-items: center;
}
#app.sidebar-collapsed .sidebar-footer {
  justify-content: center;
  padding-left: 0;
  padding-right: 0;
}
[data-theme="light"] .sidebar-footer {
  border-top-color: rgba(31, 71, 136, 0.12);
}
.sidebar-toggle {
  position: static;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.08);
  color: #cbd5e1;
  width: 26px; height: 26px;
  border-radius: 4px;
  cursor: pointer;
  font-size: .85rem;
  display: grid; place-items: center;
  padding: 0;
  transition: background .15s, transform .22s, color .15s;
}
.sidebar-toggle:hover { background: rgba(255,255,255,.12); color: #fff; }
#app.sidebar-collapsed .sidebar-toggle .chev { transform: rotate(180deg); }
.sidebar-toggle .chev { display: inline-block; transition: transform .22s; }

.brand {
  padding: .25rem 1.25rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,.07);
  margin-bottom: .85rem;
  position: relative;
  min-height: 36px;
}
.brand-title {
  font-weight: 700; font-size: 1rem;
  color: #fff;
  letter-spacing: -.01em;
  display: flex; align-items: center; gap: .5rem;
  white-space: nowrap;
  overflow: hidden;
}
.brand-sub { font-size: .72rem; color: #64748b; margin-top: .25rem; font-weight: 500; letter-spacing: .02em; text-transform: uppercase; white-space: nowrap; overflow: hidden; }
.nav-section { padding: 0 .5rem; flex: 1; }
.nav-section-label {
  text-transform: uppercase; font-size: .65rem; letter-spacing: .08em;
  color: #475569; padding: .85rem .75rem 0; font-weight: 600;
  white-space: nowrap; overflow: hidden;
}
.nav-separator {
  height: 1px;
  background: rgba(255,255,255,.07);
  margin: 2px 5px .5rem;
}
[data-theme="light"] .nav-separator {
  background: rgba(31, 71, 136, 0.12);
}
.nav-item {
  display: flex; align-items: center; gap: .75rem;
  padding: .55rem .75rem; margin: .1rem 0;
  border-radius: 6px; cursor: pointer;
  color: #94a3b8; font-size: .875rem;
  font-weight: 500;
  transition: all .15s;
  white-space: nowrap;
  overflow: hidden;
  position: relative;
}
.nav-item:hover { background: rgba(20,184,166,.08); color: #5eead4; }
.nav-item.active {
  background: linear-gradient(90deg, rgba(20,184,166,.18), rgba(8,145,178,.12));
  color: #fff;
  box-shadow: inset 3px 0 0 var(--brand-accent);
}
.nav-icon { font-size: 1.2rem; width: 24px; text-align: center; flex-shrink: 0; }
.nav-label { transition: opacity .15s; }

/* Collapsed state — only icons visible */
#app.sidebar-collapsed .brand-title,
#app.sidebar-collapsed .brand-sub,
#app.sidebar-collapsed .nav-section-label,
#app.sidebar-collapsed .nav-label {
  opacity: 0;
  visibility: hidden;
  width: 0;
  margin: 0;
  padding: 0;
}
#app.sidebar-collapsed .brand {
  padding: .25rem .5rem 1rem;
}
#app.sidebar-collapsed .nav-section { padding: 0 .25rem; }
#app.sidebar-collapsed .nav-item {
  justify-content: center;
  padding: .55rem .35rem;
  gap: 0;
}
#app.sidebar-collapsed .nav-item[style*="padding-left"] {
  padding-left: .35rem !important;     /* even sub-indented items collapse to centered */
}
/* Tooltip on hover when collapsed */
#app.sidebar-collapsed .nav-item:hover::after {
  content: attr(data-label);
  position: absolute;
  left: calc(100% + 8px);
  top: 50%;
  transform: translateY(-50%);
  background: #0f172a;
  color: #fff;
  padding: .35rem .65rem;
  border-radius: 4px;
  font-size: .8rem;
  white-space: nowrap;
  border: 1px solid rgba(255,255,255,.1);
  pointer-events: none;
  z-index: 100;
}

/* ========== Mobile: force sidebar collapsed regardless of class ==========
   On narrow viewports the 240px expanded sidebar eats too much horizontal
   space — apps in this category are functionally unusable. So we reuse the
   `.sidebar-collapsed` styling unconditionally below 768px, and we hide the
   toggle button (toggling has no meaning on mobile). The .sidebar-collapsed
   class state in localStorage (set on desktop) is irrelevant here. */
@media (max-width: 768px) {
  #app {
    grid-template-columns: 60px 1fr !important;
  }
  #app .brand-title,
  #app .brand-sub,
  #app .nav-section-label,
  #app .nav-label {
    opacity: 0 !important;
    visibility: hidden !important;
    width: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  #app .brand { padding: .25rem .5rem 1rem; }
  #app .nav-section { padding: 0 .25rem; }
  #app .nav-item {
    justify-content: center;
    padding: .55rem .35rem;
    gap: 0;
  }
  #app .nav-item[style*="padding-left"] {
    padding-left: .35rem !important;
  }
  /* Hide the expand/collapse toggle entirely — has no purpose on mobile. */
  #app .sidebar-footer,
  #app .sidebar-toggle {
    display: none !important;
  }
}

#main {
  grid-area: main;
  background: var(--bg); min-width: 0;
}
#content { padding: 1.5rem clamp(1rem, 2vw, 2.25rem); width: 100%; }

/* ========== Help panel ==========
   A right-side contextual help panel. When open, the #app grid expands to
   include a third column for the panel and the sidebar collapses to 60px.
   On mobile (≤768px) the panel becomes a fixed overlay instead of a grid
   column to avoid squeezing main content to zero width. */

/* The help button in the topbar — sits between the theme toggle column and
   the user-avatar block. Hidden by default; JS toggles `data-has-help` on
   the body to reveal the button on pages that have help content defined. */
.sb-helpBtn {
  display: none;
  align-items: center; justify-content: center;
  width: 38px; height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1F4788 0%, #2D8B8E 100%);
  color: #fff;
  border: none;
  font-size: 18px; font-weight: 700;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(15,23,42,.15);
  transition: transform .15s ease, box-shadow .15s ease;
  margin-right: 8px;
}
.sb-helpBtn:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(15,23,42,.2); }
.sb-helpBtn:active { transform: scale(0.96); }
body.has-help .sb-helpBtn { display: inline-flex; }
body.help-open .sb-helpBtn {
  background: linear-gradient(135deg, #2D8B8E 0%, #1F4788 100%);
  box-shadow: 0 0 0 3px rgba(45,139,142,.3), 0 4px 12px rgba(15,23,42,.2);
}

/* The help panel itself — grid area `help` on desktop, fixed overlay on
   mobile. Hidden by default; visible only when #app has `.help-open`. */
#helpPanel {
  grid-area: help;
  background: var(--surface);
  border-left: 1px solid var(--border);
  position: sticky; top: 64px; height: calc(100vh - 64px);
  overflow-y: auto;
  padding: 18px 22px 28px;
  display: none;
  font-size: .88rem;
  line-height: 1.55;
  color: var(--text);
}
#app.help-open #helpPanel { display: block; }

/* Header inside the panel: title + close button on the right */
.help-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 10px; margin-bottom: 14px;
  padding-bottom: 10px; border-bottom: 1px solid var(--border);
}
.help-title {
  font-size: 1.05rem; font-weight: 700; color: var(--brand);
  margin: 0; line-height: 1.3;
}
.help-close {
  background: transparent; border: 1px solid var(--border-strong);
  color: var(--text-muted);
  width: 30px; height: 30px;
  border-radius: 6px;
  font-size: 16px; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.help-close:hover { background: var(--field-bg); color: var(--text); border-color: var(--text-muted); }

/* Help content typography — denser than regular page content, more
   reference-card style. Keep prose narrow and scannable. */
.help-body h3 {
  font-size: .92rem; font-weight: 700;
  color: var(--text); margin: 16px 0 6px;
  letter-spacing: -.005em;
}
.help-body h3:first-child { margin-top: 0; }
.help-body p { margin: 0 0 8px; }
.help-body ol, .help-body ul {
  padding-left: 1.2rem; margin: 0 0 12px;
}
.help-body li { margin-bottom: 4px; }
.help-body code {
  background: var(--field-bg);
  font-family: 'SF Mono', Consolas, monospace;
  font-size: .78rem;
  padding: 1px 5px;
  border-radius: 3px;
  color: var(--brand);
  white-space: nowrap;
}
.help-body .help-tip {
  background: rgba(45,139,142,.08);
  border-left: 3px solid var(--teal);
  padding: 8px 10px;
  margin: 10px 0;
  border-radius: 0 4px 4px 0;
  font-size: .85rem;
  color: var(--text);
}
.help-body .help-warn {
  background: rgba(245,158,11,.10);
  border-left: 3px solid #F59E0B;
  padding: 8px 10px;
  margin: 10px 0;
  border-radius: 0 4px 4px 0;
  font-size: .85rem;
  color: var(--text);
}
.help-body .help-tip b, .help-body .help-warn b { color: var(--brand); }

/* Grid expansion when help is open: 60px sidebar | 1fr main | 380px help.
   Plus we apply the same visual collapse rules as `.sidebar-collapsed` so
   the sidebar shows icons only — saves horizontal space. */
#app.help-open {
  grid-template-columns: 60px 1fr 380px;
  grid-template-areas:
    "appbar appbar appbar"
    "sidebar main help";
}
#app.help-open .brand-title,
#app.help-open .brand-sub,
#app.help-open .nav-section-label,
#app.help-open .nav-label {
  opacity: 0; visibility: hidden;
  width: 0; margin: 0; padding: 0;
}
#app.help-open .brand { padding: .25rem .5rem 1rem; }
#app.help-open .nav-section { padding: 0 .25rem; }
#app.help-open .nav-item {
  justify-content: center; padding: .55rem .35rem; gap: 0;
}
#app.help-open .sidebar-toggle { display: none; }

/* Tighter help column on mid-size screens where 380px is too aggressive. */
@media (max-width: 1180px) {
  #app.help-open { grid-template-columns: 60px 1fr 320px; }
}

/* Mobile: help becomes a fixed overlay instead of squeezing main to zero. */
@media (max-width: 768px) {
  #app.help-open {
    /* Keep mobile sidebar rule (60px from earlier media query) — main keeps full width */
    grid-template-columns: 60px 1fr !important;
    grid-template-areas:
      "appbar appbar"
      "sidebar main" !important;
  }
  #app.help-open #helpPanel {
    position: fixed !important;
    top: 64px; right: 0; bottom: 0;
    width: 320px; max-width: calc(100vw - 60px);
    z-index: 60;
    box-shadow: -4px 0 20px rgba(0,0,0,.20);
    height: calc(100vh - 64px);
  }
}

/* ========== Mobile header optimization ==========
   The desktop header carries the logo, brand title (smartBIZ Copilot),
   module name, version, theme toggle, copyright, help button and user
   avatar. On a phone half of these are visual noise — the user just
   needs to navigate, see who they're logged in as, and access help.
   Strip down to logo + help + avatar; everything else is hidden. */
@media (max-width: 768px) {
  /* Tighten outer header padding so essential controls fit on a 380px viewport. */
  .sb-header {
    padding: 6px 12px 6px 9px !important;
  }
  .sb-headerInner {
    gap: 8px !important;
  }
  /* Hide the brand title text — keep just the logo svg/circle marker visible */
  .sb-brandTitle,
  .sb-moduleMeta {
    display: none !important;
  }
  /* Hide the right-column wrapper that holds theme toggle + copyright text.
     The user can still toggle theme in Settings if needed; on mobile the
     text labels and copyright line eat too much horizontal space. */
  .sb-headerRightCol {
    display: none !important;
  }
  /* Smaller logo on mobile — 36px → 32px */
  .sb-logo {
    width: 36px !important; height: 36px !important;
  }
  .sb-logo .sb-logoText {
    font-size: 12px !important;
  }
  /* Smaller user avatar on mobile to match the slimmer header */
  .sb-userAvatar {
    width: 34px !important; height: 34px !important;
    font-size: 12px !important;
  }
  /* Smaller help button to match */
  .sb-helpBtn {
    width: 34px !important; height: 34px !important;
    margin-right: 4px !important;
  }
  .sb-helpBtn svg {
    width: 16px !important; height: 16px !important;
  }
  /* Squeeze the info button (the small "i" inside Copilot label) — it's
     gone with the brand title, so this is just defense. */
  .sb-iconBtn {
    display: none !important;
  }
}

/* ========== Page Header ========== */
.page-head { margin-bottom: 1.25rem; position: relative; }
.page-head h1 { font-size: 1.45rem; font-weight: 700; letter-spacing: -.02em; color: var(--text); }
.subtitle { color: var(--text-muted); margin-top: .25rem; font-size: .9rem; }

/* ===== Page help — superscript ⓘ next to the H1 title =====
   Click toggles a small popover containing the page-specific hint that
   used to live in the .subtitle <p>. Sits inline at the end of the H1 as
   a true superscript: smaller font, raised baseline, no extra layout. */
.page-help-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 16px; height: 16px;
  margin-left: 4px;
  background: var(--brand);
  color: #fff;
  border: none;
  border-radius: 50%;
  font: italic 700 10px Georgia, serif;
  line-height: 1;
  cursor: pointer;
  vertical-align: super;       /* the actual "superscript" placement */
  position: relative; top: -2px;
  transition: background-color .15s ease, transform .12s ease;
  padding: 0;
}
.page-help-btn:hover {
  background: #2D8B8E;
  transform: scale(1.12);
}
.page-help-btn:focus-visible {
  outline: 2px solid var(--brand);
  outline-offset: 2px;
}
[data-theme="dark"] .page-help-btn { background: #4A90B5; }
[data-theme="dark"] .page-help-btn:hover { background: #5fa8c9; }

.page-help-popover {
  position: absolute;
  top: calc(100% - 2px);
  left: 0;
  background: var(--surface);
  border: 1px solid var(--border-strong, var(--border));
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(15,23,42,.18);
  padding: 0;
  max-width: 460px;
  min-width: 280px;
  z-index: 100;
  font-size: .85rem;
}
[data-theme="dark"] .page-help-popover {
  background: var(--surface-2, #1f2937);
  box-shadow: 0 10px 30px rgba(0,0,0,.5);
}
.page-help-popover-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  background: var(--field-bg);
  border-radius: 8px 8px 0 0;
}
.page-help-popover-title {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text-muted);
}
.page-help-popover-close {
  background: transparent; border: none;
  color: var(--text-muted);
  width: 22px; height: 22px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0;
}
.page-help-popover-close:hover {
  background: var(--field-bg);
  color: var(--text);
}
.page-help-popover-body {
  padding: 10px 14px 12px;
  color: var(--text);
  line-height: 1.5;
}
.page-help-popover-body b { color: var(--brand); font-weight: 700; }

/* ========== Cards ========== */
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 1.1rem 1.25rem;
  box-shadow: var(--shadow);
}
.card h3 { font-size: 1rem; font-weight: 600; margin-bottom: .65rem; }
.card p { color: var(--text-muted); }

/* Upload page: dropzone + 3 info cards in one responsive row.
   Dropzone takes 2x the visual weight of an info card when there's room. */
.upload-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
.upload-cards > .card {
  flex: 1 1 240px;       /* default info-card: 240px min, grow to fill */
  min-width: 0;          /* allow shrink past content's intrinsic width */
}
.upload-cards > .upload-drop-card {
  flex: 2 1 360px;       /* dropzone takes ~2x the space, 360px min before wrap */
}
/* On narrow viewports the cards stack naturally because of flex-wrap; nothing more needed. */
@media (max-width: 1024px) {
  .upload-cards > .upload-drop-card { flex-basis: 100%; }   /* dropzone alone on its row */
}
@media (max-width: 640px) {
  .upload-cards > .card { flex-basis: 100%; }                /* one per row on phone */
}

/* Stat cards */
.card.stat { display: flex; flex-direction: column; gap: .25rem; }
.stat-label { font-size: .8rem; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.stat-value { font-size: 1.95rem; font-weight: 700; color: var(--text); letter-spacing: -.02em; line-height: 1.1; }
.stat-foot { font-size: .8rem; color: var(--text-muted); }

/* Dashboard filter bar */
.dash-filterbar {
  display: flex; flex-wrap: wrap;
  gap: .75rem; align-items: end;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--sb-radius-md);
  padding: .9rem 1rem;
  margin-top: 1.5rem;
  box-shadow: var(--sb-shadow-card);
}
.dash-filter-group { display: flex; flex-direction: column; gap: .35rem; min-width: 130px; flex: 1 1 130px; }
.dash-filter-group label {
  font-size: .7rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--text-muted);
}
.dash-filter {
  width: 100%;
  padding: .5rem .65rem;
  font: 13px var(--sb-font-sans);
  color: var(--text);
  background: var(--surface-2, var(--bg));
  border: 1px solid var(--border);
  border-radius: var(--sb-radius-sm);
  outline: none;
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
}
.dash-filter:focus {
  border-color: var(--brand-accent, #2D8B8E);
  box-shadow: 0 0 0 3px rgba(45,139,142,.15);
}
.dash-filter-actions { display: flex; align-items: end; }
.dash-filter-reset {
  padding: .5rem .9rem;
  font: 600 12px var(--sb-font-sans);
  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: var(--sb-radius-sm);
  cursor: pointer;
  white-space: nowrap;
  transition: all .15s;
}
.dash-filter-reset:hover {
  color: var(--text);
  border-color: var(--text-muted);
}

/* Dashboard chart cards — explicit breakpoints to avoid the awkward
   3-column orphan layout that auto-fit produces with 4 charts on mid-sized
   viewports. Transitions are clean: 1 col → 2 cols → 4 cols. */
.dash-charts {
  display: grid;
  grid-template-columns: 1fr;          /* default: 1 column (phones) */
  gap: 1rem;
  margin-top: 1rem;
}
@media (min-width: 700px) {
  .dash-charts { grid-template-columns: 1fr 1fr; }   /* 2 columns: tablet, laptop */
}
@media (min-width: 1600px) {
  .dash-charts { grid-template-columns: 1fr 1fr 1fr 1fr; }  /* 4 columns: large desktop */
}

.dash-chart-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--sb-radius-md);
  box-shadow: var(--sb-shadow-card);
  padding: 1rem 1.1rem 1.1rem;
  display: flex; flex-direction: column;
  min-width: 0;             /* allow card to shrink below content width */
  min-height: 360px;
}
.dash-chart-card canvas { max-width: 100%; }   /* keep canvas inside card */
/* Full-width card spanning all grid columns (used for charts with many
   X-axis labels, e.g. by-series breakdowns). */
.dash-chart-card-wide { grid-column: 1 / -1; }

/* Tablet-and-narrower: shrink card height and tighten padding so the four
   charts don't span four full screens of vertical scrolling. */
@media (max-width: 768px) {
  .dash-charts { gap: .75rem; margin-top: .75rem; }
  .dash-chart-card { min-height: 280px; padding: .75rem .85rem .85rem; }
  .dash-chart-body { min-height: 200px; }
}
@media (max-width: 480px) {
  .dash-chart-card { min-height: 240px; }
  .dash-chart-body { min-height: 170px; }
  .dash-chart-title { font-size: .85rem; }
  .dash-chart-meta  { font-size: .62rem; }
}
.dash-chart-head {
  display: flex; align-items: baseline; justify-content: space-between;
  gap: .5rem; margin-bottom: .75rem;
}
.dash-chart-title {
  font-size: .95rem; font-weight: 700;
  color: var(--text); letter-spacing: -.01em;
}
.dash-chart-meta {
  font-size: .7rem; font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .04em;
}
.dash-chart-body {
  position: relative;
  flex: 1;
  min-height: 280px;
}
.dash-chart-empty {
  display: flex; align-items: center; justify-content: center;
  height: 100%; min-height: 240px;
  color: var(--text-muted); font-size: .85rem;
  text-align: center; padding: 1rem;
}

/* Lists */
.workflow { padding-left: 1.25rem; }
.workflow li { margin-bottom: .5rem; color: var(--text-muted); }
.workflow li b { color: var(--text); }
.bullets { padding-left: 1.25rem; }
.bullets li { margin-bottom: .25rem; color: var(--text-muted); }

/* ========== Buttons ========== */
.btn {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .5rem 1rem; border: 1px solid var(--border);
  background: var(--surface); color: var(--text);
  border-radius: 6px; font-size: .875rem; font-weight: 500;
  cursor: pointer; transition: all .15s;
}
.btn:hover:not(:disabled) { background: var(--surface-2); border-color: var(--border-strong); }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
.btn-primary:hover:not(:disabled) { background: var(--primary-hover); border-color: var(--primary-hover); }
.btn-danger { background: var(--surface); color: var(--error); border-color: #fecaca; }
.btn-danger:hover { background: var(--error-bg); border-color: var(--error); }
[data-theme="dark"] .btn-danger { border-color: #7f1d1d; }

/* ========== Forms ========== */
.form-row { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.form-row label { font-size: .85rem; color: var(--text-muted); }
input[type=text], input[type=number], input[type=date], input[type=file], select {
  padding: .45rem .65rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: .875rem;
  background: var(--surface); color: var(--text); font-family: inherit;
}
input[type=text]:focus, input[type=number]:focus, input[type=date]:focus, select:focus {
  outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light);
}

/* ===== File input — responsive + dark-mode-aware =====
   The native <input type="file"> renders as a "Choose File" button + a
   filename label, both styled by the browser by default. On narrow
   screens or in flex containers, the input doesn't shrink and ends up
   overflowing or producing a black gap. We:
   (a) cap max-width and let it shrink to fit the container
   (b) style the inner ::file-selector-button to match the theme so the
       light/dark contrast works in both modes. */
input[type=file] {
  max-width: 100%;
  min-width: 0;
  flex: 1 1 auto;
  cursor: pointer;
}
input[type=file]::file-selector-button {
  background: var(--field-bg, var(--surface-2));
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: .25rem .65rem;
  margin-right: .65rem;
  font-family: inherit; font-size: .85rem;
  cursor: pointer;
  transition: background-color .15s ease, border-color .15s ease;
}
input[type=file]::file-selector-button:hover {
  background: var(--surface-2, var(--field-bg));
  border-color: var(--border-strong, var(--border));
}
[data-theme="dark"] input[type=file] {
  color-scheme: dark;   /* tells browser to use dark UI for file picker */
}
.filter { width: 100%; }
.checkbox { display: flex; align-items: center; gap: .5rem; cursor: pointer; }
.hint { color: var(--text-muted); font-size: .8rem; }

/* ========== Tables ========== */
.table-wrap {
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: 6px;
  max-height: 70vh;
  background: var(--surface);
}
.data-table { width: 100%; border-collapse: collapse; font-size: .82rem; table-layout: auto; }
.data-table thead { position: sticky; top: 0; z-index: 1; background: var(--surface-2); }
.data-table th {
  padding: .55rem .65rem; text-align: left; font-weight: 600;
  color: var(--text-muted); border-bottom: 1px solid var(--border);
  text-transform: uppercase; font-size: .7rem; letter-spacing: .03em;
  white-space: nowrap;
}
.data-table td {
  padding: .45rem .65rem; border-bottom: 1px solid var(--border);
  white-space: nowrap; color: var(--text);
}
.data-table tbody tr:hover { background: var(--surface-2); }
.data-table.compact td { padding: .35rem .55rem; font-size: .8rem; }
.data-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.data-table small { color: var(--text-muted); }
.data-table code { background: var(--surface-2); color: var(--text); padding: 1px 5px; border-radius: 3px; font-size: .8rem; }
.empty { text-align: center; color: var(--text-muted); padding: 2rem; }
.total-row { background: #fefce8; font-weight: 600; }
[data-theme="dark"] .total-row { background: #422006; color: #fef3c7; }
.total-row td { border-top: 2px solid #facc15; }

/* Pagination */
.pagination {
  display: flex; align-items: center; gap: .75rem;
  padding: .5rem 0 0; justify-content: center;
}
.pagination button {
  padding: .35rem .75rem; background: var(--surface); color: var(--text); border: 1px solid var(--border);
  border-radius: 4px; cursor: pointer;
}
.pagination button:hover { background: var(--surface-2); }
.pagination span { font-size: .85rem; color: var(--text-muted); }

/* Totals */
.totals {
  display: flex; gap: 1.5rem; padding: .75rem 0 0;
  font-size: .9rem; color: var(--text-muted); flex-wrap: wrap;
}
.totals b { color: var(--text); font-variant-numeric: tabular-nums; }

/* ========== Badges ========== */
.badge {
  display: inline-block; padding: .15rem .55rem;
  border-radius: 12px; font-size: .7rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .03em;
}
.badge-mkp { background: #dbeafe; color: #1e40af; }
.badge-fbe { background: #d1fae5; color: #065f46; }
.status { font-size: .75rem; font-weight: 500; }
.status-activ { color: var(--success); }
.status-inactiv { color: var(--text-muted); }

/* ========== Drop zone ========== */
.drop-zone {
  border: 2px dashed var(--border);
  border-radius: 10px;
  padding: 3rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: all .2s;
  background: var(--surface-2);
}
.drop-zone:hover, .drop-zone.drag {
  border-color: var(--primary);
  background: color-mix(in srgb, var(--primary) 10%, var(--surface-2));
}
.drop-icon { font-size: 2.5rem; margin-bottom: .5rem; }
.drop-text { font-size: 1rem; margin-bottom: .25rem; }
/* When dropzone is inside the inline upload card, reduce padding so it doesn't dominate vertical space */
.upload-drop-card .drop-zone { padding: 1.75rem .75rem; }
.upload-drop-card .drop-icon { font-size: 2rem; }
.upload-drop-card .drop-text { font-size: .92rem; }

/* ========== Status messages ========== */
.success {
  background: var(--success-bg); color: #065f46;
  padding: .75rem 1rem; border-radius: 6px;
  border-left: 3px solid var(--success);
  margin-top: .75rem;
}
.error {
  background: var(--error-bg); color: #991b1b;
  padding: .75rem 1rem; border-radius: 6px;
  border-left: 3px solid var(--error);
  margin-top: .75rem;
}
[data-theme="dark"] .success { color: #6ee7b7; }
[data-theme="dark"] .error   { color: #fca5a5; }
.loading {
  color: var(--text-muted); padding: 1.5rem; text-align: center;
}

/* ========== Toasts ========== */
#toasts { position: fixed; top: 1rem; right: 1rem; z-index: 999; display: flex; flex-direction: column; gap: .5rem; }
.toast {
  background: #1e293b; color: #fff;
  padding: .65rem 1rem; border-radius: 6px;
  box-shadow: var(--shadow-md);
  font-size: .875rem;
  animation: slideIn .25s ease-out;
  max-width: 360px;
}
.toast-success { background: var(--success); }
.toast-error { background: var(--error); }
.toast.out { opacity: 0; transition: opacity .4s; }
@keyframes slideIn { from { transform: translateX(20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* ========== Busy overlay ========== */
#busy {
  position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 1000;
  display: none; align-items: center; justify-content: center;
}
#busy.show { display: flex; }
#busy .box {
  background: var(--surface); color: var(--text); padding: 1.25rem 2rem; border-radius: 10px;
  display: flex; align-items: center; gap: 1rem;
  box-shadow: var(--shadow-md);
}
.spinner {
  width: 28px; height: 28px;
  border: 3px solid var(--border); border-top-color: var(--primary);
  border-radius: 50%; animation: spin .9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* responsive */
@media (max-width: 768px) {
  #app { grid-template-columns: 1fr; }
  #sidebar { position: static; height: auto; }
  #content { padding: 1rem; }
}

/* ========== Page Header w/ Actions ========== */
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.page-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

/* ========== Icon Buttons ========== */
.btn-icon {
  background: transparent; border: none;
  padding: .25rem .35rem; margin: 0 1px;
  cursor: pointer; border-radius: 4px;
  font-size: .9rem; transition: all .12s;
  opacity: .55; color: var(--text);
}
.btn-icon:hover { background: var(--surface-2); opacity: 1; }

/* ========== Modal ========== */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, .55);
  z-index: 1100;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
}
[data-theme="dark"] .modal-overlay { background: rgba(0,0,0,.7); }
.modal-overlay.show { animation: modal-in .18s ease-out; }
@keyframes modal-in {
  from { opacity: 0; }
  to { opacity: 1; }
}
.modal {
  background: var(--surface); color: var(--text);
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0,0,0,.25);
  width: 100%;
  max-height: 92vh;
  display: flex; flex-direction: column;
}
.modal-overlay.show .modal { animation: modal-pop .18s ease-out; }
@keyframes modal-pop {
  from { transform: translateY(8px); opacity: .8; }
  to { transform: translateY(0); opacity: 1; }
}
.modal-sm { max-width: 420px; }
.modal-md { max-width: 560px; }
.modal-lg { max-width: 720px; }
.modal-xl { max-width: 960px; }

.modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border);
}
.modal-head h3 { font-size: 1.05rem; font-weight: 600; }
.modal-close {
  background: transparent; border: none;
  font-size: 1.5rem; line-height: 1;
  cursor: pointer; color: var(--text-muted);
  padding: 0 .5rem;
}
.modal-close:hover { color: var(--text); }
.modal-body {
  padding: 1.25rem;
  overflow-y: auto;
  flex: 1;
}
.modal-foot {
  display: flex; gap: .5rem; align-items: center;
  padding: .85rem 1.25rem;
  border-top: 1px solid var(--border);
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
}
.modal-foot .spacer { flex: 1; }

/* ========== Form fields ========== */
.modal-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem 1.25rem;
}
.modal-xl .modal-form { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
.field { display: flex; flex-direction: column; gap: .25rem; min-width: 0; }
.field label {
  font-size: .8rem; font-weight: 500;
  color: var(--text); display: flex; gap: .25rem; align-items: center;
}
.field .req { color: var(--error); }
.field input, .field select, .field textarea {
  width: 100%;
  padding: .5rem .65rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: .875rem;
  background: var(--surface); color: var(--text);
  font-family: inherit;
}
.field textarea { resize: vertical; min-height: 60px; }
.field input:focus, .field select:focus, .field textarea:focus {
  outline: none; border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-light);
}
.field input[readonly], .field select[disabled] {
  background: var(--surface-2); color: var(--text-muted); cursor: not-allowed;
}
.field-error { border-color: var(--error) !important; box-shadow: 0 0 0 3px var(--error-bg) !important; }
.field-hint { font-size: .72rem; color: var(--text-muted); line-height: 1.35; }
.field-checkbox { margin-bottom: 14px; }
.field-checkbox .checkbox-label {
  display: inline-flex; align-items: center; gap: .55rem;
  cursor: pointer; user-select: none;
  font-size: .9rem; color: var(--text);
}
.field-checkbox input[type="checkbox"] {
  width: 18px; height: 18px;
  accent-color: var(--brand-accent, #2D8B8E);
  cursor: pointer;
}

/* Admin-only elements: hidden unless body has .is-admin */
.admin-only { display: none !important; }
body.is-admin .admin-only { display: revert !important; }

/* Read-only mode: hide common write-action UI for non-admin users.
   Server enforces 403 on writes anyway — these rules just prevent the
   user from clicking buttons that would always fail. */
body.is-readonly .btn-icon[data-act="edit"],
body.is-readonly .btn-icon[data-act="delete"],
body.is-readonly .btn-icon[data-act="duplicate"],
body.is-readonly .btn-danger {
  display: none !important;
}
/* Hide the standard "Adaugă..." button on CRUD pages.
   Convention used throughout the app: the primary "add" button has id="addBtn". */
body.is-readonly #addBtn { display: none !important; }

/* ========== Excel-style table ========== */
.excel-grid {
  max-height: 75vh;
  overflow: auto;
  scrollbar-gutter: stable;
}
.excel-table { font-size: .78rem; table-layout: fixed; width: max-content; min-width: 100%; }
.excel-table thead { background: var(--surface-2); }
.excel-table th {
  background: var(--surface-2); color: var(--text);
  padding: .35rem .5rem;
  border-right: 1px solid var(--border);
  border-bottom: 1px solid var(--border-strong);
  white-space: nowrap;
  font-size: .68rem;
  letter-spacing: .02em;
  overflow: hidden;
  text-overflow: ellipsis;
}
.excel-table th.key { background: #fef3c7; color: #92400e; }
[data-theme="dark"] .excel-table th.key { background: #78350f; color: #fef3c7; }
.excel-colnum th {
  background: var(--border);
  text-align: center;
  font-size: .65rem;
  color: var(--text-muted);
  font-weight: 500;
  padding: .15rem .35rem;
  border-right: 1px solid var(--border-strong);
  text-transform: none;
  letter-spacing: 0;
}
.excel-colnum th.colnum { font-variant-numeric: tabular-nums; }
.excel-table td {
  padding: .3rem .5rem;
  border-right: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  font-size: .78rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--text);
}
.excel-table td.key { background: #fffbeb; }
[data-theme="dark"] .excel-table td.key { background: #422006; }
.excel-table tbody tr:hover { background: var(--surface-2); }
.excel-table tbody tr:hover td.key { background: #fef3c7; }
[data-theme="dark"] .excel-table tbody tr:hover td.key { background: #78350f; }
.excel-table tbody tr:hover td[title] { cursor: help; }
.excel-table .sticky-act {
  position: sticky; right: 0;
  background: var(--surface);
  border-left: 1px solid var(--border);
  z-index: 2;
  text-align: center;
}
.excel-table thead .sticky-act { background: var(--surface-2); z-index: 3; }
.excel-table tbody tr:hover .sticky-act { background: var(--surface-2); }

/* ========== Derived columns (computed, not stored) ========== */
.excel-table th.derived {
  background: #e0f2fe;
  color: #075985;
}
[data-theme="dark"] .excel-table th.derived { background: #075985; color: #e0f2fe; }
.excel-table td.derived {
  background: #f0f9ff;
  color: #075985;
  font-style: italic;
  position: relative;
}
.excel-table td.derived::before {
  content: 'ƒ';
  position: absolute;
  top: 1px; left: 2px;
  font-size: 8px;
  color: #0ea5e9;
  font-style: normal;
  font-weight: 700;
}
.excel-table tr.row-noMp td { background: #fef2f2; }
.excel-table tr.row-noMp td.derived { background: #fee2e2; color: #991b1b; }
.excel-table tr.row-noMp:hover td { background: #fecaca; }
/* Dark theme overrides for derived columns + missing-marketplace rows */
[data-theme="dark"] .excel-table td.derived {
  background: rgba(14, 165, 233, 0.10);
  color: #7dd3fc;
}
[data-theme="dark"] .excel-table td.derived::before { color: #38bdf8; }
[data-theme="dark"] .excel-table tr.row-noMp td { background: rgba(239, 68, 68, 0.10); }
[data-theme="dark"] .excel-table tr.row-noMp td.derived {
  background: rgba(239, 68, 68, 0.18);
  color: #fca5a5;
}
[data-theme="dark"] .excel-table tr.row-noMp:hover td { background: rgba(239, 68, 68, 0.18); }
.missing { color: var(--error); font-size: .75rem; font-weight: 500; }

/* ========== Centralizator (Excel-style 1:1) ========== */
.centralizator {
  background: var(--surface-paper);
  padding: 1.5rem 1.75rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-family: 'Segoe UI', system-ui, sans-serif;
  color: var(--text);
  max-width: 100%;
  /* overflow-x is delegated to the inner .cz-pivot-1-wrap / .cz-pivot-2-wrap / .decont-pivot-wrap
     so the title bar, filters, totals and footer stay fixed while only the wide tables scroll. */
}

/* Top bar with Refresh + company titles */
.cz-topbar {
  display: flex;
  flex-direction: column;
  gap: .5rem;
  margin-bottom: 1rem;
}
.cz-titles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: end;
  margin-bottom: 12px;
}
.cz-title-l h1, .cz-title-r h1 {
  font-size: 2rem;
  font-weight: 700;
  color: #1e3a8a;
  letter-spacing: -.01em;
  line-height: 1.1;
  margin: 0;
}
.cz-title-l { text-align: left; }
.cz-title-r { text-align: right; }
.cz-mkp-label {
  font-style: italic;
  color: #6b7280;
  font-size: .85rem;
  margin-top: .15rem;
}
.btn-success { background: #16a34a; color: #fff; border-color: #15803d; }
.btn-success:hover { background: #15803d; }

/* Filters block + doc ref */
.cz-filters {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1rem;
  padding: .5rem .75rem;
  background: linear-gradient(to right, #fffbe6 0%, #fffbe6 50%, transparent 100%);
  border-radius: 6px;
}
.cz-num-deconturi {
  display: flex;
  align-items: baseline;
  gap: .4rem;
}
.cz-num-deconturi .lbl { color: #1f2937; font-weight: 600; font-size: .85rem; }
.cz-num-deconturi .val { font-size: 1.4rem; font-weight: 700; color: #1e3a8a; line-height: 1; }

.cz-filter-inline {
  display: inline-flex;
  align-items: center;
  gap: 0;
  border: 1px solid #d1c684;
  border-radius: 4px;
  background: #fff;
  font-size: .85rem;
  width: fit-content;
  max-width: 100%;        /* never exceed container width */
  overflow-x: auto;       /* scroll filter bar internally if too wide for container */
  scrollbar-width: thin;
}
.cz-fil-lbl {
  padding: .35rem .65rem;
  background: #fef9e7;
  font-weight: 600;
  color: #1f2937;
  border-right: 1px solid #e5d39a;
}
.cz-fil-val {
  padding: 0;
  background: transparent;
  border-right: 1px solid #e5d39a;
  display: flex;
  align-items: center;
}
.cz-fil-val:last-child { border-right: none; }
.cz-fil-val select {
  border: none;
  background: transparent;
  font-size: .85rem;
  font-weight: 600;
  font-family: inherit;
  padding: .35rem .5rem;
  cursor: pointer;
  min-width: 80px;
  color: #1f2937;
}
.cz-fil-val select:focus { outline: 2px solid #fbbf24; outline-offset: -2px; }
.cz-doc-ref {
  text-align: right;
  color: #6b7280;
  font-style: italic;
  font-size: .85rem;
  white-space: nowrap;
}

/* Pivot 1 wrapper */
.cz-pivot-1-wrap { margin-bottom: 1.25rem; }

/* Wrappers around pivot tables — allow horizontal scroll on narrow desktop widths
   without breaking the page layout. The table keeps its full intrinsic width
   (nowrap cells + multi-column headers) and the wrapper scrolls. */
.cz-pivot-1-wrap,
.cz-pivot-2-wrap,
.decont-pivot-wrap {
  overflow-x: auto;
  /* Subtle scrollbar styling for desktop browsers */
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}
.cz-pivot-1-wrap::-webkit-scrollbar,
.cz-pivot-2-wrap::-webkit-scrollbar,
.decont-pivot-wrap::-webkit-scrollbar { height: 8px; }
.cz-pivot-1-wrap::-webkit-scrollbar-thumb,
.cz-pivot-2-wrap::-webkit-scrollbar-thumb,
.decont-pivot-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* On narrow desktops (~1100-1400px) reduce padding & font slightly so the table fits more often without scroll. */
@media (max-width: 1400px) {
  .cz-pivot { font-size: 1rem; }
  .cz-pivot th { font-size: .96rem; padding: .35rem .5rem; }
  .cz-pivot td { padding: .25rem .5rem; }
}
@media (max-width: 1180px) {
  .cz-pivot { font-size: .95rem; }
  .cz-pivot th { font-size: .92rem; padding: .3rem .4rem; }
  .cz-pivot td { padding: .22rem .4rem; }
}

/* First row: tools + anexe banner inline */
.cz-banner-row {
  display: flex;
  align-items: center;
  gap: .75rem;
  margin-bottom: .75rem;
  padding-bottom: .5rem;
  border-bottom: 1px solid #e5e7eb;
}
.cz-banner-tools {
  display: flex;
  gap: .25rem;
  flex-shrink: 0;
}
.btn-icon-tool {
  background: transparent;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 4px;
  padding: .2rem .45rem;
  cursor: pointer;
  font-size: .95rem;
  line-height: 1;
  opacity: .55;
  transition: opacity .15s, background .15s, border-color .15s;
}
.btn-icon-tool:hover {
  opacity: 1;
  background: var(--surface-2);
  border-color: var(--border-strong);
}
.btn-icon-tool:active { transform: translateY(1px); }

/* Anexe banner inline (no own border / margin since wrapper handles it) */
.cz-anexe-banner {
  flex: 1;
  font-style: italic;
  color: #1e3a8a;
  font-size: .95rem;
  text-align: right;
  font-weight: 500;
}
[data-theme="dark"] .cz-anexe-banner { color: #93c5fd; }

/* Pivot tables */
.cz-pivot {
  border-collapse: collapse;
  width: 100%;
  font-size: 1.07rem;          /* was .92rem · +2px on data rows */
  font-family: 'Calibri', sans-serif;
}
.cz-pivot th {
  background: transparent;
  font-weight: 700;
  color: var(--text);
  padding: .4rem .65rem;
  text-align: left;
  border-bottom: 1.5px solid #1e3a8a;
  white-space: nowrap;
  font-size: 1.03rem;          /* was .89rem · +2px on headers */
}
[data-theme="dark"] .cz-pivot th { border-bottom-color: #4A90B5; }
.cz-pivot th.num { text-align: right; }
.cz-pivot td {
  padding: .3rem .65rem;
  border-bottom: 1px solid var(--border);
  vertical-align: top;
  white-space: nowrap;
  color: var(--text);
}
.cz-pivot td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cz-pivot tbody tr:hover { background: var(--surface-2); }
.cz-pivot .empty { text-align: center; color: var(--text-muted); padding: 1.5rem; }

/* Nr.Crt column - fixed width for visual alignment between pivot 1 and pivot 2 */
.cz-pivot th:first-child,
.cz-pivot td:first-child {
  width: 56px;
  min-width: 56px;
  max-width: 56px;
  text-align: center !important;
}

.cz-anexa-col { color: #1e3a8a; font-style: italic; }

/* ========== Raport dinamic ========== */
.r-config { padding: 1rem; }
.r-config summary {
  cursor: pointer; font-weight: 600; color: var(--text);
  padding: .35rem 0; user-select: none;
}
.r-config summary:hover { color: var(--primary); }
.r-cols-table-wrap {
  max-height: 380px;
  overflow: auto;            /* allow both vertical AND horizontal scroll */
  margin: .75rem 0;
  border: 1px solid var(--border); border-radius: 6px;
  width: 100%; max-width: 100%;
}

/* Search box for the column configurator — filters rows by label/key.
   The icon is an inline SVG so it follows the theme. The clear ✕ button
   appears on the right when there's text, click to wipe the filter. */
.r-cols-search {
  position: relative;
  margin: .5rem 0 0;
  max-width: 320px;
}
.r-cols-search input {
  width: 100%;
  padding: 6px 30px 6px 32px;
  font-size: .85rem;
  background: var(--surface) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='8'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg>") no-repeat 9px center;
  background-size: 14px 14px;
  color: var(--text);
  border: 1px solid var(--border-strong, var(--border));
  border-radius: 6px;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.r-cols-search input:focus {
  outline: none;
  border-color: var(--sb-brand-navy);
  box-shadow: 0 0 0 2px rgba(31,71,136,.15);
}
.r-cols-search input::placeholder { color: var(--text-muted); }
.r-cols-search-clear {
  position: absolute;
  right: 6px; top: 50%; transform: translateY(-50%);
  width: 22px; height: 22px;
  background: transparent; border: none;
  color: var(--text-muted);
  font-size: 16px; line-height: 1; cursor: pointer;
  border-radius: 4px;
  display: inline-flex; align-items: center; justify-content: center;
}
.r-cols-search-clear:hover {
  background: var(--field-bg, rgba(15,23,42,.05));
  color: var(--text);
}
[data-theme="dark"] .r-cols-search input {
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='8'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg>");
}
.r-cols-table {
  width: auto; border-collapse: collapse; font-size: .8rem;
}
.r-cols-table thead th {
  position: sticky; top: 0; z-index: 2; background: var(--surface-2);
  padding: .3rem .55rem; text-align: left; vertical-align: middle;
  font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
  color: var(--text-muted); font-weight: 600;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
/* Sticky first column on horizontal scroll. The intersection (thead + first
   col) needs the highest z-index so it stays above both other sticky thead
   cells and other sticky first-col cells. The right edge gets a subtle
   shadow so users can see where the pinned region ends. */
.r-cols-table thead th:first-child {
  left: 0; z-index: 4;
  box-shadow: 2px 0 4px -2px rgba(15,23,42,.12);
}
.r-cols-table tbody td:first-child {
  position: sticky; left: 0; z-index: 1;
  background: var(--surface);
  box-shadow: 2px 0 4px -2px rgba(15,23,42,.08);
}
.r-cols-table tbody tr:hover td:first-child {
  background: var(--surface-2);   /* match row hover */
}
.r-cols-table tbody tr.r-dragging td:first-child {
  /* drag visual must beat the hover bg */
  background: var(--surface);
}
.r-cols-table th.r-col-actions {
  text-align: center; vertical-align: middle;
  min-width: 110px;
  padding: .25rem .5rem;
}
.r-col-title { font-size: .72rem; line-height: 1.1; }
.r-mini-actions {
  display: flex; justify-content: center; align-items: center;
  gap: .15rem; font-size: .68rem; font-weight: 400;
  text-transform: none; letter-spacing: 0;
  margin-top: .1rem;
}
.r-mini-btn {
  background: none; border: 0; padding: 0;
  color: var(--primary); cursor: pointer;
  font: inherit; text-decoration: underline;
}
.r-mini-btn:hover { color: var(--primary-hover); }
.r-mini-sep { color: var(--text-muted); }
.r-cols-table tbody td {
  padding: .2rem .55rem;
  border-bottom: 1px solid var(--border-subtle, var(--border));
  color: var(--text);
  white-space: nowrap;
}
.r-cols-table tbody tr { cursor: grab; }
.r-cols-table tbody tr:active { cursor: grabbing; }
.r-cols-table tbody tr:hover { background: var(--surface-2); }
.r-cols-table tbody tr.r-dragging { opacity: .4; }
.r-cols-table tbody tr.r-drop-above td { box-shadow: inset 0 2px 0 var(--primary); }
.r-cols-table tbody tr.r-drop-below td { box-shadow: inset 0 -2px 0 var(--primary); }
.r-drag-handle {
  display: inline-block; width: 14px; margin-right: .35rem;
  color: var(--text-muted); font-weight: 700; user-select: none;
}
/* Center-align cells in checkbox/control columns. After adding Ordine as
   col 2, the visible-checkbox shifted from col 2 to col 3. Filtru (col 4)
   has a select that's left-aligned via display:block on the wrap, so we
   exclude it. */
.r-cols-table tbody td:nth-child(2),
.r-cols-table tbody td:nth-child(3),
.r-cols-table tbody td:nth-child(5) {
  text-align: center;
  vertical-align: middle;
}
.r-cols-table input[type="checkbox"] {
  cursor: pointer; transform: scale(1.05); margin: 0;
  accent-color: var(--sb-brand-navy);
}

/* Order column input: small, compact, centered, no spinner buttons.
   The user types desired position (1..N); reordering happens on "Aplică".
   Default appearance matches other table inputs. */
.r-cols-table .r-order-input {
  width: 50px;
  padding: 2px 6px;
  font-size: .8rem;
  text-align: center;
  font-variant-numeric: tabular-nums;
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 3px;
}
.r-cols-table .r-order-input:focus {
  outline: none;
  border-color: var(--sb-brand-navy);
  box-shadow: 0 0 0 2px rgba(31,71,136,.15);
}
/* Hide the native number-spinner buttons — they're cramped at this size
   and the keyboard arrow keys still work to bump values. */
.r-cols-table .r-order-input::-webkit-outer-spin-button,
.r-cols-table .r-order-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.r-cols-table .r-order-input { -moz-appearance: textfield; }
/* Center-align the order column header + cell */
.r-cols-table th:nth-child(2),
.r-cols-table td:nth-child(2) {
  text-align: center;
  vertical-align: middle;
}
/* Active filter dropdown — value selected, not "(toate)". Matches checkbox tone. */
.r-cols-table select.r-filter-active {
  color: var(--sb-brand-navy);
  font-weight: 600;
  border-color: var(--sb-brand-navy);
}
[data-theme="dark"] .r-cols-table select.r-filter-active {
  color: #93c5fd;
  border-color: #4A90B5;
}
.r-sort-btn {
  background: var(--surface); color: var(--text-muted);
  border: 1px solid var(--border); border-radius: 4px;
  padding: .15rem .4rem; font-size: .75rem; cursor: pointer;
  font-family: inherit; min-width: 60px;
}
.r-sort-btn:hover:not(:disabled) { border-color: var(--sb-brand-navy); }
.r-sort-btn:disabled { opacity: .4; cursor: not-allowed; }
.r-sort-btn.r-sort-active {
  background: var(--sb-brand-navy); color: #fff;
  border-color: var(--sb-brand-navy); font-weight: 600;
}
[data-theme="dark"] .r-sort-btn.r-sort-active {
  background: #4A90B5; border-color: #4A90B5;
}
.r-cols-table select {
  /* Filter dropdowns. min-width ensures the control stays readable when
     the parent grid column is narrow (e.g. on mobile or when the help
     panel is open). The wrap has overflow:auto so horizontal scroll
     kicks in if the whole table can't fit. */
  width: 100%; min-width: 150px; max-width: 220px;
  padding: .15rem .35rem; font-size: .78rem;
  background: var(--surface); color: var(--text);
  border: 1px solid var(--border); border-radius: 4px;
}
/* Filter column — reserve a sensible minimum so the dropdown never
   shrinks to invisibility, even when the configurator panel itself is
   narrow. After the Ordine column was added at position 2, Filtru moved
   from nth-child(3) to nth-child(4). The table-wrap will scroll
   horizontally past this. */
.r-cols-table th:nth-child(4),
.r-cols-table td:nth-child(4) {
  min-width: 160px;
}
.r-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .75rem; }

/* Grid container holding the two configuration panels (column config + saved
   configs) side by side on laptop+, stacking on narrower viewports. */
.r-config-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
  margin-top: .5rem;
}
@media (max-width: 1180px) {
  .r-config-grid { grid-template-columns: minmax(0, 1fr); }
}

/* Each inner panel is a <details>; constrain it to its grid column so it
   doesn't try to push content past the column boundary. */
.r-config-grid > details {
  min-width: 0;       /* allow children to shrink below intrinsic width */
  max-width: 100%;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: .65rem .85rem;
}
.r-config-grid > details[open] { padding-bottom: .85rem; }
.r-config-grid > details > summary {
  margin: -.65rem -.85rem;
  padding: .65rem .85rem;
  border-radius: 8px;
}
.r-config-grid > details[open] > summary {
  margin-bottom: .25rem;
  border-bottom: 1px solid var(--border);
  border-radius: 8px 8px 0 0;
}

/* The outer parent <details> wraps both panels — gets a slightly more
   prominent summary so users see it first. */
.r-config > details[data-section="parent"] > summary {
  font-size: .92rem;
  font-weight: 700;
  padding: .25rem 0;
}

/* Saved configurations panel — second <details> in the r-config card */
.r-saved-details { margin-top: .25rem; }
.r-saved-create {
  display: flex; gap: .5rem; align-items: center;
  margin: .75rem 0 .5rem;
  flex-wrap: wrap;
}
.r-saved-create input[type="text"] {
  flex: 1; min-width: 0; max-width: 360px;
  padding: .45rem .65rem;
  border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--text);
  font-size: .85rem;
}
.r-saved-create input[type="text"]:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(45,139,142,.18);
}
/* "Update" button — appears only when the user has loaded a saved config
   and made edits. Amber tint to signal pending edits, distinct from the
   primary "New" button. The config name is appended as a subtle subtitle
   so the user knows which config will be overwritten. */
.r-saved-create .r-save-existing {
  background: linear-gradient(180deg, #F59E0B, #D97706);
  border: 1px solid #B45309;
  color: #fff;
  font-weight: 600;
  padding: .45rem .8rem;
  border-radius: 6px;
  font-size: .85rem;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}
.r-saved-create .r-save-existing-name {
  font-weight: 500;
  font-size: .78rem;
  opacity: .9;
  font-style: italic;
}
.r-saved-create .r-save-existing:hover {
  background: linear-gradient(180deg, #FBBF24, #F59E0B);
  box-shadow: 0 2px 6px rgba(245,158,11,.3);
}
.r-saved-create .r-save-existing:active {
  transform: translateY(1px);
}
.r-saved-table-wrap {
  max-height: 320px; overflow: auto;
  margin: .25rem 0;
  border: 1px solid var(--border); border-radius: 6px;
}
.r-saved-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.r-saved-table thead th {
  position: sticky; top: 0; z-index: 2;
  background: var(--surface);
  text-align: left; font-weight: 600;
  padding: .5rem .65rem;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
/* Sticky first two columns on horizontal scroll: ★ (default flag) + Nume.
   The ★ alone is too narrow (32px) to be useful; pinning Nume too keeps
   the row identifier visible. The right edge of the second sticky cell
   gets a soft shadow as the visual divider. */
.r-saved-table thead th:nth-child(1) { left: 0; z-index: 4; }
.r-saved-table thead th:nth-child(2) { left: 32px; z-index: 4; box-shadow: 2px 0 4px -2px rgba(15,23,42,.12); }
.r-saved-table tbody td:nth-child(1),
.r-saved-table tbody td:nth-child(2) {
  position: sticky; z-index: 1;
  background: var(--surface);
}
.r-saved-table tbody td:nth-child(1) { left: 0; }
.r-saved-table tbody td:nth-child(2) { left: 32px; box-shadow: 2px 0 4px -2px rgba(15,23,42,.08); }
.r-saved-table tbody tr:hover td:nth-child(1),
.r-saved-table tbody tr:hover td:nth-child(2) {
  background: #f0fafa;   /* match row hover (resolved teal-tinted) */
}
[data-theme="dark"] .r-saved-table tbody tr:hover td:nth-child(1),
[data-theme="dark"] .r-saved-table tbody tr:hover td:nth-child(2) {
  background: rgba(45,139,142,.18);
}
.r-saved-table tbody tr.r-default-row td:nth-child(1),
.r-saved-table tbody tr.r-default-row td:nth-child(2) {
  background: rgba(245,158,11,.04);   /* match default-row tint */
}
.r-saved-table tbody td {
  padding: .45rem .65rem;
  border-bottom: 1px solid var(--border-subtle, var(--border));
  vertical-align: middle;
}
.r-saved-table tbody tr:hover { background: rgba(45,139,142,.05); }
.r-saved-table th.num, .r-saved-table td.num { text-align: right; }

/* Sortable headers in the saved-configs table — click cycles asc/desc.
   Active sorted column shows a tiny arrow next to the label. */
.r-saved-table th.r-sort-h {
  cursor: pointer;
  user-select: none;
  transition: background-color .12s ease;
}
.r-saved-table th.r-sort-h:hover { background: rgba(31,71,136,.06); }
.r-saved-table .r-sort-arr {
  display: inline-block;
  margin-left: 3px;
  font-size: .65rem;
  color: var(--brand);
  vertical-align: middle;
}

/* Default-config column: a star button per row. Single-default invariant
   means at most ONE row has the filled star at any time. The active
   default row gets a subtle background tint to stand out in the list. */
.r-saved-table th.r-default-col,
.r-saved-table td.r-default-col {
  width: 32px;
  text-align: center;
  padding: 5px 4px;
}
.r-saved-table th.r-default-col {
  color: var(--text-muted);
  font-size: 1rem;
}
.r-default-btn {
  background: transparent; border: none;
  color: var(--text-muted);
  font-size: 1.05rem;
  line-height: 1;
  cursor: pointer;
  padding: 2px 5px;
  border-radius: 4px;
  transition: color .12s ease, transform .12s ease;
}
.r-default-btn:hover { color: #f59e0b; background: rgba(245,158,11,.08); transform: scale(1.15); }
.r-default-btn[aria-pressed="true"] {
  color: #f59e0b;     /* amber filled star */
  font-weight: bold;
}
.r-default-row {
  background: rgba(245,158,11,.04);
}
.r-default-row:hover { background: rgba(245,158,11,.08) !important; }
.r-default-tag {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 7px;
  background: rgba(245,158,11,.15);
  color: #92400e;
  border: 1px solid rgba(245,158,11,.35);
  border-radius: 999px;
  font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em;
  vertical-align: middle;
}
.r-summary {
  padding: .5rem 0; font-size: .85rem; color: var(--text-muted);
}
.r-summary b { color: var(--text); }

/* Active-report quick selector at the top of the results — doubles as a
   visible label of which saved config is currently applied. Looks more
   like a stylized title than a form field. */
.r-active-report {
  display: flex; align-items: center; gap: 10px;
  padding: 6px 0 12px;
  border-bottom: 1px solid var(--border);
  margin-bottom: .5rem;
}
.r-ar-label {
  font-size: .78rem; font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .06em;
}
.r-ar-select {
  /* Title-like appearance: bold, brand-colored, transparent until hover. */
  font: 700 1.05rem -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: var(--brand);
  background-color: transparent;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%231F4788' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
  background-repeat: no-repeat;
  background-position: right 8px center;
  background-size: 14px 14px;
  border: 1px solid transparent;
  padding: 5px 28px 5px 10px;
  border-radius: 6px;
  cursor: pointer;
  appearance: none; -webkit-appearance: none; -moz-appearance: none;
  min-width: 220px; max-width: 360px;
  transition: background-color .15s ease, border-color .15s ease;
}
.r-ar-select:hover {
  background-color: var(--field-bg);
  border-color: var(--border-strong, var(--border));
}
.r-ar-select:focus {
  outline: none;
  background-color: var(--field-bg);
  border-color: var(--brand);
}
.r-ar-select:disabled {
  cursor: not-allowed; opacity: .55;
  font-weight: 500; color: var(--text-muted);
}
[data-theme="dark"] .r-ar-select {
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23e2e8f0' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
  color: var(--text);
  /* Tells the browser to render the native <option> popup with dark UI
     so the list of saved configs stays legible (default is light). */
  color-scheme: dark;
}
[data-theme="dark"] .r-ar-select option {
  /* Most browsers honour these on the option element itself for the
     popup list — fixes the "white-on-light-grey" contrast issue when
     the user opens the dropdown. */
  background-color: #1f2937;
  color: #e5e7eb;
}
[data-theme="dark"] .r-ar-select option:checked,
[data-theme="dark"] .r-ar-select option:hover {
  background-color: #374151;
}
/* Apply color-scheme to ALL native selects in dark mode too, so any
   other dropdown (filter pickers, etc.) uses the dark popup variant. */
[data-theme="dark"] select { color-scheme: dark; }
[data-theme="dark"] select option {
  background-color: #1f2937;
  color: #e5e7eb;
}

/* ===== Active filters info button + popover ===== */
/* Sits to the right of the active-report selector. Shows a count badge
   of how many filters are active; click toggles the popover with each
   filter as "Coloană: Valoare" + × to clear individually. Hidden when
   no filters are active. */
.r-filters-info {
  position: relative;
  margin-left: auto;   /* push to the right within the flex .r-active-report */
}
.r-filters-info-btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(45,139,142,.10);
  border: 1px solid rgba(45,139,142,.4);
  color: var(--brand);
  border-radius: 999px;
  padding: 4px 12px 4px 5px;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color .15s ease, border-color .15s ease;
}
.r-filters-info-btn:hover {
  background: rgba(45,139,142,.18);
  border-color: rgba(45,139,142,.6);
}
.r-filters-info-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 18px; height: 18px;
  background: var(--brand);
  color: #fff;
  border-radius: 50%;
  font: italic 700 11px Georgia, serif;
  letter-spacing: 0;
  line-height: 1;
  flex-shrink: 0;
}
.r-filters-info-count {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  color: var(--brand);
}
.r-filters-info-label {
  color: var(--text-muted);
  font-weight: 500;
  font-size: .76rem;
}
[data-theme="dark"] .r-filters-info-btn {
  background: rgba(74,144,181,.18);
  border-color: rgba(74,144,181,.5);
  color: #93c5fd;
}
[data-theme="dark"] .r-filters-info-icon { background: #4A90B5; }
[data-theme="dark"] .r-filters-info-count { color: #93c5fd; }

.r-filters-info-popover {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: var(--surface);
  border: 1px solid var(--border-strong, var(--border));
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(15,23,42,.18);
  padding: .65rem .8rem;
  min-width: 280px;
  max-width: 420px;
  z-index: 100;
}
[data-theme="dark"] .r-filters-info-popover {
  background: var(--surface-2, #1f2937);
  box-shadow: 0 10px 30px rgba(0,0,0,.5);
}
.r-filters-info-popover-head {
  display: flex; justify-content: space-between; align-items: center;
  padding-bottom: 6px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 6px;
}
.r-filters-info-popover-title {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text-muted);
}
.r-filters-info-popover-body {
  display: flex; flex-direction: column; gap: 4px;
}
.r-filter-row {
  display: flex; align-items: center; gap: 8px;
  padding: 4px 6px;
  border-radius: 5px;
  font-size: .82rem;
  transition: background-color .12s ease;
}
.r-filter-row:hover { background: var(--field-bg); }
.r-filter-row-label {
  color: var(--text-muted);
  font-weight: 500;
  flex-shrink: 0;
}
.r-filter-row-value {
  color: var(--text);
  font-weight: 700;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.r-filter-chip-clear {
  background: transparent;
  border: none;
  color: var(--text-muted);
  width: 20px; height: 20px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0;
  flex-shrink: 0;
}
.r-filter-chip-clear:hover {
  background: rgba(220, 38, 38, .15);
  color: #dc2626;
}
.r-filters-clear-all {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-size: .72rem;
  text-decoration: underline;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 4px;
  font-style: italic;
}
.r-filters-clear-all:hover {
  color: #dc2626;
  background: rgba(220, 38, 38, .08);
}

/* Bottom-of-results summary line — displaced from the top to make room
   for the active-report selector. Less prominent visually than the
   selector — it's reference info, not the focus. */
.r-summary-bottom {
  margin-top: 12px;
  padding: 8px 14px;
  font-size: .82rem;
  color: var(--text-muted);
  background: var(--field-bg);
  border-radius: 6px;
  text-align: center;
}
.r-summary-bottom b { color: var(--text); font-variant-numeric: tabular-nums; }
.r-summary-bottom .sep { margin: 0 8px; opacity: .5; }
.r-results { width: 100%; }
.r-table-wrap {
  overflow-x: auto; overflow-y: auto;
  max-height: 70vh;
  margin-top: .5rem;
  border: 1px solid var(--border); border-radius: 6px;
  width: max-content; max-width: 100%;
}
.r-table { width: auto; }
.r-table thead th {
  position: sticky; top: 0; z-index: 2;
  background: var(--surface-2);
}
.r-table tfoot td {
  position: sticky; bottom: 0; z-index: 1;
}
.r-table th[data-sort-key] { user-select: none; cursor: pointer; }
.r-table th[data-sort-key]:hover { color: var(--primary); }
/* Override inherited .cz-pivot first-child centering & fixed width for r-table:
   left-align text cells, right-align numeric (NrDoc when grouped). */
.r-table th:first-child,
.r-table td:first-child {
  width: auto !important;
  max-width: none !important;
  text-align: left !important;
}
.r-table th.num:first-child,
.r-table td.num:first-child { text-align: right !important; }
.r-table th[data-sort-key="Canal"] { min-width: 170px !important; }
.r-table tfoot td { font-weight: 700; }
/* Subtotal rows in raport dinamic — top group level (l0) gets stronger emphasis */
.r-table tr.r-subtotal td {
  background: var(--surface-2);
  font-weight: 600;
  border-top: 1px solid var(--border-strong, var(--border));
}
.r-table tr.r-subtotal-l0 td {
  background: color-mix(in srgb, var(--brand-accent) 12%, var(--surface-2));
  border-top: 2px solid var(--brand-accent);
}
[data-theme="dark"] .r-table tr.r-subtotal td {
  background: rgba(45, 139, 142, 0.10);
}
[data-theme="dark"] .r-table tr.r-subtotal-l0 td {
  background: rgba(45, 139, 142, 0.20);
}

/* Pivot 1 yellow total — applies to any pivot tfoot (Centralizator + Decont) */
.cz-pivot tfoot .cz-total-yellow td {
  background: #fde047;
  font-weight: 700;
  border-top: 2px solid #1e3a8a;
  border-bottom: 2px solid #1e3a8a;
  padding: .45rem .65rem;
}
.cz-pivot tfoot .cz-total-yellow .cz-total-label {
  text-align: right;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #1e3a8a;
  font-size: .85rem;
}
/* Strong emphasis variant for Decont's Pivot 1 grand total */
.cz-pivot tfoot .cz-total-emph td {
  background: #fde047;
  border-top: 3px double #1e3a8a;
  border-bottom: 3px double #1e3a8a;
  padding: .55rem .75rem;
  font-size: .95rem;
}
.cz-pivot tfoot .cz-total-emph .cz-total-label {
  font-size: .95rem;
}

/* Section divider between pivots */
.cz-section-divider {
  border-top: 3px solid #1e3a8a;
  margin: 1rem 0 .25rem;
}

/* Pivot 2 currency tag */
.cz-pivot-2-wrap { margin-bottom: 1rem; }
.cz-currency-tag {
  text-align: right;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: .25rem;
  font-size: .9rem;
}

/* Pivot 2 subtotals (tan/beige rows) */
.cz-pivot-2 .cz-subtotal td {
  background: #d6c79b;
  font-weight: 600;
  border-top: 1px solid #b8a76d;
  border-bottom: 1px solid #b8a76d;
  padding: .35rem .65rem;
}
.cz-pivot-2 .cz-subtotal td:first-child { color: #4b3f17; }
.cz-pivot-2 .cz-subtotal:hover td { background: #d6c79b; }

/* Pivot 2 grand total */
.cz-pivot-2 tfoot .cz-grand-total td {
  background: transparent;
  color: #1e3a8a;
  font-weight: 700;
  border-top: 1.5px solid #1e3a8a;
  border-bottom: 3px double #1e3a8a;
  padding: .45rem .65rem;
  font-size: .9rem;
}

/* Footer */
.cz-footer {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1.5rem;
  margin-top: 2rem;
  padding-top: 1rem;
}
.cz-foot-cell {
  display: flex;
  flex-direction: column;
  gap: .25rem;
}
.cz-foot-cell .lbl { font-weight: 600; color: #1f2937; font-size: .9rem; }
.cz-foot-cell .cz-line {
  border-bottom: 1.5px dashed #9ca3af;
  height: 1px;
  margin-top: auto;
}
.cz-foot-cell .cz-issuer-name {
  font-style: italic;
  font-size: .8rem;
  color: var(--brand-dark);
  margin-top: .35rem;
  font-weight: 500;
}
.cz-foot-issuer {
  align-self: end;
  text-align: right;
  font-style: italic;
  font-size: .8rem;
  color: #6b7280;
}

/* ========== Decont (Pivot 1 drill-down report) ========== */
.cz-decont-col {
  width: 110px !important;
  min-width: 110px !important;
  max-width: 110px !important;
  text-align: center !important;
  padding: 2px 4px !important;
}
.btn-decont {
  font-size: .75rem;
  padding: .25rem .5rem;
  background: var(--brand-light);
  color: var(--brand-dark);
  border: 1px solid var(--brand);
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  white-space: nowrap;
  transition: all .15s;
}
.btn-decont:hover {
  background: var(--brand);
  color: #fff;
  transform: translateY(-1px);
}

.decont-page .cz-titles { margin-bottom: 8px; }
.decont-co-meta {
  display: block;
  color: var(--text-muted);
  font-size: .82rem;
  line-height: 1.35;
  margin-top: 4px;
}
.cz-title-r .decont-co-meta { text-align: right; }

.decont-doc-title {
  text-align: center;
  margin: .5rem 0 1rem;
  padding: .5rem 0;
  border-top: 2px solid var(--brand-dark);
  border-bottom: 2px solid var(--brand-dark);
}
.decont-doc-title h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--brand-dark);
  letter-spacing: .05em;
  margin: 0;
}
.decont-doc-period {
  font-style: italic;
  color: var(--text-muted);
  font-size: .92rem;
  margin-top: .15rem;
}

.decont-filters {
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;        /* force the two filter rows to stack vertically */
  gap: .5rem;
  align-items: flex-start;
}
.decont-filters .cz-filter-inline {
  width: fit-content;
  display: flex;                 /* block-level flex (not inline-flex) so each row stands alone */
}
.decont-filters select { min-width: 140px; }
/* An & Luna are short numeric selects → make them narrower */
.decont-filters #dAn,
.decont-filters #dLuna {
  min-width: 56px;
  width: auto;
  padding-right: 1.5rem;
}

.decont-section-label {
  font-size: .85rem;
  font-weight: 700;
  color: var(--brand-dark);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin: 1rem 0 .35rem;
  padding-bottom: .25rem;
  border-bottom: 1px solid var(--border);
}

.decont-pivot-1 thead th,
.decont-pivot-2 thead th {
  background: var(--brand-50);
  font-size: 1.03rem;          /* was .82rem · +2px header (matches new cz-pivot th) */
}

/* Currency column distinction in Decont Pivot 2:
   .col-orig = invoice currency (Valoare/Total in EUR/HUF/RON as charged)
   .col-ron  = RON-converted value (TVA RON, Total RON) */
.decont-pivot-2 .col-orig {
  background: rgba(255, 251, 230, .55);   /* very subtle warm tint */
  font-style: italic;
}
.decont-pivot-2 thead .col-orig,
.decont-pivot-2 thead .col-ron {
  font-style: normal;
}
.decont-pivot-2 .col-ron {
  background: rgba(207, 250, 254, .55);   /* very subtle cyan tint = brand-light */
  font-weight: 500;
}
.decont-pivot-2 .col-curr {
  font-size: .8rem;
  color: #64748b;
  font-weight: 500;
  margin-left: .15rem;
}
/* When cells appear inside a yellow total row, the yellow background should win */
.decont-pivot-2 tfoot .cz-total-yellow .col-orig,
.decont-pivot-2 tfoot .cz-total-yellow .col-ron {
  background: #fde047 !important;
  font-style: normal;
}
/* When inside an articol-total row, the beige background should win */
.decont-pivot-2 .decont-articol-total .col-orig,
.decont-pivot-2 .decont-articol-total .col-ron {
  background: #faf7e6 !important;
}

.decont-articol-total {
  background: #faf7e6 !important;
  font-style: italic;
}
.decont-articol-total td { border-top: 1px solid #d6cf95 !important; }

/* Dark theme overrides for currency-distinction columns and articol totals */
[data-theme="dark"] .decont-pivot-1 thead th,
[data-theme="dark"] .decont-pivot-2 thead th { background: rgba(45, 139, 142, 0.18); }
[data-theme="dark"] .decont-pivot-2 .col-orig { background: rgba(120, 53, 15, .35); }       /* darker amber tint */
[data-theme="dark"] .decont-pivot-2 .col-ron  { background: rgba(19, 78, 74, .35); }        /* darker teal tint */
[data-theme="dark"] .decont-pivot-2 tfoot .cz-total-yellow .col-orig,
[data-theme="dark"] .decont-pivot-2 tfoot .cz-total-yellow .col-ron { background: #422006 !important; color: #fef3c7; }
[data-theme="dark"] .decont-pivot-2 .decont-articol-total .col-orig,
[data-theme="dark"] .decont-pivot-2 .decont-articol-total .col-ron,
[data-theme="dark"] .decont-articol-total { background: #3e3520 !important; color: #d6c79b; }
[data-theme="dark"] .decont-articol-total td { border-top-color: #5a4f30 !important; }
[data-theme="dark"] .decont-pivot-2 .col-curr { color: #94a3b8; }

/* Note contabile dark overrides for col-orig/col-ron */
[data-theme="dark"] .decont-note-table .col-orig { background: rgba(120, 53, 15, .35); }
[data-theme="dark"] .decont-note-table .col-ron  { background: rgba(19, 78, 74, .45); }
[data-theme="dark"] .decont-note-table tbody tr.note-vat-row td { background: rgba(19, 78, 74, .25); color: #e2e8f0; }
[data-theme="dark"] .decont-note-table tbody tr.note-vat-row .col-ron { background: rgba(19, 78, 74, .55); }
[data-theme="dark"] .decont-note-table tbody tr.note-vat-row .col-orig { background: rgba(120, 53, 15, .25); }

.decont-grand-total {
  display: flex;
  justify-content: flex-end;
  align-items: baseline;
  gap: 1rem;
  margin: .75rem 0 1.25rem;
  padding: .65rem 1rem;
  background: linear-gradient(90deg, transparent, var(--brand-50));
  border-top: 2px double var(--brand-dark);
  border-bottom: 2px double var(--brand-dark);
}
.decont-grand-total .lbl {
  font-weight: 600;
  color: var(--brand-dark);
  text-transform: uppercase;
  font-size: .85rem;
  letter-spacing: .03em;
}
.decont-grand-total .val {
  font-weight: 800;
  font-size: 1.25rem;
  color: var(--brand-dark);
  font-family: 'Calibri', sans-serif;
}

/* ========== Decont tabs ========== */
.decont-tabs {
  display: flex;
  gap: .25rem;
  border-bottom: 2px solid var(--brand-dark);
  margin: 1.25rem 0 .75rem;
  padding-left: .25rem;
}
.decont-tab {
  background: transparent;
  border: 1px solid transparent;
  border-bottom: none;
  padding: .55rem 1.1rem;
  cursor: pointer;
  font-size: .95rem;
  font-weight: 600;
  color: var(--text-muted);
  border-radius: 6px 6px 0 0;
  margin-bottom: -2px;
  transition: all .15s;
  font-family: inherit;
}
.decont-tab:hover {
  background: var(--brand-50);
  color: var(--brand-dark);
}
.decont-tab.active {
  background: var(--brand-50);
  border-color: var(--brand-dark);
  border-bottom: 2px solid var(--brand-50);  /* hide the bottom border by matching bg */
  color: var(--brand-dark);
}
.decont-tab-panel.hidden { display: none; }

/* Note contabile — info banner */
.decont-note-info {
  background: #ecfeff;
  border-left: 3px solid var(--brand);
  padding: .55rem .85rem;
  border-radius: 4px;
  margin-bottom: .75rem;
  font-size: .85rem;
  color: #134e4a;
}
.decont-note-info b { color: var(--brand-darker); }
[data-theme="dark"] .decont-note-info { background: #042f2e; color: #99f6e4; }
[data-theme="dark"] .decont-note-info b { color: #5eead4; }
[data-theme="dark"] .decont-tab:hover,
[data-theme="dark"] .decont-tab.active { background: #134e4a; color: #99f6e4; }
[data-theme="dark"] .decont-note-table thead th { background: #134e4a; }
[data-theme="dark"] .decont-note-table tbody tr.note-net-row td { border-top-color: #2a3346; }

/* Note contabile table */
.decont-note-table thead th {
  background: var(--brand-50);
  font-size: 1.03rem;
}
/* NDP column wider than the generic .cz-pivot 56px — fits "C01-12" comfortably */
.decont-note-table th:first-child,
.decont-note-table td:first-child {
  width: 80px;
  min-width: 80px;
  max-width: 80px;
}
/* Net line (TZ-side movement) — slight separator at the top to mark group boundary */
.decont-note-table tbody tr.note-net-row td {
  border-top: 1px solid #e2e8f0;
}
.decont-note-table tbody tr.note-net-row:first-child td {
  border-top: none;
}
/* TVA line — subtle cyan tint to distinguish from net line */
.decont-note-table tbody tr.note-vat-row td {
  background: rgba(207, 250, 254, .35);
  color: #1f2937;
  border-bottom: 0;
}
.decont-note-table .col-orig {
  background: rgba(255, 251, 230, .55);
  font-style: italic;
}
.decont-note-table .col-ron {
  background: rgba(207, 250, 254, .55);
  font-weight: 600;
}
.decont-note-table tbody tr.note-vat-row .col-ron {
  background: rgba(207, 250, 254, .65);
}
.decont-note-table tbody tr.note-vat-row .col-orig {
  background: rgba(255, 251, 230, .4);
}

/* ========== Print / PDF Export ========== */
@media print {
  /* Hide everything except centralizator content */
  body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
  #app {
    display: block !important;
    grid-template-columns: none !important;
    grid-template-rows: none !important;
    grid-template-areas: none !important;
  }
  #app::before { display: none !important; }
  #sidebar, .sb-header, .sb-infoOverlay, .app-bar, #busy, #toasts, .modal-overlay, .page-actions,
  .cz-banner-tools, .btn-icon-tool, .cz-decont-col, .btn-decont { display: none !important; }
  #main, #content {
    margin: 0 !important; padding: 0 !important;
    background: transparent !important;
    box-shadow: none !important; border: none !important;
    overflow: visible !important;
    width: 100% !important;
    max-width: none !important;
  }
  /* The centralizator card */
  .centralizator {
    border: none !important;
    padding: 0 !important;
    background: transparent !important;
    overflow: visible !important;
    color: #000 !important;
  }
  /* Topbar tighter for print + restore right alignment when toolbar hidden */
  .cz-topbar {
    display: grid !important;
    grid-template-columns: 1fr !important;
    margin-bottom: .5rem !important;
    gap: .5rem !important;
    width: 100% !important;
  }
  .cz-titles {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    width: 100% !important;
    align-items: end !important;
    margin-bottom: 12px !important;
  }
  .cz-title-l, .cz-title-l h1 { text-align: left !important; }
  .cz-title-r, .cz-title-r h1, .cz-title-r .cz-mkp-label {
    text-align: right !important;
  }
  .cz-title-l h1, .cz-title-r h1 {
    font-size: 18pt !important;
    color: #1e3a8a !important;
    margin: 0 !important;
  }
  .cz-mkp-label { font-size: 8pt !important; }
  /* Filter bar compact */
  .cz-filters {
    margin-bottom: .5rem !important;
    padding: 4px 6px !important;
    background: #fffbe6 !important;
    border: 0.5pt solid #d1c684 !important;
  }
  .cz-num-deconturi .lbl { font-size: 9pt !important; }
  .cz-num-deconturi .val { font-size: 14pt !important; }
  .cz-fil-lbl, .cz-fil-val select {
    font-size: 9pt !important; padding: 2px 6px !important;
  }
  .cz-doc-ref { font-size: 8pt !important; }
  /* Inline filter — hide select dropdown chrome, show value as text */
  .cz-fil-val select {
    -webkit-appearance: none;
    appearance: none;
    background: transparent !important;
    border: none !important;
  }
  /* Anexe banner — first row in print */
  .cz-banner-row {
    margin-bottom: .35rem !important;
    padding-bottom: .25rem !important;
  }
  .cz-anexe-banner { font-size: 9pt !important; }
  .cz-currency-tag { font-size: 9pt !important; margin-bottom: .15rem !important; }
  .cz-section-divider { margin: .4rem 0 !important; border-top-width: 2px !important; }

  /* Tables */
  .cz-pivot {
    width: 100% !important;
    font-size: 8.5pt !important;
    page-break-inside: auto;
    border-collapse: collapse !important;
  }
  /* Allow tables to shrink on print so all columns fit */
  .cz-pivot-1-wrap, .cz-pivot-2-wrap, .decont-pivot-wrap {
    overflow: visible !important;
    width: 100% !important;
  }
  .cz-pivot thead { display: table-header-group; }
  .cz-pivot tfoot { display: table-row-group; }
  .cz-pivot tr { page-break-inside: avoid; page-break-after: auto; }
  .cz-pivot th {
    font-size: 8pt !important;
    background: #f3f4f6 !important;
    border-bottom: 1pt solid #1e3a8a !important;
    padding: 3pt 4pt !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .cz-pivot td {
    padding: 2.5pt 4pt !important;
    font-size: 8.5pt !important;
    border-bottom: 0.25pt solid #e5e7eb !important;
  }
  /* Keep Nr.Crt column narrow & aligned in print too */
  .cz-pivot th:first-child,
  .cz-pivot td:first-child {
    width: 36pt !important;
    min-width: 36pt !important;
    max-width: 36pt !important;
  }
  /* Force background colors to print (subtotals, yellow total, etc.) */
  .cz-pivot tfoot .cz-total-yellow td,
  .cz-pivot tfoot .cz-total-emph td,
  .cz-pivot-2 .cz-subtotal td,
  .cz-pivot-2 .cz-grand-total td,
  .decont-pivot-2 .col-orig,
  .decont-pivot-2 .col-ron,
  .cz-filters {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }
  /* Avoid awkward breaks */
  .cz-topbar, .cz-filters, .cz-pivot-1-header { page-break-after: avoid; }
  .cz-pivot-1-wrap { page-break-inside: avoid; }
  /* Pivot 2 can naturally break — header repeats */

  /* Footer signatures: tighter */
  .cz-footer { margin-top: .8rem !important; font-size: 8pt !important; page-break-inside: avoid; }
  .cz-foot-cell .cz-line { margin-top: auto !important; }
  .cz-foot-issuer { font-size: 7pt !important; }

  /* Hide refresh and other buttons that survived */
  button { display: none !important; }

  /* ==== Decont/Centralizator title bar (shared) print rules ==== */
  .decont-doc-title {
    margin: .25rem 0 .55rem !important;
    padding: .2rem 0 !important;
    border-top: 1.2pt solid #1e3a8a !important;
    border-bottom: 1.2pt solid #1e3a8a !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .decont-doc-title h2 { font-size: 12pt !important; color: #1e3a8a !important; }
  .decont-doc-period { font-size: 8.5pt !important; }

  /* ==== Decont-specific print rules ==== */
  /* Tabs hidden on print; only the active panel prints */
  .decont-tabs { display: none !important; }
  .decont-tab-panel.hidden { display: none !important; }
  .decont-note-info { font-size: 7.5pt !important; padding: .25rem .4rem !important; margin-bottom: .35rem !important; }
  .decont-note-table .eq { width: 14pt !important; }

  /* Decont filter rows always stack vertically and show full content at print */
  .decont-filters {
    display: flex !important;
    flex-direction: column !important;
    gap: .25rem !important;
    align-items: flex-start !important;
    background: #fffbe6 !important;
    border: 0.5pt solid #d1c684 !important;
    padding: 4px 6px !important;
    margin-bottom: .5rem !important;
  }
  .decont-filters .cz-filter-inline {
    overflow: visible !important;
    max-width: none !important;
    width: fit-content !important;
    display: flex !important;
  }

  .decont-page .decont-section-label {
    font-size: 8.5pt !important;
    margin: .55rem 0 .25rem !important;
    color: #1e3a8a !important;
    border-bottom: 0.5pt solid #d1d5db !important;
  }
  .decont-page .decont-articol-total {
    background: #faf7e6 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .decont-page .decont-articol-total td {
    border-top: 0.5pt solid #d6cf95 !important;
    font-style: italic !important;
  }
  .decont-page .decont-grand-total {
    margin: .5rem 0 .65rem !important;
    padding: .35rem .65rem !important;
    border-top: 1.5pt double #1e3a8a !important;
    border-bottom: 1.5pt double #1e3a8a !important;
    background: #ecfeff !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    page-break-inside: avoid;
  }
  .decont-page .decont-grand-total .lbl { font-size: 9pt !important; color: #1e3a8a !important; }
  .decont-page .decont-grand-total .val { font-size: 13pt !important; color: #1e3a8a !important; }
  .decont-page .decont-co-meta { font-size: 8pt !important; }
}

</style>
</head>
<body>

<!-- ============== Login overlay (multi-user, server-side auth) ============== -->
<div class="sb-loginOverlay" id="loginOverlay" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
  <form class="sb-loginCard" id="loginForm" autocomplete="on" novalidate>
    <div class="sb-loginBrand">
      <div class="sb-loginBrandText"><span>smart</span>BIZ Copilot</div>
    </div>
    <h2 class="sb-loginTitle" id="loginTitle">Autentificare</h2>
    <p class="sb-loginSubtitle" id="loginSubtitle">EcR Deconturi · eMag Marketplace</p>

    <div class="sb-setupBanner" id="setupBanner" hidden>
      <strong>Configurare inițială.</strong> Acesta e primul cont și va fi
      <b>administrator</b>. Folosește un email și o parolă pe care le poți reține —
      nu există recuperare automată.
    </div>

    <div class="sb-loginError" id="loginError" role="alert"></div>

    <div class="sb-loginField">
      <label for="loginEmail">Email</label>
      <input id="loginEmail" name="email" type="email" autocomplete="username" required spellcheck="false" />
    </div>
    <div class="sb-loginField">
      <label for="loginPassword">Parolă</label>
      <input id="loginPassword" name="password" type="password" autocomplete="current-password" required />
    </div>
    <div class="sb-loginField" id="confirmField" hidden>
      <label for="loginConfirm">Confirmă parola</label>
      <input id="loginConfirm" name="confirm" type="password" autocomplete="new-password" />
    </div>
    <button type="submit" class="sb-loginBtn" id="loginSubmit">Intră în cont</button>

    <div class="sb-loginFoot" id="loginFoot">
      Nu ai cont? Conturile sunt create de administrator.
    </div>
  </form>
</div>

<div id="app">
  <header class="sb-header">
    <div class="sb-headerInner">
      <div class="sb-headerLeft">
        <div class="sb-logo">
          <span class="sb-logoText">EcR</span>
          <svg width="44" height="44" viewBox="0 0 44 44" style="position:absolute;top:0;left:0">
            <circle cx="33" cy="8" r="3" fill="#2D8B8E"></circle>
            <circle cx="8" cy="10" r="2" fill="rgba(255,255,255,0.4)"></circle>
            <circle cx="36" cy="24" r="2" fill="rgba(255,255,255,0.35)"></circle>
            <line x1="8" y1="10" x2="20" y2="7" stroke="rgba(255,255,255,0.2)" stroke-width="1"></line>
            <line x1="20" y1="7" x2="33" y2="8" stroke="#2D8B8E" stroke-width="1" opacity="0.5"></line>
          </svg>
        </div>
        <div>
          <h1 class="sb-brandTitle">
            <span class="sb-brandSmart">smart</span><span class="sb-brandBiz">BIZ</span>
            <span class="sb-brandCopilot">
              Copilot
              <button type="button" class="sb-iconBtn" id="infoBtn" title="Despre modul" aria-label="Despre modul">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="9"></circle>
                  <path d="M12 10v6"></path>
                  <circle cx="12" cy="7" r="1" fill="currentColor" stroke="none"></circle>
                </svg>
              </button>
            </span>
          </h1>
          <div class="sb-moduleMeta">
            <div class="sb-moduleDot"></div>
            <span class="sb-moduleName">EcR Deconturi · eMag Marketplace</span>
            <span class="sb-moduleVersion">v1.0</span>
          </div>
        </div>
      </div>

      <div class="sb-headerRight">
        <div class="sb-headerRightCol">
          <button class="sb-themeToggle" id="themeToggle" type="button" aria-label="Schimbă temă">
          <div class="sb-themeSeg sb-light-seg" title="Temă luminoasă">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
              <circle cx="12" cy="12" r="4"></circle>
              <path d="M12 2v2"></path><path d="M12 20v2"></path>
              <path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path>
              <path d="M2 12h2"></path><path d="M20 12h2"></path>
              <path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path>
            </svg>
          </div>
          <div class="sb-themeSeg sb-dark-seg" title="Temă întunecată">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
            </svg>
          </div>
        </button>
        <div class="sb-copyright">Creat de <strong>AIALL</strong> ©2026</div>
        </div><!-- /.sb-headerRightCol -->

        <button type="button" class="sb-helpBtn" id="helpBtn" title="Ajutor pentru această pagină" aria-label="Deschide panou ajutor">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </button>

        <div class="sb-userMenu" id="userMenu" hidden>
          <button type="button" class="sb-userAvatar" id="userAvatarBtn" aria-haspopup="true" aria-expanded="false" aria-label="Meniu utilizator">
            <span class="sb-userAvatar-initials" id="userAvatarInitials">·</span>
            <span class="sb-userAvatar-dot" id="userAvatarDot" hidden title="Cont cu drept de vizualizare"></span>
          </button>
          <div class="sb-userDropdown" id="userDropdown" hidden role="menu" aria-labelledby="userAvatarBtn">
            <div class="sb-userDropdown-head">
              <div class="sb-userAvatar-large" id="userAvatarLarge">·</div>
              <div class="sb-userDropdown-id">
                <div class="sb-userDropdown-name" id="userDropdownName" hidden></div>
                <div class="sb-userDropdown-email" id="userDropdownEmail"></div>
                <div class="sb-userDropdown-roles">
                  <span class="sb-userRole sb-userRole-admin" id="userRoleAdmin" hidden>Administrator</span>
                  <span class="sb-userRole sb-userRole-readonly" id="userRoleReadonly" hidden>🔒 Vizualizare</span>
                </div>
              </div>
            </div>
            <div class="sb-userDropdown-divider"></div>
            <button type="button" class="sb-userDropdown-item" id="logoutBtn" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
              <span>Ieșire din cont</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Info modal: Despre EcR Deconturi -->
  <div class="sb-infoOverlay" id="infoOverlay" role="dialog" aria-modal="true">
    <div class="sb-infoModal">
      <div class="sb-infoTop">
        <div class="sb-infoTopRow">
          <div style="min-width:0">
            <div class="sb-infoKicker">Descriere aplicație</div>
            <h2 class="sb-infoTitle">Ce face EcR Deconturi pentru utilizator</h2>
          </div>
          <button type="button" class="sb-infoCloseBtn" id="closeInfoBtn">
            Închide
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>
      </div>
      <div class="sb-infoBody">
        <p class="sb-infoText">
          Aplicația automatizează procesarea deconturilor lunare emise de eMag Marketplace
          (RO, BG, HU · MKP &amp; FBE) pentru a genera centralizatorul de documente,
          decontul detaliat per canal și notele contabile gata de import în programul de contabilitate.
        </p>

        <ul class="sb-infoBullets">
          <li class="sb-infoLi"><span class="sb-dot" aria-hidden="true"></span><span>importă fișierul Excel exportat din eMag (Financiar → Facturi) — română sau engleză, cu detectare automată a canalului din ID Seller</span></li>
          <li class="sb-infoLi"><span class="sb-dot" aria-hidden="true"></span><span>preia automat cursul valutar BNR pentru EUR și HUF (cu data emiterii − 1 zi conform reglementării)</span></li>
          <li class="sb-infoLi"><span class="sb-dot" aria-hidden="true"></span><span>generează centralizatorul lunar agregat la nivel de problematică, flux și taxare TVA, cu drill-down pe fiecare canal</span></li>
          <li class="sb-infoLi"><span class="sb-dot" aria-hidden="true"></span><span>produce decontul detaliat per canal cu sinteză operațiuni (Pivot 1) și detaliu documente (Pivot 2), cu suport multivalută</span></li>
          <li class="sb-infoLi"><span class="sb-dot" aria-hidden="true"></span><span>auto-generează notele contabile la nivel de Pivot 1 cu reguli D/C corecte pentru recunoaștere, stornare și discount, inclusiv taxare inversă</span></li>
          <li class="sb-infoLi"><span class="sb-dot" aria-hidden="true"></span><span>permite tipărire A4 landscape a centralizatorului, decontului și notelor contabile, cu antet, totaluri și paginare profesională</span></li>
        </ul>

        <div class="sb-infoResult">
          <div class="sb-infoResultKicker">Rezultat pentru utilizator</div>
          <div class="sb-infoResultText">
            Utilizatorul obține în câteva secunde, dintr-un singur fișier eMag, deconturile complete
            și notele contabile gata pentru import în SAGA, WinMentor sau SmartBill, cu echilibru
            contabil verificat (Total D = Total C) și conversie automată a sumelor în RON la cursul BNR.
          </div>
        </div>
      </div>
    </div>
  </div>

  <aside id="sidebar">
    <nav class="nav-section">
      <div class="nav-section-label">Principal</div>
      <div class="nav-separator"></div>
      <div class="nav-item active" data-route="dashboard" data-label="Dashboard"><span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span></div>
      <div class="nav-item" data-route="upload" data-label="Încărcare date"><span class="nav-icon">📤</span><span class="nav-label">Încărcare date</span></div>
      <div class="nav-item" data-route="curs" data-label="Curs Valutar"><span class="nav-icon">💱</span><span class="nav-label">Curs Valutar</span></div>
      <div class="nav-section-label">Date</div>
      <div class="nav-separator"></div>
      <div class="nav-item" data-route="rawdata" data-label="Date eMag (raw)"><span class="nav-icon">📋</span><span class="nav-label">Date eMag (raw)</span></div>
      <div class="nav-item" data-route="dataset" data-label="DataSet"><span class="nav-icon">⚙️</span><span class="nav-label">DataSet</span></div>
      <div class="nav-item" data-route="jurnal" data-label="Jurnal contabil"><span class="nav-icon">📒</span><span class="nav-label">Jurnal contabil</span></div>
      <div class="nav-section-label">Rapoarte</div>
      <div class="nav-separator"></div>
      <div class="nav-item" data-route="centralizator" data-label="Centralizator"><span class="nav-icon">📊</span><span class="nav-label">Centralizator</span></div>
      <div class="nav-item" data-route="decont" data-label="Decont" style="padding-left:1.5rem"><span class="nav-icon">📋</span><span class="nav-label">Decont</span></div>
      <div class="nav-item" data-route="raport-dinamic" data-label="Raport dinamic"><span class="nav-icon">🔍</span><span class="nav-label">Raport dinamic</span></div>
      <div class="nav-section-label">Configurări</div>
      <div class="nav-separator"></div>
      <div class="nav-item" data-route="nomenclator" data-label="Nomenclator"><span class="nav-icon">📚</span><span class="nav-label">Nomenclator</span></div>
      <div class="nav-item" data-route="marketplaces" data-label="Marketplaces"><span class="nav-icon">🏪</span><span class="nav-label">Marketplaces</span></div>
      <div class="nav-item" data-route="settings" data-label="Setări"><span class="nav-icon">⚙️</span><span class="nav-label">Setări</span></div>
      <div class="nav-section-label admin-only">Administrare</div>
      <div class="nav-separator admin-only"></div>
      <div class="nav-item admin-only" data-route="users" data-label="Utilizatori"><span class="nav-icon">👥</span><span class="nav-label">Utilizatori</span></div>
    </nav>
    <div class="sidebar-footer">
      <button class="sidebar-toggle" id="sidebarToggle" title="Comutare meniu">
        <span class="chev">‹</span>
      </button>
    </div>
  </aside>
  <main id="main"><div id="content"></div></main>

  <aside id="helpPanel" role="complementary" aria-label="Ajutor pagină">
    <div class="help-head">
      <h2 class="help-title" id="helpTitle">Ajutor</h2>
      <button type="button" class="help-close" id="helpClose" title="Închide ajutorul" aria-label="Închide ajutorul">✕</button>
    </div>
    <div class="help-body" id="helpBody">
      <p>Conținutul de ajutor pentru pagina curentă apare aici.</p>
    </div>
  </aside>
</div>
<div id="toasts"></div>
<div id="busy"><div class="box"><div class="spinner"></div><div id="busy-msg">Se procesează...</div></div></div>
<script>const NOMENCLATOR_DATA = [{"Cod":"FAACP","Tranzactie":"Avans promovare","Formula contabila":"4092 = 401","Cont factura":401,"Cont articol":4092,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads  - click-uri Ads din limita de credit"},{"Cod":"FACCP","Tranzactie":"Ads pre-paid","Formula contabila":"4092 = 623","Cont factura":4092,"Cont articol":623,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads -  credit Ads Free"},{"Cod":"FAPC","Tranzactie":"Discount cost promovare","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads  - justificare plata credit"},{"Cod":"FAPO","Tranzactie":"Ads post-paid","Formula contabila":"623 = 401","Cont factura":401,"Cont articol":623,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads - discount oferit de mkt - credit Ads Free"},{"Cod":"FAPOF","Tranzactie":"Cost promovare din discount","Formula contabila":"623 = 401","Cont factura":401,"Cont articol":623,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads - consumare credit platit"},{"Cod":"FAPOV","Tranzactie":"Ads post-paid","Formula contabila":"623 = 401","Cont factura":401,"Cont articol":623,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads - avans credit Ads"},{"Cod":"FASCP","Tranzactie":"Ads pre-paid","Formula contabila":"4092 = 401","Cont factura":401,"Cont articol":4092,"Articol":"Ads pre-paid","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Promovare","Canal":"MKP","Status":"Activ","Detalii":"Promovare Ads - storno avans  credit Ads"},{"Cod":"FC","Tranzactie":"Comision vanzare","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Comision mktp pe produs din comanda CARD anulata din vina Quasar"},{"Cod":"FCA","Tranzactie":"Comision vanzare - Storno","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Comision mktp - storno"},{"Cod":"FCCD","Tranzactie":"Comision vanzare","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Comision mktp pe produs din comanda  CARD anulata din vina Quasar - Storno"},{"Cod":"FCCDA","Tranzactie":"Comision vanzare - Storno","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Comision mktp stornat - storno"},{"Cod":"FCCO","Tranzactie":"Comision vanzare","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Discount la comision mktp"},{"Cod":"FCCOA","Tranzactie":"Comision vanzare - Storno","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Discount participare in program Genius"},{"Cod":"FCDP","Tranzactie":"Discount special intrari","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"FBE","Status":"Activ","Detalii":"Servicii fullfilment: livrare , packing, picking - storno"},{"Cod":"FCDPA","Tranzactie":"Discount special intrari - Storno","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"FBE","Status":"Activ","Detalii":"Servicii fullfilment: livrare , packing, picking - Discount"},{"Cod":"FCS","Tranzactie":"Comision vanzare","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Storno Discount la comision mktp"},{"Cod":"FCSA","Tranzactie":"Comision vanzare - Storno","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Comision marketplace","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Comision marketplace","Canal":"MKP","Status":"Activ","Detalii":"Comision Genius"},{"Cod":"FE","Tranzactie":"Discount eMAG Genius","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"FBE","Status":"Activ","Detalii":"Servicii fullfilment: livrare , packing, picking"},{"Cod":"FED","Tranzactie":"Comision Genius","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Comision mktp pe produs din comanda Ramburs anulata din vina Quasar"},{"Cod":"FEDA","Tranzactie":"Comision Genius - Storno","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Comision mktp pe produs din comanda Ramburs anulata din vina Quasar - Storno"},{"Cod":"FER","Tranzactie":"Genius taxa retur","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"FBE","Status":"Activ","Detalii":"Retragere din stocul emag - serviciu de fulfilment"},{"Cod":"FERA","Tranzactie":"Genius taxa retur - Storno","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Despagubire suportata de emag pe program Genius ( client Genius - retur incomplet )"},{"Cod":"FET","Tranzactie":"Taxa administrativa Genius","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Decont vouchere - Storno"},{"Cod":"FETA","Tranzactie":"Taxa administrativa Genius - Storno","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Voucher acordat de Quasar pentru produs neconform"},{"Cod":"FETS","Tranzactie":"Taxa administrativa Genius","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Decont vouchere comenzi stornate Ramburs - storno"},{"Cod":"FETSA","Tranzactie":"Taxa administrativa Genius - Storno","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Livrare Genius","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Taxa Genius","Canal":"MKP","Status":"Activ","Detalii":"Decont gift card date de emag in schimbul returnarii de bani, pentru comenzi returnate"},{"Cod":"FFD","Tranzactie":"Depozitare","Formula contabila":"612 = 401","Cont factura":401,"Cont articol":612,"Articol":"FBE - Depozitare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Taxa program Genius - comenzi stornate"},{"Cod":"FFDA","Tranzactie":"Depozitare - Storno","Formula contabila":"612 = 401","Cont factura":401,"Cont articol":612,"Articol":"FBE - Depozitare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Taxa program Genius - comenzi stornate - Storno"},{"Cod":"FFDP","Tranzactie":"Promotie depozitare FBE","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"FBE - Depozitare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Despagubire produse deteriorate"},{"Cod":"FFDPA","Tranzactie":"Storno promotie depozitare FBE","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"FBE - Depozitare","Problematica":"Cumparare | Intrare","TipTVA":2,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Despagubire produse deteriorate"},{"Cod":"FFO","Tranzactie":"Fulfillment comenzi","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"FBE - Pregatire livrare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Inactiv","Detalii":"Taxa transport comenzi FBE"},{"Cod":"FFOA","Tranzactie":"Fulfillment comenzi - Storno","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"FBE - Pregatire livrare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Inactiv","Detalii":"Taxa transport comenzi FBE - storno"},{"Cod":"FFOP","Tranzactie":"Promotie procesare comenzi","Formula contabila":"609 = 401","Cont factura":401,"Cont articol":609,"Articol":"FBE - Pregatire livrare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Corectie factura"},{"Cod":"FFR","Tranzactie":"Procesare retur FBE","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"FBE - Pregatire livrare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Despagubire suportata de emag pentru colete deteriorate in timpul transportului"},{"Cod":"FFT","Tranzactie":"Colectare, impachetare si livrare comenzi FBE","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"FBE - Transport livrare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Comision mktp"},{"Cod":"FFTS","Tranzactie":"Colectare, impachetare si livrare comenzi FBE","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"FBE - Transport livrare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Comision mktp - storno"},{"Cod":"FFX","Tranzactie":"Retragere din FBE","Formula contabila":"612 = 401","Cont factura":401,"Cont articol":612,"Articol":"FBE - Depozitare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Decont vouchere"},{"Cod":"FHDR","Tranzactie":"Despagubire produse Genius","Formula contabila":"461 = 7581","Cont factura":461,"Cont articol":7581,"Articol":"Livrare Genius","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Despagubire Genius Produse","Canal":"FBE","Status":"Activ","Detalii":"Depozitare FBE - reducere costuri conform Promotie"},{"Cod":"FHIC","Tranzactie":"Despagubire curier FBE","Formula contabila":"461 = 7581","Cont factura":461,"Cont articol":7581,"Articol":"FBE - Despagubire","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Despagubire FBE Curier","Canal":"FBE","Status":"Activ","Detalii":"Depozitare FBE"},{"Cod":"FHR","Tranzactie":"Despagubire produse FBE","Formula contabila":"461 = 7581","Cont factura":461,"Cont articol":7581,"Articol":"FBE - Despagubire","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Despagubire FBE Produse","Canal":"FBE","Status":"Activ","Detalii":"Depozitare FBE"},{"Cod":"FFS","Tranzactie":"Servicii de casare","Formula contabila":"621 = 401","Cont factura":401,"Cont articol":621,"Articol":"Servicii casare","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii FBE","Canal":"FBE","Status":"Activ","Detalii":"Servicii casare FBE"},{"Cod":"FR","Tranzactie":"Corectie factura","Formula contabila":"622 = 401","Cont factura":401,"Cont articol":622,"Articol":"Corectie Factura","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Corectie","Canal":"MKP","Status":"Activ","Detalii":"Comision Genius - storno"},{"Cod":"FV","Tranzactie":"Decontare vouchere","Formula contabila":"461 = 418","Cont factura":461,"Cont articol":418,"Articol":"Plati / Incasari prin Emag","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"MKP","Status":"Activ","Detalii":"Discount participare in program Genius - comenzi returnate"},{"Cod":"FVA","Tranzactie":"Decontare vouchere - Storno","Formula contabila":"461 = 418","Cont factura":461,"Cont articol":418,"Articol":"Plati / Incasari prin Emag","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"MKP","Status":"Activ","Detalii":"Taxa program Genius "},{"Cod":"FVS","Tranzactie":"Decontare vouchere","Formula contabila":"461 = 418","Cont factura":461,"Cont articol":418,"Articol":"Plati / Incasari prin Emag","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"MKP","Status":"Activ","Detalii":"Storno Discount participare in program Genius - comenzi returnate"},{"Cod":"FVSA","Tranzactie":"Decontare vouchere - Storno","Formula contabila":"461 = 418","Cont factura":461,"Cont articol":418,"Articol":"Plati / Incasari prin Emag","Problematica":"Vanzare | Iesire","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"MKP","Status":"Activ","Detalii":"Taxa program Genius  - Storno"},{"Cod":"FVSM","Tranzactie":"Voucher discount vanzari","Formula contabila":"461 = 418","Cont factura":461,"Cont articol":418,"Articol":"Voucher Discount Vanzare","Problematica":"Vanzare | Iesire","TipTVA":1,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"MKP","Status":"Activ","Detalii":"Discount vanzare promovare"},{"Cod":"FVSMA","Tranzactie":"Voucher discount vanzari - Storno","Formula contabila":"461 = 418","Cont factura":461,"Cont articol":418,"Articol":"Voucher Discount Vanzare","Problematica":"Vanzare | Iesire","TipTVA":1,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"FBE","Status":"Activ","Detalii":"Discount vanzare promovare"},{"Cod":"FW","Tranzactie":"Factura de penalizare","Formula contabila":"6581 = 401","Cont factura":401,"Cont articol":6581,"Articol":"Penalizare comerciala","Problematica":"Cumparare | Intrare","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Penalizare","Canal":"FBE","Status":"Activ","Detalii":"Servicii fullfilment pentru comenzile returnate"},{"Cod":"FTIC","Tranzactie":"Factura de transport - Suplimentar","Formula contabila":"621=401","Cont factura":401,"Cont articol":621,"Articol":"Transport Suplimentar","Problematica":"Cumparare | Intrare","TipTVA":1,"TipOpsTVA":"[ ] Ops cu TZ cu TVA impozabile","Cost Canal":"Servicii MKP","Canal":"MKP","Status":"Activ","Detalii":"Taxa suplimentara de transport"},{"Cod":"FY","Tranzactie":"RMA voucher","Formula contabila":"408 = 462","Cont factura":462,"Cont articol":408,"Articol":"Voucher Despagubire Defecte","Problematica":"Cumparare | Intrare","TipTVA":0,"TipOpsTVA":"[n] Ops cu TZ cu TVA neimpozabile","Cost Canal":"Decontari","Canal":"MKP","Status":"Activ","Detalii":"Decont vouchere comenzi stornate CARd - storno"}];

MARKETPLACES_DATA = [{"IdSeller":1441,"Marketplace":"Emag MKP RO","Parteneri":"Dante International S.A.","CUI":"14399840","AF":"RO","CotaTVA":21,"Moneda":"RON","Demultiplicator":1,"Tara":"Romania","Localitate":"Bucuresti"},{"IdSeller":65672,"Marketplace":"Emag MKP RO FBE","Parteneri":"Dante International S.A.","CUI":"14399841","AF":"RO","CotaTVA":21,"Moneda":"RON","Demultiplicator":1,"Tara":"Romania","Localitate":"Bucuresti"},{"IdSeller":33310,"Marketplace":"Emag MKP HU","Parteneri":"Extreme Digital-eMAG Kft.","CUI":"13282156-2-44","AF":"HU","CotaTVA":27,"Moneda":"HUF_100","Demultiplicator":100,"Tara":"Ungaria","Localitate":"Budapesta"},{"IdSeller":146571,"Marketplace":"Emag MKP BG FBE","Parteneri":"eMag International OOD","CUI":"203187055","AF":"BG","CotaTVA":20,"Moneda":"EUR","Demultiplicator":1,"Tara":"Bulgaria","Localitate":"Sofia"},{"IdSeller":148696,"Marketplace":"Emag MKP HU FBE","Parteneri":"Extreme Digital-eMAG Kft.","CUI":"13282156-2-44","AF":"HU","CotaTVA":27,"Moneda":"HUF_100","Demultiplicator":100,"Tara":"Ungaria","Localitate":"Budapesta"},{"IdSeller":9055,"Marketplace":"Emag MKP BG","Parteneri":"eMag International OOD","CUI":"203187055","AF":"BG","CotaTVA":20,"Moneda":"EUR","Demultiplicator":1,"Tara":"Bulgaria","Localitate":"Sofia"}];
</script>
<script>// ========================== SQLite (server) Wrapper ==========================
// Mirror of the previous IndexedDB API. Same function signatures, same
// return shapes — call sites in the rest of the app are unchanged.
// All operations hit api.php (PHP + SQLite) via fetch with session cookies.
const API_URL = 'api.php';

class AuthError extends Error {
  constructor(msg) { super(msg); this.name = 'AuthError'; }
}

// Optional callback set by the auth code; invoked when a request returns 401
// so we can re-display the login overlay without further code changes.
let _onAuthLost = null;
function onAuthLost(cb) { _onAuthLost = cb; }

async function apiCall(action, payload) {
  let res;
  try {
    res = await fetch(API_URL + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {})
    });
  } catch (netErr) {
    throw new Error('Eroare de rețea: ' + netErr.message);
  }
  let json;
  try { json = await res.json(); }
  catch { throw new Error('Răspuns invalid de la server (HTTP ' + res.status + ')'); }
  if (!json.ok) {
    if (res.status === 401) {
      const err = new AuthError(json.error || 'Sesiune expirată');
      if (typeof _onAuthLost === 'function') { try { _onAuthLost(); } catch (_) {} }
      throw err;
    }
    if (res.status === 403) {
      // Forbidden — typically a non-admin attempting a write operation.
      // Surface a clear, user-friendly message regardless of the server text.
      throw new Error(json.error || 'Această operațiune necesită drepturi de administrator.');
    }
    throw new Error(json.error || ('HTTP ' + res.status));
  }
  return json.data;
}

// ----- Wrapper API (signatures match the old IndexedDB layer) -----
// openDB() existed in the old code and was awaited from init(). It has no
// equivalent server-side, so we keep it as a no-op for compatibility.
async function openDB() { return null; }

async function dbPut(storeName, value)       { return apiCall('put',     { store: storeName, value }); }
async function dbBulkPut(storeName, values)  { return apiCall('bulkPut', { store: storeName, values }); }
async function dbGet(storeName, key)         { return apiCall('get',     { store: storeName, key }); }
async function dbGetAll(storeName)           { return (await apiCall('getAll',  { store: storeName })) || []; }
async function dbCount(storeName)            { return apiCall('count',   { store: storeName }); }
async function dbClear(storeName)            { return apiCall('clear',   { store: storeName }); }
async function dbClearPeriods(storeName, periods) { return apiCall('clearPeriods', { store: storeName, periods }); }
async function dbDelete(storeName, key)      { return apiCall('delete',  { store: storeName, key }); }

// ----- Auth helpers -----
async function authMe()                      { return apiCall('me'); }
async function authStatus()                  { return apiCall('status'); }
async function authLogin(email, password)    { return apiCall('login',     { email, password }); }
async function authBootstrap(email, password){ return apiCall('bootstrap', { email, password }); }
async function authLogout()                  { return apiCall('logout'); }

// ----- Admin user-management helpers -----
async function adminUsersList()                               { return apiCall('users.list'); }
async function adminUsersCreate(payload)                      { return apiCall('users.create', payload); }
async function adminUsersUpdate(id, payload)                  { return apiCall('users.update', { id, ...payload }); }
async function adminUsersPasswd(id, password)                 { return apiCall('users.passwd', { id, password }); }
async function adminUsersDelete(id)                           { return apiCall('users.delete', { id }); }

// Track current user across the app — used to show/hide admin UI
// and guard the /users route on the client side too (defense in depth).
let _currentUser = null;
function setCurrentUser(user) {
  _currentUser = user || null;
  const adminFlag = !!(user && user.is_admin);
  document.body.classList.toggle('is-admin', adminFlag);
  // is-readonly is the inverse of admin for any logged-in user. Used by
  // CSS rules to hide write-action buttons (table edit/delete icons etc).
  document.body.classList.toggle('is-readonly', !!(user && !user.is_admin));
}
function isAdmin() { return !!(_currentUser && _currentUser.is_admin); }
function isReadOnly() { return !!(_currentUser && !_currentUser.is_admin); }
</script>
<script>// ========================== BNR Exchange Rates ==========================
// CORS strategy: try multiple endpoints + multiple proxies as fallback chain.
// First success wins.

const CURRENCY_MAP = {
  'EUR': { internal: 'EUR', demultiplicator: 1 },
  'HUF': { internal: 'HUF_100', demultiplicator: 100 }
};

// Try in order; stops at first success
function buildBnrSources(year) {
  const today = new Date();
  const isCurrentYear = year === today.getFullYear();
  const sources = [];

  // 1) Direct hits — some BNR endpoints/CDNs may set permissive CORS
  if (isCurrentYear) {
    sources.push({ kind: 'direct', url: 'https://www.bnr.ro/nbrfxrates.xml' });
    sources.push({ kind: 'direct', url: 'https://curs.bnr.ro/nbrfxrates.xml' });
  }
  sources.push({ kind: 'direct', url: `https://www.bnr.ro/files/xml/years/nbrfxrates${year}.xml` });
  sources.push({ kind: 'direct', url: `https://curs.bnr.ro/files/xml/years/nbrfxrates${year}.xml` });

  // 2) Public CORS proxies (best-effort; degrade gracefully)
  const targetUrls = [
    `https://www.bnr.ro/files/xml/years/nbrfxrates${year}.xml`,
    `https://curs.bnr.ro/files/xml/years/nbrfxrates${year}.xml`
  ];
  if (isCurrentYear) {
    targetUrls.unshift('https://www.bnr.ro/nbrfxrates.xml');
  }

  for (const t of targetUrls) {
    sources.push({ kind: 'proxy', name: 'corsproxy.io', url: 'https://corsproxy.io/?' + encodeURIComponent(t) });
    sources.push({ kind: 'proxy', name: 'allorigins',   url: 'https://api.allorigins.win/raw?url=' + encodeURIComponent(t) });
    sources.push({ kind: 'proxy', name: 'codetabs',     url: 'https://api.codetabs.com/v1/proxy?quest=' + encodeURIComponent(t) });
    sources.push({ kind: 'proxy', name: 'thingproxy',   url: 'https://thingproxy.freeboard.io/fetch/' + t });
  }
  return sources;
}

async function fetchWithTimeout(url, timeoutMs = 10000) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const resp = await fetch(url, { signal: ctrl.signal, redirect: 'follow' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const text = await resp.text();
    if (!text || text.length < 50) throw new Error('Răspuns gol');
    return text;
  } finally {
    clearTimeout(timer);
  }
}

async function fetchBnrXml(year, onProgress) {
  const sources = buildBnrSources(year);
  const errors = [];
  for (let i = 0; i < sources.length; i++) {
    const s = sources[i];
    const label = s.kind === 'direct'
      ? `direct (${new URL(s.url).hostname})`
      : `proxy (${s.name})`;
    if (onProgress) onProgress(`Încercare ${i + 1}/${sources.length}: ${label}...`);
    try {
      const text = await fetchWithTimeout(s.url, 12000);
      // Quick sanity check - must look like XML
      if (!/<\s*(\?xml|DataSet|Cube)/i.test(text.slice(0, 500))) {
        throw new Error('Răspuns nu e XML BNR');
      }
      console.log(`[BNR] ✓ Succes via ${label}`);
      return { text, source: label };
    } catch (e) {
      errors.push(`${label}: ${e.message}`);
      console.warn(`[BNR] ✗ ${label} eșuat: ${e.message}`);
    }
  }
  throw new Error(`Toate sursele BNR au eșuat:\n• ` + errors.join('\n• '));
}

function parseBnrXml(xmlText) {
  const parser = new DOMParser();
  const doc = parser.parseFromString(xmlText, 'application/xml');
  const err = doc.querySelector('parsererror');
  if (err) throw new Error('XML invalid: ' + err.textContent.slice(0, 200));
  const rates = [];
  // Use getElementsByTagName for namespace-agnostic lookup
  const cubes = doc.getElementsByTagName('Cube');
  for (let i = 0; i < cubes.length; i++) {
    const cube = cubes[i];
    const date = cube.getAttribute('date');
    if (!date) continue;
    const rateNodes = cube.getElementsByTagName('Rate');
    for (let j = 0; j < rateNodes.length; j++) {
      const rate = rateNodes[j];
      const currency = rate.getAttribute('currency');
      if (!CURRENCY_MAP[currency]) continue;
      const map = CURRENCY_MAP[currency];
      const multiplier = parseInt(rate.getAttribute('multiplier') || '1', 10);
      const value = parseFloat((rate.textContent || '').trim());
      if (isNaN(value)) continue;
      const [y, m, d] = date.split('-').map(Number);
      rates.push({
        key: `${date}|${map.internal}`,
        Data: date, An: y, Luna: m, Zi: d,
        Valuta: map.internal,
        OriginalCurrency: currency,
        Multiplier: multiplier,
        Demultiplicator: map.demultiplicator,
        Curs: value,
        Sursa: 'BNR'
      });
    }
  }
  return rates;
}

async function importBnrYear(year, onProgress) {
  const { text, source } = await fetchBnrXml(year, onProgress);
  if (onProgress) onProgress('Parsez XML-ul...');
  const rates = parseBnrXml(text);
  if (rates.length === 0) throw new Error('Niciun curs EUR/HUF parsat din XML');
  if (onProgress) onProgress(`Salvez ${rates.length} cursuri în baza de date...`);
  await dbBulkPut('cursValutar', rates);
  return { count: rates.length, source };
}

// Get rate for (date, currency). If exact date not present, walk back up to 14 days.
async function getRate(dateStr, currency) {
  if (currency === 'RON') return { Curs: 1, Demultiplicator: 1, Data: dateStr };
  let d = new Date(dateStr);
  for (let i = 0; i < 14; i++) {
    const key = `${d.toISOString().slice(0, 10)}|${currency}`;
    const r = await dbGet('cursValutar', key);
    if (r) return r;
    d.setDate(d.getDate() - 1);
  }
  return null;
}

// "data emiterii -1" - rate is published the day BEFORE the invoice
function getRateDateForInvoice(invoiceDate) {
  const d = new Date(invoiceDate);
  d.setDate(d.getDate() - 1);
  return d.toISOString().slice(0, 10);
}
</script>
<script>// ========================== Excel Import ==========================
// Reads eMag invoice exports. Two formats supported:
//   A) REAL eMag export ("Worksheet" sheet, 23 cols, no Canal/Moneda/RezidentaFiscala)
//      -> Canal, Moneda, RezidentaFiscala, AF, CIFDante are derived from the
//         marketplace by ID Seller (which is uniform per file).
//   B) Legacy template ("EMagRawData" sheet, 26 cols, Canal/Moneda/RezidentaFiscala
//      already filled in).

// Real eMag export columns (23) - exact order in file
const EMAG_REAL_HEADERS = [
  'ID Seller', 'ID Client', 'ID Supplier', 'ID FP',
  'Cumparator', 'CIF cumparator', 'Cont bancar seller',
  'Furnizor', 'CIF furnizor',
  'Document', 'Tip factura', 'Serie/numar factura',
  'Valoare factura fara TVA', 'Valoare TVA', 'Cota TVA', 'Valoare factura cu TVA',
  'Cod taxa SAP', 'Balanta factura',
  'Data emitere factura', 'Data scadenta factura', 'Serie si numar FP',
  'Data payout', 'MKTP Finance'
];

// Detect format - returns 'real' | 'legacy' | null
// REAL eMag export uses 23 columns in identical positional order; headers vary by language:
//   RO: "ID Seller", "ID Client", ..., "Tip factura" at col 11
//   EN (BG/HU): "Seller ID", "Client ID", ..., "Invoice type" at col 11
// LEGACY template: "Canal" at col 1, "Tip factura" at col 14
function detectFormat(wb) {
  const lower = (s) => String(s||'').toLowerCase().replace(/[\s_]+/g, '');
  for (const sn of wb.SheetNames) {
    const ws = wb.Sheets[sn]; if (!ws['!ref']) continue;
    const range = XLSX.utils.decode_range(ws['!ref']);
    if (range.e.r < 1) continue;
    const headers = [];
    for (let c = 0; c <= range.e.c; c++) {
      const cell = ws[XLSX.utils.encode_cell({ r: 0, c })];
      headers.push(cell ? String(cell.v || '') : '');
    }
    const h0 = lower(headers[0]);
    const h1 = lower(headers[1] || '');
    const h10 = lower(headers[10] || '');
    const h13 = lower(headers[13] || '');

    // REAL format: position-based, Tip factura/Invoice type must be at col 11 (idx 10)
    const isRealRo = (h0 === 'idseller' && h1 === 'idclient' && h10 === 'tipfactura');
    const isRealEn = (h0 === 'sellerid' && h1 === 'clientid' && h10 === 'invoicetype');
    if (isRealRo || isRealEn) {
      return {
        format: 'real',
        lang: isRealEn ? 'en' : 'ro',
        sheetName: sn,
        headers
      };
    }
    // LEGACY format: Canal at col 1, Tip factura at col 14
    if (h0 === 'canal' && h13 === 'tipfactura') {
      return { format: 'legacy', lang: 'ro', sheetName: sn, headers };
    }
  }
  return null;
}

// Robust date parser - handles: Date object, Excel serial, ISO, mm/dd/yyyy, dd.mm.yyyy
function parseAnyDate(v) {
  if (v === null || v === undefined || v === '') return null;
  if (v instanceof Date) {
    if (isNaN(v.getTime())) return null;
    return v.toISOString().slice(0, 10);
  }
  if (typeof v === 'number') {
    const d = new Date(Math.round((v - 25569) * 86400 * 1000));
    return isNaN(d.getTime()) ? null : d.toISOString().slice(0, 10);
  }
  const s = String(v).trim();
  if (!s) return null;
  // ISO yyyy-mm-dd
  let m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  // mm/dd/yyyy (US format from eMag) - HH:MM:SS optional
  m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (m) {
    const mm = m[1].padStart(2, '0');
    const dd = m[2].padStart(2, '0');
    return `${m[3]}-${mm}-${dd}`;
  }
  // dd.mm.yyyy or dd-mm-yyyy
  m = s.match(/^(\d{1,2})[.\-](\d{1,2})[.\-](\d{4})/);
  if (m) {
    const dd = m[1].padStart(2, '0');
    const mm = m[2].padStart(2, '0');
    return `${m[3]}-${mm}-${dd}`;
  }
  // last resort
  const dt = new Date(s);
  if (!isNaN(dt.getTime())) return dt.toISOString().slice(0, 10);
  return null;
}

function coerceNumber(v) {
  if (v === null || v === undefined || v === '') return null;
  if (typeof v === 'number') return v;
  const n = Number(String(v).replace(/\s/g, '').replace(',', '.'));
  return isNaN(n) ? null : n;
}

async function readExcelFile(file) {
  const buf = await file.arrayBuffer();
  const wb = XLSX.read(buf, { type: 'array', cellDates: true });
  const det = detectFormat(wb);
  if (!det) {
    return { error: 'Format Excel necunoscut. Aștept un export eMag (sheet "Worksheet" cu 23 coloane, headere RO sau EN) sau șablonul "EMagRawData".' };
  }
  const ws = wb.Sheets[det.sheetName];
  const aoa = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, dateNF: 'yyyy-mm-dd' });
  if (aoa.length < 2) return { error: 'Sheet-ul nu conține date.' };

  const marketplaces = await dbGetAll('marketplaces');
  const mpById = {};
  marketplaces.forEach(m => { mpById[Number(m.IdSeller)] = m; });

  // Build set of existing SerieNumars (for dedup across all imports)
  const existingRaw = await dbGetAll('rawData');
  const seenSerii = new Set();
  existingRaw.forEach(r => {
    if (r.SerieNumar) seenSerii.add(String(r.SerieNumar).trim());
  });

  // Generate batch ID for this import (used to delete-by-import later)
  const batchId = `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

  const data = [];
  const warnings = [];
  const channelStats = {};
  let skippedDuplicates = 0;

  if (det.format === 'real') {
    // REAL eMag format - 23 cols. IDSeller is the master, Canal/Moneda/Rezidenta are derived.
    const unknownSellers = new Set();
    for (let r = 1; r < aoa.length; r++) {
      const row = aoa[r];
      if (!row || row.every(c => c === undefined || c === '' || c === null)) continue;
      const idSeller = parseInt(row[0], 10);
      if (isNaN(idSeller)) continue;

      // Dedup by Serie/numar factura
      const serieNumar = row[11] ? String(row[11]).trim() : '';
      if (serieNumar && seenSerii.has(serieNumar)) {
        skippedDuplicates++;
        continue;
      }
      if (serieNumar) seenSerii.add(serieNumar);

      const mp = mpById[idSeller];
      if (!mp) unknownSellers.add(idSeller);
      const canalName = mp ? mp.Marketplace : `(ID ${idSeller} necunoscut)`;
      channelStats[canalName] = (channelStats[canalName] || 0) + 1;

      data.push({
        IDSeller: idSeller,
        IDClient: coerceNumber(row[1]),
        IDSupplier: coerceNumber(row[2]),
        IDFP: coerceNumber(row[3]),
        Seller: row[4] || null,
        CIFSeller: row[5] ? String(row[5]) : null,
        ContBancar: row[6] || null,
        EntitateDante: row[7] || null,
        CIFDante: row[8] ? String(row[8]) : null,
        Document: row[9] || null,
        TipFactura: row[10] || null,
        SerieNumar: serieNumar || null,
        ValoareFaraTVA: coerceNumber(row[12]),
        ValoareTVA: coerceNumber(row[13]),
        CotaTVA: coerceNumber(row[14]),
        ValoareCuTVA: coerceNumber(row[15]),
        CodTaxaSAP: row[16] || null,
        BalantaFactura: coerceNumber(row[17]),
        DataEmitere: parseAnyDate(row[18]),
        DataScadenta: parseAnyDate(row[19]),
        SerieNumarFP: row[20] || null,
        DataPayout: parseAnyDate(row[21]),
        MKTPFinance: row[22] || null,
        _SourceFile: file.name,
        _batchId: batchId
      });
    }
    unknownSellers.forEach(id =>
      warnings.push(`ID Seller ${id} nu există în Marketplaces - rândurile aferente nu vor avea Canal/Monedă și nu se vor procesa.`)
    );
  } else {
    // LEGACY 26-col format - has Canal in col 1, but we still need IDSeller (col 4) for lookup.
    for (let r = 1; r < aoa.length; r++) {
      const row = aoa[r];
      if (!row || row.every(c => c === undefined || c === '' || c === null)) continue;
      if (!row[0] || !row[13]) continue;

      // Dedup by Serie/numar factura
      const serieNumar = row[14] ? String(row[14]).trim() : '';
      if (serieNumar && seenSerii.has(serieNumar)) {
        skippedDuplicates++;
        continue;
      }
      if (serieNumar) seenSerii.add(serieNumar);

      let idSeller = coerceNumber(row[3]);
      // Try to match by Canal name if IDSeller missing
      if (!idSeller && row[0]) {
        const m = marketplaces.find(x => x.Marketplace === row[0]);
        if (m) idSeller = Number(m.IdSeller);
      }
      const mp = mpById[idSeller];
      const canalName = mp ? mp.Marketplace : (row[0] || `(ID ${idSeller})`);
      channelStats[canalName] = (channelStats[canalName] || 0) + 1;
      data.push({
        IDSeller: idSeller,
        IDClient: coerceNumber(row[4]),
        IDSupplier: coerceNumber(row[5]),
        IDFP: coerceNumber(row[6]),
        Seller: row[7],
        CIFSeller: row[8] ? String(row[8]) : null,
        ContBancar: row[9],
        EntitateDante: row[10],
        CIFDante: row[11] ? String(row[11]) : null,
        Document: row[12],
        TipFactura: row[13],
        SerieNumar: serieNumar || null,
        ValoareFaraTVA: coerceNumber(row[15]),
        ValoareTVA: coerceNumber(row[16]),
        CotaTVA: coerceNumber(row[17]),
        ValoareCuTVA: coerceNumber(row[18]),
        CodTaxaSAP: row[19],
        BalantaFactura: coerceNumber(row[20]),
        DataEmitere: parseAnyDate(row[21]),
        DataScadenta: parseAnyDate(row[22]),
        SerieNumarFP: row[23],
        DataPayout: parseAnyDate(row[24]),
        MKTPFinance: row[25],
        _SourceFile: file.name,
        _batchId: batchId
      });
    }
  }

  return {
    sheetName: det.sheetName,
    format: det.format,
    lang: det.lang,
    rows: data,
    warnings,
    channelStats,
    skippedDuplicates,
    batchId
  };
}
</script>
<script>// ========================== Data Processing Engine ==========================
// Mirrors the formulas from the DataSet sheet of the original Excel template

function excelDateToISO(v) {
  if (!v) return null;
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  if (typeof v === 'number') {
    // Excel serial date
    const d = new Date(Math.round((v - 25569) * 86400 * 1000));
    return d.toISOString().slice(0, 10);
  }
  if (typeof v === 'string') {
    // Try parse common formats
    const s = v.trim();
    // ISO already
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
    // dd/mm/yyyy or dd.mm.yyyy
    const m = s.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})/);
    if (m) {
      const dd = m[1].padStart(2, '0');
      const mm = m[2].padStart(2, '0');
      return `${m[3]}-${mm}-${dd}`;
    }
    // mm/dd/yyyy fallback - try anyway
    const dt = new Date(s);
    if (!isNaN(dt)) return dt.toISOString().slice(0, 10);
  }
  return null;
}

function round2(n) {
  if (n === null || n === undefined || isNaN(n)) return 0;
  return Math.round((Number(n) + Number.EPSILON) * 100) / 100;
}

// Lookup helpers
function buildLookups(nomenclator, marketplaces) {
  const nomBy = {};
  nomenclator.forEach(n => nomBy[n.Cod] = n);
  const mpById = {};
  marketplaces.forEach(m => mpById[Number(m.IdSeller)] = m);
  return { nomBy, mpById };
}

// New types keep accounting mappings blank until reviewed in Nomenclator.
function newInvoiceType(cod) {
  return { Cod: cod, Tranzactie: '', 'Formula contabila': '',
    'Cont factura': '', 'Cont articol': '', Articol: '', Problematica: '',
    TipTVA: '', TipOpsTVA: '', 'Cost Canal': '', Canal: '', Status: 'Inactiv',
    Detalii: 'Tip document nou detectat la import Excel. Completați maparea contabilă.',
    NecesitaActualizare: true };
}

async function ensureInvoiceTypes(rows, nomenclator = null) {
  const existing = nomenclator || await dbGetAll('nomenclator');
  const known = new Set(existing.map(n => n.Cod));
  const added = [];
  for (const row of rows) {
    const cod = row.TipFactura;
    if (!cod || known.has(cod)) continue;
    known.add(cod);
    added.push(newInvoiceType(cod));
  }
  if (added.length) await dbBulkPut('nomenclator', added);
  return existing.concat(added);
}

function invoiceTypesNotice(items) {
  const pending = items.filter(n => n.NecesitaActualizare);
  return pending.length ? `<div class="card" style="margin-top:.75rem">
    <p class="error">⚠️ Nomenclatorul are ${pending.length} tipuri de documente noi de actualizat:
    <b>${pending.map(n => esc(n.Cod)).join(', ')}</b>. Liniile sunt încărcate; completați mapările contabile și TVA, apoi re-procesați DataSet.</p>
    <button class="btn" onclick="navigate('nomenclator')">Actualizează Nomenclator →</button>
  </div>` : '';
}

// Determine TipTaxare from CotaTVA and TVA value (mirrors AF2)
function deriveTipTaxare(cotaTVA, ronTVA) {
  const cota = Number(cotaTVA) || 0;
  if (String(cota).includes('21')) return 'Taxare normala';
  if (cota === 0 && Math.floor(Math.abs(ronTVA || 0)) === 0) return 'Neimpozabila';
  return 'Taxare inversa';
}

// Process a single raw invoice row to produce a DataSet row
async function processRawRow(raw, nomBy, mpById) {
  const codTipFactura = raw.TipFactura;
  const nom = nomBy[codTipFactura] || newInvoiceType(codTipFactura);
  const mp = mpById[Number(raw.IDSeller)];
  if (!mp) {
    return {
      _error: `ID Seller "${raw.IDSeller}" nu exista in tabelul Marketplaces`,
      IDSeller: raw.IDSeller,
      CodTipFactura: codTipFactura,
      SerieNumar: raw.SerieNumar
    };
  }

  // Derived from Marketplace
  const canal = mp.Marketplace;
  const moneda = mp.Moneda;
  const rezidentaFiscala = mp.AF;

  // Date fields
  const dataEmitereISO = raw.DataEmitere;
  if (!dataEmitereISO) {
    return {
      _error: 'Lipseste data emiterii',
      IDSeller: raw.IDSeller,
      CodTipFactura: codTipFactura,
      SerieNumar: raw.SerieNumar
    };
  }
  const d = new Date(dataEmitereISO);
  const an = d.getUTCFullYear();
  const luna = d.getUTCMonth() + 1;
  const zi = d.getUTCDate();

  // Serie / Numar (Excel: LEFT 6 chars and rest after pos 7)
  const seriePref = (raw.SerieNumar || '').slice(0, 6);
  const numar = (raw.SerieNumar || '').slice(7);

  // Currency / Rate
  let curs = 1;
  let demultiplicator = 1;
  let cursDataUsed = null;
  if (moneda !== 'RON') {
    const rateDateStr = getRateDateForInvoice(dataEmitereISO);
    const r = await getRate(rateDateStr, moneda);
    if (!r) {
      return {
        _error: `Curs valutar lipsă pentru ${moneda} la data ${rateDateStr} (data emitere - 1)`,
        Canal: canal,
        IDSeller: raw.IDSeller,
        CodTipFactura: codTipFactura,
        SerieNumar: raw.SerieNumar,
        DataEmitere: dataEmitereISO,
        Moneda: moneda
      };
    }
    curs = r.Curs;
    demultiplicator = r.Demultiplicator || mp.Demultiplicator || 1;
    cursDataUsed = r.Data;
  }

  // Cota TVA (Excel col V): if RON, use raw cota;
  // else CotaTVA = TipTVA (multiplicator din Nomenclator, col AD) × cota marketplace.
  // TipTVA=0 → cota 0 (neimpozabil), TipTVA=1 → cota standard, TipTVA=2 → cota dublă.
  const tipTVA = Number(nom.TipTVA) || 0;
  const cotaTVA = (moneda === 'RON')
    ? (Number(raw.CotaTVA) || 0)
    : (nom.NecesitaActualizare ? (Number(raw.CotaTVA) || 0) : tipTVA * (Number(mp.CotaTVA) || 0));

  // Compute Ron values
  const valoareRaw = Number(raw.ValoareFaraTVA) || 0;
  const ronVal = round2(valoareRaw * curs / demultiplicator);
  const ronTVA = round2(ronVal * cotaTVA / 100);

  // TipTranzactie
  let tipTranzactie;
  const contTz = String(nom['Cont articol'] || '');
  if (contTz.includes('609')) tipTranzactie = '3_Discount';
  else if (ronVal < 0) tipTranzactie = '2_Stornare';
  else tipTranzactie = '1_Recunoastere';

  // TipTaxare
  const tipTaxare = deriveTipTaxare(cotaTVA, ronTVA);

  // TipDocumentTVA
  let tipDocumentTVA;
  if (tipTaxare === 'Neimpozabila') tipDocumentTVA = 'F_Decont';
  else if (tipTaxare === 'Taxare inversa') tipDocumentTVA = 'Factura UE TI';
  else if (Math.abs(ronVal + ronTVA) <= 490 && moneda === 'RON') tipDocumentTVA = 'F_Simplificata';
  else tipDocumentTVA = 'F_Normala';

  // MKP / FBE
  const mkpFbe = canal.includes('FBE') ? 'FBE' : 'MKP';

  // Referinta = Serie & Numar / day.month.year
  const referinta = `${seriePref}${numar} / ${zi}.${luna}.${an}`;

  // ValCh: if formula contabila is 4092 = 623, value is reversed
  const valCh = (nom['Formula contabila'] === '4092 = 623') ? -ronVal : ronVal;

  return {
    Canal: canal,
    IDSeller: raw.IDSeller,
    RezidentaFiscala: rezidentaFiscala,
    Flux: nom.Problematica || '',
    NecesitaActualizare: !!nom.NecesitaActualizare,
    TipOperatiune: raw.Document,
    CodTipFactura: codTipFactura,
    TipTranzactie: tipTranzactie,
    TipDocumentTVA: tipDocumentTVA,
    MkpFbe: mkpFbe,
    CostCanal: nom['Cost Canal'] || '',
    NaturaEconomica: nom.Tranzactie || '',
    ContTert: nom['Cont factura'] || '',
    ContTz: nom['Cont articol'] || '',
    FormulaContabila: nom['Formula contabila'] || '',
    Articol: nom.Articol || '',
    Serie: seriePref,
    Numar: numar,
    DataEmitere: dataEmitereISO,
    Referinta: referinta,
    An: an,
    Luna: luna,
    Zi: zi,
    Valoare: valoareRaw,
    CotaTVA: cotaTVA,
    ValoareTVA: Number(raw.ValoareTVA) || 0,
    Moneda: moneda,
    CursValutar: curs,
    CursDataUsed: cursDataUsed,
    Demultiplicator: demultiplicator,
    Ron_Val: ronVal,
    Ron_TVA: ronTVA,
    TipOpsTVA: nom.TipOpsTVA || '',
    TipTVA: nom.TipTVA,
    ValCh: valCh,
    TipTaxare: tipTaxare
  };
}

// Cheia perioadei "AAAA-LL" din data emiterii (UTC, identic cu processRawRow). Null daca data e invalida.
function periodKeyOf(dataEmitereISO) {
  if (!dataEmitereISO) return null;
  const d = new Date(dataEmitereISO);
  if (isNaN(d.getTime())) return null;
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
}

async function processAllRawData(progressCb, periods = null) {
  // periods: null => reprocesare completa (sterge tot dataSet-ul);
  //          Set de chei "AAAA-LL" => reprocesare selectiva (sterge si regenereaza doar lunile alese).
  const raw = await dbGetAll('rawData');
  const nomenclator = await ensureInvoiceTypes(raw);
  const marketplaces = await dbGetAll('marketplaces');
  const { nomBy, mpById } = buildLookups(nomenclator, marketplaces);

  let workRaw = raw;
  if (periods && periods.size > 0) {
    workRaw = raw.filter(r => periods.has(periodKeyOf(r.DataEmitere)));
    const periodObjs = [...periods].map(k => {
      const [an, luna] = k.split('-').map(Number);
      return { an, luna };
    });
    await dbClearPeriods('dataSet', periodObjs);
  } else {
    await dbClear('dataSet');
  }

  const results = [];
  const errors = [];
  for (let i = 0; i < workRaw.length; i++) {
    const r = await processRawRow(workRaw[i], nomBy, mpById);
    if (r._error) {
      errors.push(r);
    } else {
      results.push(r);
    }
    if (progressCb && i % 50 === 0) progressCb(i + 1, workRaw.length);
  }
  if (results.length > 0) await dbBulkPut('dataSet', results);
  if (progressCb) progressCb(workRaw.length, workRaw.length);
  return { processed: results.length, errors, invoiceTypes: nomenclator, total: workRaw.length, selective: !!(periods && periods.size) };
}
</script>
<script>// ========================== Modal / Dialog Component ==========================
function openModal({ title, fields, values = {}, onSave, onCancel, deleteBtn = false, onDelete, size = 'md' }) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay show';
  overlay.innerHTML = `
    <div class="modal modal-${size}">
      <div class="modal-head">
        <h3>${title}</h3>
        <button type="button" class="modal-close" aria-label="Închide">×</button>
      </div>
      <div class="modal-body">
        <div class="modal-form">
          ${fields.map((f, idx) => renderField(f, values[f.name], idx)).join('')}
        </div>
      </div>
      <div class="modal-foot">
        ${deleteBtn ? '<button type="button" class="btn btn-danger" data-act="delete">🗑️ Șterge</button>' : ''}
        <div class="spacer"></div>
        <button type="button" class="btn" data-act="cancel">Anulează</button>
        <button type="button" class="btn btn-primary" data-act="save">💾 Salvează</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  const form = overlay.querySelector('.modal-form');
  const close = () => { overlay.classList.remove('show'); setTimeout(() => overlay.remove(), 200); };

  overlay.querySelector('.modal-close').onclick = () => { close(); onCancel && onCancel(); };
  overlay.querySelector('[data-act="cancel"]').onclick = () => { close(); onCancel && onCancel(); };
  overlay.onclick = (e) => { if (e.target === overlay) { close(); onCancel && onCancel(); } };

  if (deleteBtn) {
    overlay.querySelector('[data-act="delete"]').onclick = async () => {
      if (!await confirmDialog('Confirmi ștergerea acestei înregistrări?')) return;
      try {
        await onDelete();
        close();
      } catch (err) {
        toast('Eroare: ' + err.message, 'error');
      }
    };
  }

  const doSave = async () => {
    const data = {};
    let valid = true;
    let firstInvalid = null;
    fields.forEach(f => {
      const el = form.querySelector(`[name="${f.name}"]`);
      if (!el) return;
      let v;
      if (f.type === 'checkbox') {
        v = el.checked;
      } else {
        v = el.value;
        if (f.type === 'number') v = v === '' ? null : Number(v);
      }
      // For required validation: checkboxes are never "empty" (true/false both valid)
      const isEmpty = (f.type === 'checkbox') ? false : (v === '' || v === null || v === undefined);
      if (f.required && isEmpty) {
        el.classList.add('field-error');
        valid = false;
        if (!firstInvalid) firstInvalid = el;
      } else {
        el.classList.remove('field-error');
      }
      data[f.name] = v;
    });
    if (!valid) {
      toast('Completează câmpurile obligatorii (marcate cu *)', 'error');
      if (firstInvalid) firstInvalid.focus();
      return;
    }
    try {
      await onSave(data);
      close();
    } catch (err) {
      toast('Eroare la salvare: ' + err.message, 'error');
      console.error('Save error:', err);
    }
  };

  overlay.querySelector('[data-act="save"]').onclick = doSave;

  // Submit on Ctrl/Cmd+Enter from any field
  form.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      doSave();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      close(); onCancel && onCancel();
    }
  });

  // Focus first input
  setTimeout(() => {
    const firstInput = form.querySelector('input:not([readonly]), select:not([disabled]), textarea');
    if (firstInput) firstInput.focus();
  }, 50);
}

function renderField(f, value, idx) {
  const v = value === null || value === undefined ? '' : value;
  const req = f.required ? '<span class="req">*</span>' : '';
  const readonly = f.readonly ? 'readonly' : '';
  const ph = f.placeholder ? `placeholder="${f.placeholder}"` : '';
  const hint = f.hint ? `<div class="field-hint">${f.hint}</div>` : '';
  const wrap = (inner) => `<div class="field"><label>${f.label}${req}</label>${inner}${hint}</div>`;

  if (f.type === 'select') {
    const opts = (f.options || []).map(o =>
      typeof o === 'object'
        ? `<option value="${o.value}" ${String(o.value) === String(v) ? 'selected' : ''}>${o.label}</option>`
        : `<option value="${o}" ${String(o) === String(v) ? 'selected' : ''}>${o}</option>`
    ).join('');
    return wrap(`<select name="${f.name}" ${readonly}>${f.required ? '' : '<option value=""></option>'}${opts}</select>`);
  }
  if (f.type === 'textarea') {
    return wrap(`<textarea name="${f.name}" ${ph} rows="3" ${readonly}>${v}</textarea>`);
  }
  if (f.type === 'number') {
    const step = f.step || '1';
    return wrap(`<input type="number" name="${f.name}" value="${v}" step="${step}" ${ph} ${readonly}/>`);
  }
  if (f.type === 'date') {
    return wrap(`<input type="date" name="${f.name}" value="${v}" ${readonly}/>`);
  }
  if (f.type === 'password') {
    const ac = f.autocomplete || 'new-password';
    return wrap(`<input type="password" name="${f.name}" value="${v}" ${ph} ${readonly} autocomplete="${ac}"/>`);
  }
  if (f.type === 'checkbox') {
    const checked = v ? 'checked' : '';
    const ro = f.readonly ? 'disabled' : '';
    return `<div class="field field-checkbox"><label class="checkbox-label"><input type="checkbox" name="${f.name}" ${checked} ${ro}/> <span>${f.label}</span></label>${hint}</div>`;
  }
  return wrap(`<input type="text" name="${f.name}" value="${v}" ${ph} ${readonly}/>`);
}

// Confirmation dialog
function confirmDialog(message, title = 'Confirmare') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay show';
    overlay.innerHTML = `
      <div class="modal modal-sm">
        <div class="modal-head"><h3>${title}</h3></div>
        <div class="modal-body"><p style="margin:0">${message}</p></div>
        <div class="modal-foot">
          <div class="spacer"></div>
          <button type="button" class="btn" data-a="no">Anulează</button>
          <button type="button" class="btn btn-danger" data-a="yes">Confirm</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);
    const close = (val) => { overlay.classList.remove('show'); setTimeout(() => overlay.remove(), 200); resolve(val); };
    overlay.querySelector('[data-a="yes"]').onclick = () => close(true);
    overlay.querySelector('[data-a="no"]').onclick = () => close(false);
    overlay.onclick = (e) => { if (e.target === overlay) close(false); };
    setTimeout(() => overlay.querySelector('[data-a="yes"]').focus(), 50);
  });
}
</script>
<script>// ========================== UI / Application Logic ==========================
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

function fmtNum(v, decimals = 2) {
  if (v === null || v === undefined || v === '') return '';
  const n = Number(v);
  if (isNaN(n)) return String(v);
  return n.toLocaleString('ro-RO', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function pad2(n) { return String(n).padStart(2, '0'); }

// Format a Date or yyyy-mm-dd string as "d.m.yyyy" (matches PDF references like "25.3.2026")
function fmtDateShort(v) {
  if (!v) return '';
  let d;
  if (v instanceof Date) d = v;
  else {
    const s = String(v).slice(0, 10);
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m) d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    else d = new Date(s);
  }
  if (isNaN(d.getTime())) return String(v);
  return `${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()}`;
}

function fmtDate(v) {
  if (!v) return '';
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  return String(v).slice(0, 10);
}

function toast(msg, type = 'info') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  $('#toasts').appendChild(t);
  setTimeout(() => t.classList.add('out'), 3500);
  setTimeout(() => t.remove(), 4000);
}

function setBusy(busy, message) {
  const overlay = $('#busy');
  $('#busy-msg').textContent = message || 'Se procesează...';
  overlay.classList.toggle('show', !!busy);
}

// ---- Routing ----
const routes = {
  dashboard: renderDashboard,
  nomenclator: renderNomenclator,
  marketplaces: renderMarketplaces,
  curs: renderCursValutar,
  upload: renderUpload,
  rawdata: renderRawData,
  dataset: renderDataSet,
  jurnal: renderJurnal,
  centralizator: renderCentralizator,
  decont: renderDecont,
  'raport-dinamic': renderRaportDinamic,
  settings: renderSettings,
  users: renderUsers
};

// Global context for Decont (set when clicking from Pivot 1, or from filters)
let _decontCtx = null;

// ============================================================
// Contextual help system — per-route HTML help shown in a slide-in
// panel on the right of the screen. The button in the topbar is
// hidden on routes without HELP_CONTENT entries. Opening the help
// expands the #app grid to add a third column and forces the
// sidebar into icon-only mode (`.help-open` class on #app).
// ============================================================
const HELP_CONTENT = {
  'upload': {
    title: '📤 Încărcare date',
    body: `
      <h3>Pași</h3>
      <ol>
        <li>Trage fișierul Excel peste zona de drop, sau click pe ea pentru a alege.</li>
        <li>Acceptă <code>.xlsx</code>, <code>.xlsm</code>, <code>.xls</code>.</li>
        <li>Verifică sumarul: <b>rânduri importate</b> / <b>duplicate</b> / <b>erori</b>.</li>
        <li>Mergi la <b>DataSet</b> și apasă <b>Procesează</b>.</li>
      </ol>

      <h3>Formate acceptate</h3>
      <ul>
        <li><b>Export eMag</b> (recomandat): sheet <code>Worksheet</code>, 23 coloane (RO/EN).</li>
        <li><b>Șablon legacy</b>: sheet <code>EMagRawData</code>, 26 coloane.</li>
      </ul>

      <div class="help-tip"><b>Canalul</b> (emag.ro / emag.bg / emag.hu) e detectat automat din coloana <code>ID Seller</code>.</div>

      <h3>Setări import</h3>
      <ul>
        <li><b>Adaugă la datele existente</b> bifată = append; debifată = înlocuiește tot.</li>
        <li>Deduplicare după <code>Serie/Numar</code> e mereu activă.</li>
      </ul>

      <div class="help-warn"><b>Drepturi:</b> importul e admin-only. Utilizatorii cu <code>🔒 Vizualizare</code> pot doar consulta.</div>

      <h3>Probleme frecvente</h3>
      <ul>
        <li><b>"Sheet-ul nu există":</b> verifică numele exact (case-sensitive).</li>
        <li><b>Coduri necunoscute:</b> adaugă codul în <b>Nomenclator</b>, apoi re-procesează DataSet.</li>
        <li><b>Datele nu apar în Dashboard:</b> verifică filtrele active (Reset).</li>
      </ul>
    `
  },

  'centralizator': {
    title: '📊 Centralizator',
    body: `
      <h3>Configurare</h3>
      <ol>
        <li>Selectează <b>An</b>, <b>Lună</b>, <b>Canal</b> din filtre.</li>
        <li>Listele se actualizează — apar doar valorile cu date.</li>
        <li>Verifică totalul în pivotul 1 (jos).</li>
      </ol>

      <h3>Export PDF</h3>
      <ol>
        <li>Click pe <b>🖨️</b> în colțul stânga-sus.</li>
        <li>În dialog: <b>Destinație</b> = <code>Salvează ca PDF</code>.</li>
        <li><b>Aspect:</b> Vertical (sau Orizontal pentru tabele largi).</li>
        <li><b>Pagini:</b> Toate.</li>
        <li>Click <b>Salvează</b> → alege folder + nume fișier.</li>
      </ol>

      <div class="help-tip">Sugestie nume: <code>Centralizator_emag-ro_2026-03.pdf</code></div>

      <h3>Drilldown la Decont</h3>
      <p>Click pe pictograma <code>D</code> din coloana din dreapta a unei linii de pivot 1 → deschide automat <b>Decontul</b> detaliat al acelei linii (cu toate filtrele preluate).</p>

      <h3>Probleme</h3>
      <ul>
        <li><b>Pivoți goi:</b> nu există date în DataSet pentru filtre. Schimbă perioada sau procesează DataSet.</li>
        <li><b>PDF cu URL în antet:</b> dezactivează „Antete și subsoluri" în <i>Setări mai multe</i>.</li>
        <li><b>Culori lipsă (Firefox):</b> bifează „Imprimă fundal" în <i>Setări mai multe</i>.</li>
      </ul>
    `
  },

  'decont': {
    title: '📈 Decont',
    body: `
      <h3>Cum ajungi aici</h3>
      <ul>
        <li><b>Recomandat:</b> click pe <code>D</code> din pivotul 1 al Centralizatorului.</li>
        <li><b>Direct din meniu:</b> cu cele mai recente filtre disponibile.</li>
      </ul>

      <h3>Filtre</h3>
      <ul>
        <li><b>An / Lună / Canal</b> — perioada și canalul.</li>
        <li><b>Flux</b> — Plătit, Anulat, etc.</li>
        <li><b>Tip Document TVA</b> — Factură, Storno, Avans.</li>
      </ul>

      <h3>Export PDF</h3>
      <ol>
        <li>Click pe <b>🖨️</b> din stânga-sus.</li>
        <li>În dialog: <b>Destinație</b> = <code>Salvează ca PDF</code>.</li>
        <li>Aceleași setări ca la Centralizator (Vertical, Color, Toate).</li>
        <li>Salvează cu nume sugestiv.</li>
      </ol>

      <div class="help-tip">Numărul <b>Anexei</b> din antet (dreapta-sus) corespunde cu coloana <b>Anexă</b> din Centralizator. Folosește-l ca referință în registrul de anexe.</div>

      <h3>Probleme</h3>
      <ul>
        <li><b>Tabel gol:</b> filtrele sunt prea restrictive sau nu există documente individuale pentru combinația aleasă.</li>
        <li><b>Refresh:</b> click pe <code>🔄</code> dacă datele par neactualizate.</li>
      </ul>
    `
  },

  'raport-dinamic': {
    title: '🔍 Raport dinamic',
    body: `
      <h3>Configurare coloane</h3>
      <ul>
        <li><b>✓ Vizibil</b> — apare în raport.</li>
        <li><b>🔍 Filtru</b> — text liber, AND între filtre, case-insensitive.</li>
        <li><b>Σ Valoare</b> — sumare în subtotaluri/total (doar numerice).</li>
        <li><b>🗂️ Grup</b> — grupare ierarhică, în ordinea bifării.</li>
        <li><b>∑ Subtotal</b> — total pe fiecare grup.</li>
        <li><b>↑↓ Sort</b> — click ciclu asc / desc / fără.</li>
        <li><b>⋮⋮ Drag handle</b> — apasă și trage rândul pentru reordonare.</li>
      </ul>

      <h3>Configurații salvate</h3>
      <ol>
        <li>Tastează un nume sugestiv (<code>"Vânzări lunare"</code>).</li>
        <li>Click <b>💾 Salvează</b>.</li>
        <li>Vizibilă pentru toți utilizatorii aplicației.</li>
      </ol>

      <div class="help-warn"><b>Permisiuni:</b> salvare / redenumire / ștergere — doar <b>admin</b>. Vizualizare și aplicare — toți.</div>

      <h3>Aplică & Export</h3>
      <ol>
        <li><b>🔄 Aplică</b> — generează tabelul cu setările curente.</li>
        <li><b>📥 Export Excel</b> — descarcă <code>.xlsx</code> cu coloane, grupări, subtotaluri.</li>
        <li><b>↺ Compactează</b> — ascunde duplicatele consecutive (mod pivot).</li>
      </ol>

      <div class="help-tip">Folosește <b>Export Excel</b> pentru rapoarte ad-hoc. Pentru rapoarte fixe oficiale (cu pivoți și zone de semnătură) — folosește <b>Centralizator</b> și <b>Decont</b>.</div>
    `
  }
};

function updateHelpForRoute(routeName) {
  const help = HELP_CONTENT[routeName];
  document.body.classList.toggle('has-help', !!help);
  const titleEl = document.getElementById('helpTitle');
  const bodyEl  = document.getElementById('helpBody');
  if (help) {
    if (titleEl) titleEl.textContent = help.title;
    if (bodyEl)  bodyEl.innerHTML = help.body;
  } else {
    // Auto-close help if user navigates to a page without help.
    closeHelp();
  }
}

function openHelp() {
  document.getElementById('app')?.classList.add('help-open');
  document.body.classList.add('help-open');
}
function closeHelp() {
  document.getElementById('app')?.classList.remove('help-open');
  document.body.classList.remove('help-open');
}
function toggleHelp() {
  const app = document.getElementById('app');
  if (app && app.classList.contains('help-open')) closeHelp();
  else openHelp();
}

// Wire help button + close + Escape handler. Idempotent — safe to call
// multiple times (uses _wired flags).
function wireHelpSystem() {
  const btn = document.getElementById('helpBtn');
  const closeBtn = document.getElementById('helpClose');
  if (btn && !btn._wired) { btn._wired = true; btn.addEventListener('click', toggleHelp); }
  if (closeBtn && !closeBtn._wired) { closeBtn._wired = true; closeBtn.addEventListener('click', closeHelp); }
  if (!document._helpEscWired) {
    document._helpEscWired = true;
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && document.body.classList.contains('help-open')) closeHelp();
    });
  }
}

function navigate(name) {
  // Admin-only routes — silently redirect non-admin users back to dashboard.
  // Defense in depth: the nav item is hidden via CSS, but the URL hash could
  // still be typed manually.
  const ADMIN_ROUTES = new Set(['users']);
  if (ADMIN_ROUTES.has(name) && !isAdmin()) {
    name = 'dashboard';
  }
  $$('.nav-item').forEach(el => el.classList.toggle('active', el.dataset.route === name));
  // Refresh help button visibility + content for the new route.
  updateHelpForRoute(name);
  const handler = routes[name] || routes.dashboard;
  $('#content').innerHTML = '<div class="loading">Se încarcă...</div>';
  Promise.resolve().then(() => handler($('#content'))).catch(err => {
    $('#content').innerHTML = `<div class="error">Eroare: ${err.message}</div>`;
    console.error(err);
  });
  // history.replaceState may fail in sandboxed iframes (about:srcdoc) - ignore
  try { history.replaceState(null, '', '#' + name); } catch (e) {}
}

// ---- Initial Setup ----
async function ensureMasterData() {
  // Master data writes are admin-only on the server. Skip silently for
  // non-admin users — they'll just see whatever the admin has populated.
  if (!isAdmin()) return;
  const nCount = await dbCount('nomenclator');
  if (nCount === 0) {
    await dbBulkPut('nomenclator', NOMENCLATOR_DATA);
  }
  const mCount = await dbCount('marketplaces');
  if (mCount === 0) {
    await dbBulkPut('marketplaces', MARKETPLACES_DATA);
  }
}

// ---- Dashboard ----
async function renderDashboard(root) {
  const [nomCount, mpCount, rateCount, rawCount, dsCount] = await Promise.all([
    dbCount('nomenclator'), dbCount('marketplaces'),
    dbCount('cursValutar'), dbCount('rawData'), dbCount('dataSet')
  ]);

  // Pull the full processed dataSet once. Filters and chart updates run
  // entirely client-side over this in-memory copy.
  const ds = await dbGetAll('dataSet');

  root.innerHTML = `
    <div class="page-head">
      <h1>Dashboard</h1>
      <p class="subtitle">Aplicație pentru procesarea deconturilor eMag Marketplace</p>
    </div>
    <div class="cards">
      <div class="card stat">
        <div class="stat-label">Nomenclator</div>
        <div class="stat-value">${nomCount}</div>
        <div class="stat-foot">coduri tip factură</div>
      </div>
      <div class="card stat">
        <div class="stat-label">Marketplaces</div>
        <div class="stat-value">${mpCount}</div>
        <div class="stat-foot">canale eMag</div>
      </div>
      <div class="card stat">
        <div class="stat-label">Curs Valutar</div>
        <div class="stat-value">${rateCount}</div>
        <div class="stat-foot">rate BNR stocate</div>
      </div>
      <div class="card stat">
        <div class="stat-label">Date eMag</div>
        <div class="stat-value">${rawCount}</div>
        <div class="stat-foot">facturi încărcate</div>
      </div>
      <div class="card stat">
        <div class="stat-label">DataSet</div>
        <div class="stat-value">${dsCount}</div>
        <div class="stat-foot">înregistrări procesate</div>
      </div>
    </div>

    <div class="dash-filterbar" role="group" aria-label="Filtre dashboard">
      <div class="dash-filter-group">
        <label for="filterAn">An</label>
        <select id="filterAn" class="dash-filter"><option value="">Toate</option></select>
      </div>
      <div class="dash-filter-group">
        <label for="filterLuna">Luna</label>
        <select id="filterLuna" class="dash-filter"><option value="">Toate</option></select>
      </div>
      <div class="dash-filter-group">
        <label for="filterCanal">Canal</label>
        <select id="filterCanal" class="dash-filter"><option value="">Toate</option></select>
      </div>
      <div class="dash-filter-group">
        <label for="filterArticol">Articol</label>
        <select id="filterArticol" class="dash-filter"><option value="">Toate</option></select>
      </div>
      <div class="dash-filter-actions">
        <button type="button" class="dash-filter-reset" id="dashFilterReset">Reset filtre</button>
      </div>
    </div>

    <div class="dash-charts">
      <div class="dash-chart-card">
        <div class="dash-chart-head">
          <div class="dash-chart-title">Top valoare RON pe articole</div>
          <div class="dash-chart-meta" id="chartArticleMeta">Top 10</div>
        </div>
        <div class="dash-chart-body">
          <canvas id="chartArticle"></canvas>
          <div class="dash-chart-empty" id="chartArticleEmpty" style="display:none">
            Nu există date pentru filtrele alese.
          </div>
        </div>
      </div>
      <div class="dash-chart-card">
        <div class="dash-chart-head">
          <div class="dash-chart-title">Top canale × Cost canal (valoare RON)</div>
          <div class="dash-chart-meta" id="chartCanalCostMeta">Stivuit pe Cost Canal</div>
        </div>
        <div class="dash-chart-body">
          <canvas id="chartCanalCost"></canvas>
          <div class="dash-chart-empty" id="chartCanalCostEmpty" style="display:none">
            Nu există date pentru filtrele alese.
          </div>
        </div>
      </div>
      <div class="dash-chart-card">
        <div class="dash-chart-head">
          <div class="dash-chart-title">Facturi pe lună</div>
          <div class="dash-chart-meta" id="chartFacturiLunaMeta">—</div>
        </div>
        <div class="dash-chart-body">
          <canvas id="chartFacturiLuna"></canvas>
          <div class="dash-chart-empty" id="chartFacturiLunaEmpty" style="display:none">
            Nu există date pentru filtrele alese.
          </div>
        </div>
      </div>
      <div class="dash-chart-card">
        <div class="dash-chart-head">
          <div class="dash-chart-title">Facturi pe lună × Canal</div>
          <div class="dash-chart-meta" id="chartFacturiLunaCanalMeta">Stivuit pe Canal</div>
        </div>
        <div class="dash-chart-body">
          <canvas id="chartFacturiLunaCanal"></canvas>
          <div class="dash-chart-empty" id="chartFacturiLunaCanalEmpty" style="display:none">
            Nu există date pentru filtrele alese.
          </div>
        </div>
      </div>
      <div class="dash-chart-card dash-chart-card-wide">
        <div class="dash-chart-head">
          <div class="dash-chart-title">Facturi pe Cod Document × Canal</div>
          <div class="dash-chart-meta" id="chartFacturiCodCanalMeta">Stivuit pe Canal</div>
        </div>
        <div class="dash-chart-body">
          <canvas id="chartFacturiCodCanal"></canvas>
          <div class="dash-chart-empty" id="chartFacturiCodCanalEmpty" style="display:none">
            Nu există date pentru filtrele alese.
          </div>
        </div>
      </div>
      <div class="dash-chart-card dash-chart-card-wide">
        <div class="dash-chart-head">
          <div class="dash-chart-title">Articole pe ani (valoare RON)</div>
          <div class="dash-chart-meta" id="chartArticleYearMeta">Top 10 articole · stivuit</div>
        </div>
        <div class="dash-chart-body">
          <canvas id="chartArticleYear"></canvas>
          <div class="dash-chart-empty" id="chartArticleYearEmpty" style="display:none">
            Nu există date pentru filtrele alese.
          </div>
        </div>
      </div>
    </div>

    <div class="cards" style="margin-top:1.5rem">
      <div class="card">
        <h3>📋 Pași de lucru</h3>
        <ol class="workflow">
          <li><b>Curs Valutar</b> – Importă cursurile BNR pentru anul curent.</li>
          <li><b>Date eMag</b> – Încarcă fișierul Excel exportat din eMag (Financiar → Facturi).</li>
          <li><b>DataSet</b> – Procesează datele și generează înregistrările contabile.</li>
          <li><b>Centralizator</b> – Vizualizează raportul sintetic pe canal/lună.</li>
        </ol>
      </div>
    </div>
  `;

  // Populate filter options from the unique values in dataSet
  populateDashFilters(ds);

  // Wire filter change handlers
  $$('.dash-filter').forEach(sel => sel.addEventListener('change', () => updateDashCharts(ds)));
  $('#dashFilterReset').addEventListener('click', () => {
    $$('.dash-filter').forEach(sel => sel.value = '');
    updateDashCharts(ds);
  });

  // Initial render
  updateDashCharts(ds);
}

// ---- Dashboard filters & charts ----
const MONTH_NAMES_RO = ['', 'Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'];

function populateDashFilters(ds) {
  const anSet      = new Set();
  const lunaSet    = new Set();
  const canalSet   = new Set();
  const articolSet = new Set();
  for (const r of ds) {
    if (r.An      != null && r.An      !== '') anSet.add(String(r.An));
    if (r.Luna    != null && r.Luna    !== '') lunaSet.add(String(r.Luna));
    if (r.Canal)        canalSet.add(r.Canal);
    if (r.Articol)      articolSet.add(r.Articol);
  }
  fillSelect('#filterAn',
    [...anSet].sort((a, b) => Number(b) - Number(a)),
    v => v
  );
  fillSelect('#filterLuna',
    [...lunaSet].sort((a, b) => Number(a) - Number(b)),
    v => `${String(v).padStart(2, '0')} — ${MONTH_NAMES_RO[Number(v)] || v}`
  );
  fillSelect('#filterCanal',   [...canalSet].sort(),   v => v);
  fillSelect('#filterArticol', [...articolSet].sort(), v => v);
}

function fillSelect(selector, values, labelFn) {
  const sel = $(selector);
  if (!sel) return;
  // Keep the existing "Toate" option (first child); append the rest.
  while (sel.options.length > 1) sel.remove(1);
  for (const v of values) {
    const opt = document.createElement('option');
    opt.value = String(v);
    opt.textContent = labelFn(v);
    sel.appendChild(opt);
  }
}

function getDashFilters() {
  return {
    an:      $('#filterAn')?.value      || '',
    luna:    $('#filterLuna')?.value    || '',
    canal:   $('#filterCanal')?.value   || '',
    articol: $('#filterArticol')?.value || ''
  };
}

function applyDashFilters(ds, f) {
  return ds.filter(r => {
    if (f.an      && String(r.An)   !== f.an)      return false;
    if (f.luna    && String(r.Luna) !== f.luna)    return false;
    if (f.canal   && r.Canal        !== f.canal)   return false;
    if (f.articol && r.Articol      !== f.articol) return false;
    return true;
  });
}

let _chartArticle = null;
let _chartCanalCost = null;
let _chartFacturiLuna = null;
let _chartFacturiLunaCanal = null;
let _chartFacturiCodCanal = null;
let _chartArticleYear = null;

function applyChartTheme() {
  if (typeof Chart === 'undefined') return;
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  Chart.defaults.color       = isDark ? '#cbd5e1' : '#475569';
  Chart.defaults.borderColor = isDark ? 'rgba(148,163,184,0.18)' : 'rgba(15,23,42,0.08)';
  Chart.defaults.font.family = "'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
}

const CHART_PALETTE = [
  '#1F4788', '#2D8B8E', '#F59E0B', '#0891b2', '#7c3aed',
  '#ec4899', '#14b8a6', '#f97316', '#84cc16', '#06b6d4',
  '#a855f7', '#dc2626', '#22c55e', '#eab308', '#3b82f6'
];

function fmtRON(n) {
  const v = Number(n) || 0;
  return v.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Format a percentage with Romanian decimal separator. Returns '—' for non-finite.
// Optional `signed` prefixes a '+' for positive values (useful for deltas).
function fmtPct(v, signed = false) {
  if (!isFinite(v)) return '—';
  const s = v.toFixed(1).replace('.', ',') + '%';
  return signed && v > 0 ? '+' + s : s;
}

function showChartEmpty(canvasId, emptyId, isEmpty) {
  const cv = document.getElementById(canvasId);
  const em = document.getElementById(emptyId);
  if (!cv || !em) return;
  cv.style.display = isEmpty ? 'none' : '';
  em.style.display = isEmpty ? '' : 'none';
}

function updateDashCharts(ds) {
  const filtered = applyDashFilters(ds, getDashFilters());
  if (typeof Chart !== 'undefined') applyChartTheme();
  renderArticleChart(filtered);
  renderCanalCostChart(filtered);
  renderFacturiLunaChart(filtered);
  renderFacturiLunaCanalChart(filtered);
  renderFacturiCodCanalChart(filtered);
  renderArticleYearChart(filtered);
}

function renderArticleChart(rows) {
  const TOP_N = 10;
  const byArticol = {};
  const countByArticol = {};
  for (const r of rows) {
    const k = r.Articol || '(necunoscut)';
    byArticol[k] = (byArticol[k] || 0) + (Number(r.Ron_Val) || 0);
    countByArticol[k] = (countByArticol[k] || 0) + 1;
  }
  const ranked = Object.entries(byArticol)
    .sort((a, b) => Math.abs(b[1]) - Math.abs(a[1]))
    .slice(0, TOP_N);

  // Grand total over ALL articles (not just top N), absolute values — used
  // for the "X% din total" line in the tooltip. Using absolute values means
  // negative entries (returns/storno) don't artificially inflate the share.
  const grandAbsTotal = Object.values(byArticol).reduce((s, v) => s + Math.abs(v), 0);

  const metaEl = document.getElementById('chartArticleMeta');
  if (metaEl) metaEl.textContent = `Top ${ranked.length} din ${Object.keys(byArticol).length}`;

  if (_chartArticle) { _chartArticle.destroy(); _chartArticle = null; }
  if (ranked.length === 0 || typeof Chart === 'undefined') {
    showChartEmpty('chartArticle', 'chartArticleEmpty', true);
    return;
  }
  showChartEmpty('chartArticle', 'chartArticleEmpty', false);

  const ctx = document.getElementById('chartArticle').getContext('2d');
  _chartArticle = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ranked.map(r => r[0]),
      datasets: [{
        label: 'Valoare RON',
        data: ranked.map(r => r[1]),
        backgroundColor: ranked.map(r => r[1] >= 0 ? '#1F4788' : '#dc2626'),
        borderWidth: 0,
        borderRadius: 4,
        maxBarThickness: 48
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => `${fmtRON(ctx.parsed.y)} RON`,
            afterLabel: (ctx) => {
              const articol = ctx.label;
              const count = countByArticol[articol] || 0;
              const lines = [`${count} ${count === 1 ? 'rând' : 'rânduri'} contributive`];
              if (grandAbsTotal > 0) {
                const pct = (Math.abs(ctx.parsed.y) / grandAbsTotal) * 100;
                lines.push(`${fmtPct(pct)} din valoarea totală (abs.)`);
              }
              return lines;
            }
          }
        }
      },
      scales: {
        x: {
          ticks: { autoSkip: false, maxRotation: 40, minRotation: 30, font: { size: 10 } }
        },
        y: {
          beginAtZero: true,
          ticks: { callback: v => fmtRON(v) }
        }
      }
    }
  });
}

function renderCanalCostChart(rows) {
  const TOP_N = 10;
  // canal → costCanal → sumRon
  const byCanal = {};
  for (const r of rows) {
    const c = r.Canal || '(necunoscut)';
    const cc = r.CostCanal || '(necunoscut)';
    if (!byCanal[c]) byCanal[c] = {};
    byCanal[c][cc] = (byCanal[c][cc] || 0) + (Number(r.Ron_Val) || 0);
  }
  const canalAgg = Object.entries(byCanal).map(([c, costs]) => {
    const total = Object.values(costs).reduce((s, v) => s + Math.abs(v), 0);
    return { canal: c, total, costs };
  }).sort((a, b) => b.total - a.total).slice(0, TOP_N);

  const metaEl = document.getElementById('chartCanalCostMeta');
  if (metaEl) metaEl.textContent = `Top ${canalAgg.length} canale, stivuit pe Cost Canal`;

  if (_chartCanalCost) { _chartCanalCost.destroy(); _chartCanalCost = null; }
  if (canalAgg.length === 0 || typeof Chart === 'undefined') {
    showChartEmpty('chartCanalCost', 'chartCanalCostEmpty', true);
    return;
  }
  showChartEmpty('chartCanalCost', 'chartCanalCostEmpty', false);

  const labels = canalAgg.map(c => c.canal);
  // All cost categories present, ordered by total magnitude across selected canale
  const ccTotals = {};
  for (const c of canalAgg) {
    for (const [cc, v] of Object.entries(c.costs)) {
      ccTotals[cc] = (ccTotals[cc] || 0) + Math.abs(v);
    }
  }
  const ccOrdered = Object.entries(ccTotals).sort((a, b) => b[1] - a[1]).map(([k]) => k);

  const datasets = ccOrdered.map((cc, i) => ({
    label: cc,
    data: canalAgg.map(c => c.costs[cc] || 0),
    backgroundColor: CHART_PALETTE[i % CHART_PALETTE.length],
    borderWidth: 0
  }));

  const ctx = document.getElementById('chartCanalCost').getContext('2d');
  // Grand total across all displayed canale (absolute), for share-of-total info.
  const grandAbsTotal = canalAgg.reduce((s, c) => s + c.total, 0);
  _chartCanalCost = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      // Show all stacked segments at once when hovering — required for the
      // sum/footer line to be meaningful.
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 11, boxHeight: 11, font: { size: 10 }, padding: 8 } },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.dataset.label}: ${fmtRON(ctx.parsed.y)} RON`,
            footer: (items) => {
              const sum = items.reduce((s, it) => s + it.parsed.y, 0);
              const lines = [`Total canal: ${fmtRON(sum)} RON`];
              if (grandAbsTotal > 0) {
                const pct = (Math.abs(sum) / grandAbsTotal) * 100;
                lines.push(`${fmtPct(pct)} din valoarea totală afișată`);
              }
              return lines;
            }
          }
        }
      },
      scales: {
        x: { stacked: true, ticks: { font: { size: 11 } } },
        y: {
          stacked: true, beginAtZero: true,
          ticks: { callback: v => fmtRON(v) }
        }
      }
    }
  });
}

// Counts DISTINCT invoices per month (Serie + Numar uniquely identifies a
// factura — multiple dataSet rows from the same invoice contribute once).
function renderFacturiLunaChart(rows) {
  const byLuna = {};
  for (let m = 1; m <= 12; m++) byLuna[m] = new Set();
  for (const r of rows) {
    const luna = Number(r.Luna);
    if (!luna || luna < 1 || luna > 12) continue;
    const key = `${r.Serie || ''}|${r.Numar || ''}`;
    if (key === '|') continue; // skip rows with no invoice identifier
    byLuna[luna].add(key);
  }
  const labels = MONTH_NAMES_RO.slice(1); // 'Ian' ... 'Dec'
  const data = Array.from({ length: 12 }, (_, i) => byLuna[i + 1].size);
  const total = data.reduce((s, v) => s + v, 0);

  const metaEl = document.getElementById('chartFacturiLunaMeta');
  if (metaEl) metaEl.textContent = total > 0 ? `Total: ${total} facturi` : '—';

  if (_chartFacturiLuna) { _chartFacturiLuna.destroy(); _chartFacturiLuna = null; }
  if (total === 0 || typeof Chart === 'undefined') {
    showChartEmpty('chartFacturiLuna', 'chartFacturiLunaEmpty', true);
    return;
  }
  showChartEmpty('chartFacturiLuna', 'chartFacturiLunaEmpty', false);

  const ctx = document.getElementById('chartFacturiLuna').getContext('2d');
  // Average across months that have at least one invoice — gives a more
  // meaningful baseline than dividing by 12 when business started mid-year.
  const monthsWithData = data.filter(v => v > 0).length;
  const avgPerMonth = monthsWithData > 0 ? total / monthsWithData : 0;
  _chartFacturiLuna = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Număr facturi',
        data,
        backgroundColor: '#2D8B8E',
        borderWidth: 0,
        borderRadius: 4,
        maxBarThickness: 40
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.parsed.y} facturi`,
            afterLabel: (ctx) => {
              const v = ctx.parsed.y;
              const lines = [];
              if (total > 0) {
                lines.push(`${fmtPct((v / total) * 100)} din total an`);
              }
              if (avgPerMonth > 0 && v > 0) {
                const delta = ((v - avgPerMonth) / avgPerMonth) * 100;
                lines.push(`${fmtPct(delta, true)} față de media lunară (${avgPerMonth.toFixed(1).replace('.', ',')})`);
              }
              return lines;
            }
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}

// Distinct invoices per month, stacked by Canal (top N canale by total).
function renderFacturiLunaCanalChart(rows) {
  const TOP_N = 10;
  // canal → luna → Set of "Serie|Numar"
  const byCanal = {};
  for (const r of rows) {
    const luna = Number(r.Luna);
    if (!luna || luna < 1 || luna > 12) continue;
    const key = `${r.Serie || ''}|${r.Numar || ''}`;
    if (key === '|') continue;
    const canal = r.Canal || '(necunoscut)';
    if (!byCanal[canal]) byCanal[canal] = {};
    if (!byCanal[canal][luna]) byCanal[canal][luna] = new Set();
    byCanal[canal][luna].add(key);
  }

  // Compute total per canal for ranking, then take top N
  const canalAgg = Object.entries(byCanal).map(([c, monthMap]) => {
    let total = 0;
    for (const set of Object.values(monthMap)) total += set.size;
    return { canal: c, total, monthMap };
  }).sort((a, b) => b.total - a.total).slice(0, TOP_N);

  const metaEl = document.getElementById('chartFacturiLunaCanalMeta');
  if (metaEl) metaEl.textContent = canalAgg.length
    ? `${canalAgg.length} canale × 12 luni`
    : 'Stivuit pe Canal';

  if (_chartFacturiLunaCanal) { _chartFacturiLunaCanal.destroy(); _chartFacturiLunaCanal = null; }
  if (canalAgg.length === 0 || typeof Chart === 'undefined') {
    showChartEmpty('chartFacturiLunaCanal', 'chartFacturiLunaCanalEmpty', true);
    return;
  }
  showChartEmpty('chartFacturiLunaCanal', 'chartFacturiLunaCanalEmpty', false);

  const labels = MONTH_NAMES_RO.slice(1);
  const datasets = canalAgg.map((c, i) => ({
    label: c.canal,
    data: Array.from({ length: 12 }, (_, m) => c.monthMap[m + 1] ? c.monthMap[m + 1].size : 0),
    backgroundColor: CHART_PALETTE[i % CHART_PALETTE.length],
    borderWidth: 0
  }));

  const ctx = document.getElementById('chartFacturiLunaCanal').getContext('2d');
  // Grand total = sum of distinct invoice counts across all displayed canale + months.
  const grandTotal = canalAgg.reduce((s, c) => s + c.total, 0);
  _chartFacturiLunaCanal = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      // Show all stacked segments at once when hovering — needed for footer
      // sum + percentage to be meaningful.
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 11, boxHeight: 11, font: { size: 10 }, padding: 8 } },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} facturi`,
            footer: (items) => {
              const sum = items.reduce((s, it) => s + it.parsed.y, 0);
              const lines = [`Total lună: ${sum} facturi`];
              if (grandTotal > 0) {
                lines.push(`${fmtPct((sum / grandTotal) * 100)} din total an`);
              }
              return lines;
            }
          }
        }
      },
      scales: {
        x: { stacked: true, ticks: { font: { size: 11 } } },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}

// Distinct invoices per Cod Document (CodTipFactura), stacked by Canal.
// X axis = Cod Document values (top N by total invoice count, descending).
// Y axis = number of distinct invoices (Serie+Numar pair).
// Each bar is split into Canal segments, with one stack color per Canal.
//
// A single invoice can appear on multiple dataSet rows (one per article line),
// all sharing the same CodTipFactura — the Set on Serie|Numar dedupes them so
// each invoice is counted once per (CodTipFactura, Canal) cell.
function renderFacturiCodCanalChart(rows) {
  const TOP_N = 15;
  // codTip → canal → Set of "Serie|Numar"
  const byCod = {};
  // codTip → { tranzactie, articol } — the human-readable description from
  // the nomenclator (resolved into each dataSet row at processing time).
  // Since all dataSet rows with the same Cod share the same nomenclator entry,
  // we just capture the first non-empty value we see.
  const byCodMeta = {};
  for (const r of rows) {
    const cod = (r.CodTipFactura || '').toString().trim();
    if (!cod) continue;
    const serie = (r.Serie || '').toString().trim();
    const numar = (r.Numar || '').toString().trim();
    if (!serie && !numar) continue;
    const invKey = `${serie}|${numar}`;
    const canal = r.Canal || '(necunoscut)';
    if (!byCod[cod]) byCod[cod] = {};
    if (!byCod[cod][canal]) byCod[cod][canal] = new Set();
    byCod[cod][canal].add(invKey);

    if (!byCodMeta[cod]) byCodMeta[cod] = { tranzactie: '', articol: '' };
    if (!byCodMeta[cod].tranzactie && r.TipTranzactie) byCodMeta[cod].tranzactie = String(r.TipTranzactie);
    if (!byCodMeta[cod].articol    && r.Articol)       byCodMeta[cod].articol    = String(r.Articol);
  }

  // Total per Cod for ranking. Then take top N most-used codes.
  const codAgg = Object.entries(byCod).map(([cod, canalMap]) => {
    let total = 0;
    for (const set of Object.values(canalMap)) total += set.size;
    return { cod, total, canalMap };
  }).sort((a, b) => b.total - a.total).slice(0, TOP_N);

  // Grand total across ALL codes (not just top N) — used for share-of-total info.
  const grandTotal = Object.entries(byCod).reduce((s, [, canalMap]) => {
    for (const set of Object.values(canalMap)) s += set.size;
    return s;
  }, 0);

  // Distinct canals across the displayed codes — one stack color per canal,
  // sorted alphabetically for stable visual order.
  const canalSet = new Set();
  for (const c of codAgg) {
    for (const ch of Object.keys(c.canalMap)) canalSet.add(ch);
  }
  const canals = Array.from(canalSet).sort();

  const metaEl = document.getElementById('chartFacturiCodCanalMeta');
  if (metaEl) {
    metaEl.textContent = codAgg.length
      ? (codAgg.length < TOP_N
          ? `${codAgg.length} coduri × ${canals.length} canale`
          : `Top ${TOP_N} coduri × ${canals.length} canale`)
      : 'Stivuit pe Canal';
  }

  if (_chartFacturiCodCanal) { _chartFacturiCodCanal.destroy(); _chartFacturiCodCanal = null; }
  if (codAgg.length === 0 || typeof Chart === 'undefined') {
    showChartEmpty('chartFacturiCodCanal', 'chartFacturiCodCanalEmpty', true);
    return;
  }
  showChartEmpty('chartFacturiCodCanal', 'chartFacturiCodCanalEmpty', false);

  const labels = codAgg.map(c => c.cod);
  const datasets = canals.map((canal, i) => ({
    label: canal,
    data: codAgg.map(c => c.canalMap[canal] ? c.canalMap[canal].size : 0),
    backgroundColor: CHART_PALETTE[i % CHART_PALETTE.length],
    borderWidth: 0
  }));

  const ctx = document.getElementById('chartFacturiCodCanal').getContext('2d');
  _chartFacturiCodCanal = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      // Show all stacked segments at once when hovering — needed so the
      // footer total and percentage represent the entire Cod, not a slice.
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 11, boxHeight: 11, font: { size: 10 }, padding: 8 } },
        tooltip: {
          callbacks: {
            // Title: Cod + Tranzacție description (if known)
            title: (items) => {
              if (!items.length) return '';
              const cod = items[0].label;
              const meta = byCodMeta[cod];
              return (meta && meta.tranzactie)
                ? `${cod} — ${meta.tranzactie}`
                : `Cod: ${cod}`;
            },
            // Before-body: Articol info, shown once at the top before the per-canal lines
            beforeBody: (items) => {
              if (!items.length) return [];
              const cod = items[0].label;
              const meta = byCodMeta[cod];
              return (meta && meta.articol) ? [`Articol: ${meta.articol}`] : [];
            },
            label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} facturi`,
            footer: (items) => {
              const sum = items.reduce((s, it) => s + it.parsed.y, 0);
              const lines = [`Total cod: ${sum} facturi`];
              if (grandTotal > 0) {
                lines.push(`${fmtPct((sum / grandTotal) * 100)} din total facturi`);
              }
              return lines;
            }
          }
        }
      },
      scales: {
        x: {
          stacked: true,
          ticks: {
            font: { size: 11 },
            // Diagonally rotate long Cod labels so they don't overlap
            maxRotation: 50,
            minRotation: 30,
            autoSkip: false
          }
        },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}

// Top N articles, stacked column with years on the X-axis. Each year shows
// the breakdown by article — useful for spotting year-over-year shifts in
// revenue mix. Reuses the same palette as the other dash charts.
function renderArticleYearChart(rows) {
  const TOP_N = 10;
  // article → year → sumRonVal
  const byArticle = {};
  for (const r of rows) {
    const ar = r.Articol || '(necunoscut)';
    const an = Number(r.An);
    if (!an) continue;
    if (!byArticle[ar]) byArticle[ar] = {};
    byArticle[ar][an] = (byArticle[ar][an] || 0) + (Number(r.Ron_Val) || 0);
  }

  // Rank articles by total absolute value across all years; take top N.
  const articleAgg = Object.entries(byArticle).map(([ar, yearMap]) => {
    let total = 0;
    for (const v of Object.values(yearMap)) total += Math.abs(v);
    return { article: ar, total, yearMap };
  }).sort((a, b) => b.total - a.total).slice(0, TOP_N);

  // Years across the filtered dataset, sorted ascending
  const years = [...new Set(rows.map(r => Number(r.An)).filter(Boolean))]
    .sort((a, b) => a - b);

  const metaEl = document.getElementById('chartArticleYearMeta');
  if (metaEl) metaEl.textContent = articleAgg.length
    ? `${articleAgg.length} articole × ${years.length} ${years.length === 1 ? 'an' : 'ani'} · stivuit`
    : 'Top 10 articole · stivuit';

  if (_chartArticleYear) { _chartArticleYear.destroy(); _chartArticleYear = null; }
  if (articleAgg.length === 0 || years.length === 0 || typeof Chart === 'undefined') {
    showChartEmpty('chartArticleYear', 'chartArticleYearEmpty', true);
    return;
  }
  showChartEmpty('chartArticleYear', 'chartArticleYearEmpty', false);

  const labels = years.map(String);
  const datasets = articleAgg.map((a, i) => ({
    label: a.article,
    data: years.map(y => a.yearMap[y] || 0),
    backgroundColor: CHART_PALETTE[i % CHART_PALETTE.length],
    borderWidth: 0
  }));

  const ctx = document.getElementById('chartArticleYear').getContext('2d');
  // Per-year totals for tooltip footer percentage calc
  const yearTotals = years.map(y =>
    articleAgg.reduce((s, a) => s + (a.yearMap[y] || 0), 0)
  );

  _chartArticleYear = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      // Show all stacked segments at once when hovering — needed for footer
      // sum + percentage to be meaningful.
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 11, boxHeight: 11, font: { size: 10 }, padding: 8 } },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const v = ctx.parsed.y;
              const yt = yearTotals[ctx.dataIndex] || 0;
              const pct = yt ? ` · ${fmtPct((v / yt) * 100)}` : '';
              return `${ctx.dataset.label}: ${fmtNum(v)} RON${pct}`;
            },
            footer: (items) => {
              if (!items.length) return '';
              const sum = items.reduce((s, it) => s + it.parsed.y, 0);
              return `Total ${items[0].label}: ${fmtNum(sum)} RON`;
            }
          }
        }
      },
      scales: {
        x: { stacked: true },
        y: {
          stacked: true, beginAtZero: true,
          ticks: { callback: v => fmtNum(v) }
        }
      }
    }
  });
}

// ---- Nomenclator (CRUD) ----
const NOMENCLATOR_FIELDS = [
  { name: 'Cod', label: 'Cod', required: true, placeholder: 'ex: FAACP', hint: 'Identificator unic - face VLOOKUP cu coloana "Tip factura" din eMag.' },
  { name: 'Tranzactie', label: 'Tranzactie', required: true, placeholder: 'ex: Avans promovare' },
  { name: 'Articol', label: 'Articol', type: 'select', options: [
    'Ads pre-paid', 'Comision marketplace', 'Corectie Factura', 'FBE - Depozitare',
    'FBE - Despagubire', 'FBE - Pregatire livrare', 'FBE - Transport livrare',
    'Livrare Genius', 'Penalizare comerciala', 'Plati / Incasari prin Emag',
    'Servicii casare', 'Transport Suplimentar', 'Voucher Despagubire Defecte',
    'Voucher Discount Vanzare'
  ]},
  { name: 'Cont factura', label: 'Cont factura (tert)', type: 'number', required: true, placeholder: 'ex: 401' },
  { name: 'Cont articol', label: 'Cont articol', type: 'number', required: true, placeholder: 'ex: 4092' },
  { name: 'Formula contabila', label: 'Formula contabila', placeholder: 'ex: 4092 = 401', hint: 'Format: ContArticol = ContTert' },
  { name: 'Problematica', label: 'Problematica (Flux)', type: 'select', required: true,
    options: ['Cumparare | Intrare', 'Vanzare | Iesire'] },
  { name: 'TipOpsTVA', label: 'Tip Ops TVA', type: 'select', options: [
    '[ ] Ops cu TZ cu TVA impozabile',
    '[n] Ops cu TZ cu TVA neimpozabile'
  ]},
  { name: 'TipTVA', label: 'Tip TVA', type: 'number', step: '1', placeholder: '0, 1 sau 2' },
  { name: 'Cost Canal', label: 'Cost Canal', type: 'select', options: [
    'Comision marketplace', 'Corectie', 'Decontari', 'Despagubire FBE Curier',
    'Despagubire FBE Produse', 'Despagubire Genius Produse', 'Penalizare',
    'Promovare', 'Servicii FBE', 'Servicii MKP', 'Taxa Genius'
  ]},
  { name: 'Canal', label: 'Canal', type: 'select', required: true, options: ['MKP', 'FBE'] },
  { name: 'Status', label: 'Status', type: 'select', required: true, options: ['Activ', 'Inactiv'] },
  { name: 'Detalii', label: 'Detalii', type: 'textarea', placeholder: 'Descriere extinsă...' }
];

async function renderNomenclator(root) {
  const data = await dbGetAll('nomenclator');
  data.sort((a, b) => a.Cod.localeCompare(b.Cod));
  root.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Nomenclator</h1>
        <p class="subtitle">${data.length} coduri tip factură. Mapări contabile pentru fiecare tip de operațiune eMag.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="addBtn">➕ Adaugă cod nou</button>
      </div>
    </div>
    ${invoiceTypesNotice(data)}
    <div class="card">
      <input type="text" class="filter" placeholder="🔍 Caută după cod, tranzacție, articol, cont..." id="nomFilter"/>
      <div class="table-wrap">
        <table class="data-table compact">
          <thead><tr>
            <th>Cod</th><th>Tranzacție</th><th>Articol</th>
            <th>Cont T</th><th>Cont Tz</th><th>Formula</th>
            <th>Tip Ops TVA</th><th>Tip TVA</th>
            <th>Cost Canal</th><th>Canal</th><th>Status</th><th class="num">Acțiuni</th>
          </tr></thead>
          <tbody id="nomBody"></tbody>
        </table>
      </div>
    </div>
  `;

  // Etichetă scurtă pentru TipOpsTVA: "[ ] ..." = Impozabil, "[n] ..." = Neimpozabil
  const opsBadge = (r) => {
    const raw = String(r.TipOpsTVA || '');
    if (!raw) return '<span class="hint">—</span>';
    const neimp = raw.startsWith('[n]');
    const cls = neimp ? 'status-inactiv' : 'status-activ';
    const lbl = neimp ? 'Neimpozabil' : 'Impozabil';
    return `<span class="status ${cls}" title="${escapeAttr(raw)}">${lbl}</span>`;
  };

  const tableView = QcxTable.create({paginate:true,body:'nomBody',key:'nomenclator',title:'Nomenclator',onChange:()=>renderRows($('#nomFilter').value),columns:[{key:'Cod'},{key:'Tranzactie'},{key:'Articol'},{key:'Cont factura'},{key:'Cont articol'},{key:'Formula contabila'},{key:'TipOpsTVA'},{key:'TipTVA',type:'number',decimals:0},{key:'Cost Canal'},{key:'Canal'},{key:'Status'}]});
  const renderRows = (filter = '') => {
    const f = filter.toLowerCase();
    let filtered = data.filter(r =>
      !f || Object.values(r).some(v => String(v||'').toLowerCase().includes(f))
    );
    filtered = tableView.pageRows(tableView.apply(filtered));
    $('#nomBody').innerHTML = filtered.map(r => `
      <tr data-cod="${escapeAttr(r.Cod)}">
        <td><b>${esc(r.Cod)}</b></td>
        <td>${esc(r.Tranzactie)}</td>
        <td>${esc(r.Articol)}</td>
        <td>${esc(r['Cont factura'])}</td>
        <td>${esc(r['Cont articol'])}</td>
        <td><code>${esc(r['Formula contabila'])}</code></td>
        <td>${opsBadge(r)}</td>
        <td class="num">${esc(r.TipTVA ?? '')}</td>
        <td>${esc(r['Cost Canal'])}</td>
        <td><span class="badge badge-${(r.Canal||'').toLowerCase()}">${esc(r.Canal)}</span></td>
        <td><span class="status status-${(r.Status||'').toLowerCase()}">${esc(r.Status)}</span></td>
        <td class="num">
          <button class="btn-icon" data-act="edit" title="Editează">✏️</button>
          <button class="btn-icon" data-act="duplicate" title="Duplică">📋</button>
          <button class="btn-icon" data-act="delete" title="Șterge">🗑️</button>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="12" class="empty">Niciun rezultat.</td></tr>';

    tableView.rendered(filtered);
    $$('#nomBody [data-act]').forEach(btn => {
      btn.onclick = async () => {
        const tr = btn.closest('tr');
        const cod = tr.dataset.cod;
        const item = data.find(r => r.Cod === cod);
        const act = btn.dataset.act;
        if (act === 'edit') openNomEditor(item, false);
        else if (act === 'duplicate') openNomEditor({ ...item, Cod: '' }, true);
        else if (act === 'delete') {
          if (!await confirmDialog(`Sigur ștergi codul <b>${esc(cod)}</b>?<br/><span class="hint">Această acțiune este definitivă.</span>`)) return;
          await dbDelete('nomenclator', cod);
          toast(`Cod "${cod}" șters`, 'success');
          renderNomenclator(root);
        }
      };
    });
  };
  renderRows();
  $('#nomFilter').oninput = (e) => renderRows(e.target.value);
  $('#addBtn').onclick = () => openNomEditor({}, true);

  function openNomEditor(values, isNew) {
    openModal({
      title: isNew ? '➕ Adaugă cod nomenclator' : `✏️ Editare cod: ${values.Cod}`,
      fields: NOMENCLATOR_FIELDS.map(f => ({
        ...f,
        readonly: !isNew && f.name === 'Cod'
      })),
      values,
      size: 'lg',
      deleteBtn: !isNew,
      onDelete: async () => {
        await dbDelete('nomenclator', values.Cod);
        toast(`Cod "${values.Cod}" șters`, 'success');
        renderNomenclator(root);
      },
      onSave: async (formData) => {
        if (isNew) {
          const exists = await dbGet('nomenclator', formData.Cod);
          if (exists) throw new Error(`Codul "${formData.Cod}" există deja.`);
        }
        await dbPut('nomenclator', { ...formData, NecesitaActualizare: false });
        toast(isNew ? `Cod "${formData.Cod}" adăugat` : `Cod "${formData.Cod}" salvat`, 'success');
        renderNomenclator(root);
      }
    });
  }
}

// HTML escape helpers
function esc(v) { if (v === null || v === undefined) return ''; return String(v).replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])); }
function escAttr(v) { return esc(v).replace(/"/g, '&quot;'); }
function escapeAttr(v) { return escAttr(v); }

// ---- Marketplaces (CRUD) ----
const MARKETPLACE_FIELDS = [
  { name: 'Marketplace', label: 'Marketplace', required: true, placeholder: 'ex: Emag MKP RO',
    hint: 'Identificator unic - face match cu coloana "Canal" din eMag.' },
  { name: 'IdSeller', label: 'ID Seller', type: 'number', required: true, placeholder: 'ex: 146571' },
  { name: 'Parteneri', label: 'Partener (entitate juridică)', placeholder: 'ex: Dante International S.A.' },
  { name: 'CUI', label: 'CUI', placeholder: 'ex: 14399840' },
  { name: 'AF', label: 'Afiliere fiscală', type: 'select', options: ['RO', 'HU', 'BG'] },
  { name: 'CotaTVA', label: 'Cota TVA (%)', type: 'number', step: '1', required: true, placeholder: '19, 20, 21, 27...' },
  { name: 'Moneda', label: 'Moneda', type: 'select', required: true, options: ['RON', 'EUR', 'HUF_100'],
    hint: 'HUF_100 = curs HUF per 100 unități (BNR)' },
  { name: 'Demultiplicator', label: 'Demultiplicator', type: 'number', required: true, placeholder: '1 sau 100',
    hint: '1 pentru EUR/RON, 100 pentru HUF_100' },
  { name: 'Tara', label: 'Țară', placeholder: 'ex: Romania' },
  { name: 'Localitate', label: 'Localitate', placeholder: 'ex: Bucuresti' }
];

async function renderMarketplaces(root) {
  const data = await dbGetAll('marketplaces');
  data.sort((a, b) => (a.Marketplace||'').localeCompare(b.Marketplace||''));
  root.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Marketplaces</h1>
        <p class="subtitle">${data.length} canale eMag configurate. Monedă, cota TVA și demultiplicator per canal.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="addBtn">➕ Adaugă marketplace</button>
      </div>
    </div>
    <div class="card">
      <input type="text" class="filter" placeholder="🔍 Caută..." id="mpFilter"/>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr>
            <th>Marketplace</th><th>ID Seller</th><th>Partener</th>
            <th>CUI</th><th>AF</th><th class="num">Cota TVA</th>
            <th>Monedă</th><th class="num">Demult.</th><th>Țară</th><th class="num">Acțiuni</th>
          </tr></thead>
          <tbody id="mpBody"></tbody>
        </table>
      </div>
    </div>
  `;

  const tableView = QcxTable.create({paginate:true,body:'mpBody',key:'marketplaces',title:'Marketplaces',onChange:()=>renderRows($('#mpFilter').value),columns:[{key:'Marketplace'},{key:'IdSeller'},{key:'Parteneri'},{key:'CUI'},{key:'AF'},{key:'CotaTVA',type:'number'},{key:'Moneda'},{key:'Demultiplicator',type:'number',decimals:0},{key:'Tara',get:r=>[r.Tara,r.Localitate].filter(Boolean).join(', ')}]});
  const renderRows = (filter = '') => {
    const f = filter.toLowerCase();
    let filtered = data.filter(r =>
      !f || Object.values(r).some(v => String(v||'').toLowerCase().includes(f))
    );
    filtered = tableView.pageRows(tableView.apply(filtered));
    $('#mpBody').innerHTML = filtered.map(m => `
      <tr data-id="${escAttr(m.IdSeller)}" data-mkp="${escAttr(m.Marketplace)}">
        <td><b>${esc(m.Marketplace)}</b></td>
        <td>${esc(m.IdSeller)}</td>
        <td>${esc(m.Parteneri)}</td>
        <td>${esc(m.CUI)}</td>
        <td>${esc(m.AF)}</td>
        <td class="num">${esc(m.CotaTVA)}%</td>
        <td><span class="badge badge-mkp">${esc(m.Moneda)}</span></td>
        <td class="num">${esc(m.Demultiplicator)}</td>
        <td>${esc(m.Tara)}${m.Localitate ? ', '+esc(m.Localitate) : ''}</td>
        <td class="num">
          <button class="btn-icon" data-act="edit" title="Editează">✏️</button>
          <button class="btn-icon" data-act="duplicate" title="Duplică">📋</button>
          <button class="btn-icon" data-act="delete" title="Șterge">🗑️</button>
        </td>
      </tr>
    `).join('') || '<tr><td colspan="10" class="empty">Niciun rezultat.</td></tr>';

    tableView.rendered(filtered);
    $$('#mpBody [data-act]').forEach(btn => {
      btn.onclick = async () => {
        const tr = btn.closest('tr');
        const id = parseInt(tr.dataset.id, 10);
        const item = data.find(r => r.IdSeller === id);
        const act = btn.dataset.act;
        if (act === 'edit') openMpEditor(item, false);
        else if (act === 'duplicate') openMpEditor({ ...item, IdSeller: '', Marketplace: item.Marketplace + ' (copie)' }, true);
        else if (act === 'delete') {
          if (!await confirmDialog(`Sigur ștergi marketplace-ul <b>${esc(item.Marketplace)}</b>?`)) return;
          await dbDelete('marketplaces', id);
          toast('Marketplace șters', 'success');
          renderMarketplaces(root);
        }
      };
    });
  };
  renderRows();
  $('#mpFilter').oninput = (e) => renderRows(e.target.value);
  $('#addBtn').onclick = () => openMpEditor({ Demultiplicator: 1, Moneda: 'RON' }, true);

  function openMpEditor(values, isNew) {
    openModal({
      title: isNew ? '➕ Adaugă marketplace' : `✏️ Editare: ${values.Marketplace}`,
      fields: MARKETPLACE_FIELDS.map(f => ({
        ...f,
        readonly: !isNew && f.name === 'IdSeller'
      })),
      values,
      size: 'lg',
      deleteBtn: !isNew,
      onDelete: async () => {
        await dbDelete('marketplaces', values.IdSeller);
        toast('Marketplace șters', 'success');
        renderMarketplaces(root);
      },
      onSave: async (formData) => {
        if (isNew) {
          const exists = await dbGet('marketplaces', formData.IdSeller);
          if (exists) throw new Error(`ID Seller ${formData.IdSeller} există deja.`);
        }
        await dbPut('marketplaces', formData);
        toast(isNew ? 'Marketplace adăugat' : 'Marketplace salvat', 'success');
        renderMarketplaces(root);
      }
    });
  }
}

// ---- Curs Valutar ----
async function renderCursValutar(root) {
  const data = await dbGetAll('cursValutar');
  data.sort((a, b) => (a.Data + a.Valuta).localeCompare(b.Data + b.Valuta));
  const currentYear = new Date().getFullYear();
  root.innerHTML = `
    <div class="page-head">
      <h1>Curs Valutar BNR</h1>
      <p class="subtitle">${data.length} cursuri stocate. Preia cursurile oficiale de pe <a href="https://www.bnr.ro" target="_blank">bnr.ro</a>.</p>
    </div>

    <div class="cards">
      <div class="card">
        <h3>🔄 Importă din BNR</h3>
        <p class="hint">Selectează anul. Aplicația încearcă mai multe surse (direct + proxy CORS) până găsește una funcțională.</p>
        <div class="form-row">
          <label>An:</label>
          <select id="bnrYear"></select>
          <button class="btn btn-primary" id="bnrFetch">Importă</button>
        </div>
        <div class="hint" style="margin-top:.5rem">
          Surse: bnr.ro, curs.bnr.ro + proxy-uri (corsproxy.io, allorigins, codetabs, thingproxy)
        </div>
      </div>

      <div class="card">
        <h3>📁 Importă XML salvat</h3>
        <p class="hint">Descarcă XML și încarcă-l aici.</p>
        <div class="form-row" style="margin-bottom:.5rem">
          <a class="btn" id="bnrOpenLink" target="_blank" rel="noopener">↗️ Deschide BNR</a>
        </div>
        <div class="form-row">
          <input type="file" id="bnrXmlFile" accept=".xml"/>
          <button class="btn" id="bnrXmlBtn">Importă XML</button>
        </div>
      </div>

      <div class="card">
        <h3>📋 Lipește XML</h3>
        <p class="hint">Deschide URL-ul, copiază tot conținutul (Ctrl+A, Ctrl+C) și lipește aici.</p>
        <textarea id="bnrPasteXml" placeholder="<?xml version=&quot;1.0&quot; ...?>" style="width:100%;min-height:80px;padding:.5rem;border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;resize:vertical"></textarea>
        <button class="btn" id="bnrPasteBtn" style="margin-top:.5rem">Procesează XML lipit</button>
      </div>

      <div class="card">
        <h3>➕ Adaugă manual</h3>
        <div class="form-row">
          <input type="date" id="manData" required/>
          <select id="manValuta"><option>EUR</option><option>HUF_100</option></select>
          <input type="number" step="0.0001" id="manCurs" placeholder="Curs"/>
          <button class="btn" id="manAdd">Adaugă</button>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:1rem">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:.5rem">
        <input type="text" class="filter" placeholder="🔍 Caută după dată..." id="cursFilter" style="flex:1"/>
        <button class="btn btn-danger" id="cursClear">🗑️ Șterge tot</button>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Dată</th><th>Valută</th><th>Demultiplicator</th><th>Curs (RON)</th><th>Sursă</th></tr></thead>
          <tbody id="cursBody"></tbody>
        </table>
      </div>
    </div>
  `;

  // Year dropdown
  const yearSel = $('#bnrYear');
  for (let y = currentYear; y >= currentYear - 5; y--) {
    const o = document.createElement('option');
    o.value = y; o.textContent = y;
    yearSel.appendChild(o);
  }

  // Update BNR link based on selected year
  const updateBnrLink = () => {
    const y = parseInt(yearSel.value, 10);
    const isCur = y === new Date().getFullYear();
    const url = isCur
      ? 'https://www.bnr.ro/nbrfxrates.xml'
      : `https://www.bnr.ro/files/xml/years/nbrfxrates${y}.xml`;
    $('#bnrOpenLink').href = url;
    $('#bnrOpenLink').textContent = `↗️ Deschide BNR ${y}`;
  };
  updateBnrLink();
  yearSel.onchange = updateBnrLink;

  const tableView = QcxTable.create({paginate:true,body:'cursBody',key:'cursValutar',title:'Curs Valutar',onChange:()=>renderRows($('#cursFilter').value),columns:[{key:'Data',type:'date'},{key:'Valuta'},{key:'Demultiplicator',type:'number',decimals:0},{key:'Curs',type:'number',decimals:4},{key:'Sursa'}]});
  const renderRows = (filter = '') => {
    const f = filter.toLowerCase();
    const tbody = $('#cursBody');
    let filtered = data.filter(r => !f || (r.Data + r.Valuta).toLowerCase().includes(f));
    filtered = tableView.pageRows(tableView.apply(filtered));
    tbody.innerHTML = filtered.map(r => `
      <tr>
        <td>${r.Data}</td>
        <td><span class="badge badge-mkp">${r.Valuta}</span></td>
        <td>${r.Demultiplicator}</td>
        <td><b>${fmtNum(r.Curs, 4)}</b></td>
        <td>${r.Sursa || ''}</td>
      </tr>
    `).join('') || '<tr><td colspan="5" class="empty">Niciun curs stocat. Importă din BNR.</td></tr>';
    tableView.rendered(filtered);
  };
  renderRows();
  $('#cursFilter').oninput = (e) => renderRows(e.target.value);

  $('#bnrFetch').onclick = async () => {
    const year = parseInt(yearSel.value, 10);
    setBusy(true, `Pornesc importul BNR pentru ${year}...`);
    try {
      const result = await importBnrYear(year, (msg) => {
        $('#busy-msg').textContent = msg;
      });
      toast(`Importate ${result.count} cursuri (${result.source})`, 'success');
      renderCursValutar(root);
    } catch (e) {
      console.error('[BNR] Import eșuat:', e);
      // Show detailed error in a modal
      const overlay = document.createElement('div');
      overlay.className = 'modal-overlay show';
      overlay.innerHTML = `
        <div class="modal modal-md">
          <div class="modal-head"><h3>❌ Import BNR eșuat</h3></div>
          <div class="modal-body">
            <p style="margin-bottom:.75rem">Niciuna din sursele încercate nu a răspuns. Aceasta poate fi din cauza:</p>
            <ul style="margin-left:1.25rem;color:var(--text-muted);font-size:.875rem">
              <li>BNR.ro nu permite CORS din browser</li>
              <li>Toate proxy-urile publice sunt indisponibile</li>
              <li>Conexiune internet întreruptă</li>
            </ul>
            <details style="margin-top:.75rem"><summary style="cursor:pointer;font-size:.85rem;color:var(--text-muted)">Detalii tehnice</summary>
              <pre style="background:#f3f4f6;padding:.5rem;border-radius:4px;font-size:.75rem;overflow:auto;max-height:200px;margin-top:.5rem">${esc(e.message)}</pre>
            </details>
            <div style="margin-top:1rem;padding:.75rem;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px">
              <b style="color:#92400e">Soluție alternativă:</b><br/>
              <span style="font-size:.875rem">
                1. Deschide <a href="https://www.bnr.ro/files/xml/years/nbrfxrates${year}.xml" target="_blank">www.bnr.ro/.../${year}.xml</a> într-un tab nou<br/>
                2. Salvează (Ctrl+S) ca fișier .xml<br/>
                3. Folosește butonul <b>"Importă XML manual"</b> de mai jos
              </span>
            </div>
          </div>
          <div class="modal-foot"><div class="spacer"></div><button type="button" class="btn btn-primary" data-close>OK</button></div>
        </div>
      `;
      document.body.appendChild(overlay);
      overlay.querySelector('[data-close]').onclick = () => { overlay.classList.remove('show'); setTimeout(() => overlay.remove(), 200); };
      overlay.onclick = (ev) => { if (ev.target === overlay) overlay.querySelector('[data-close]').click(); };
    } finally {
      setBusy(false);
    }
  };

  $('#bnrXmlBtn').onclick = async () => {
    const file = $('#bnrXmlFile').files[0];
    if (!file) { toast('Selectează un fișier XML.', 'error'); return; }
    setBusy(true, 'Se procesează XML...');
    try {
      const text = await file.text();
      const rates = parseBnrXml(text);
      if (rates.length === 0) { toast('XML nu conține cursuri valide.', 'error'); return; }
      await dbBulkPut('cursValutar', rates);
      toast(`Importate ${rates.length} cursuri din XML`, 'success');
      renderCursValutar(root);
    } catch (e) {
      toast('Eroare: ' + e.message, 'error');
    } finally {
      setBusy(false);
    }
  };

  $('#bnrPasteBtn').onclick = async () => {
    const text = ($('#bnrPasteXml').value || '').trim();
    if (!text) { toast('Lipește XML-ul în textarea.', 'error'); return; }
    if (!/<\s*(\?xml|DataSet|Cube)/i.test(text.slice(0, 500))) {
      toast('Nu pare a fi XML BNR valid (nu conține <?xml> sau <DataSet>).', 'error');
      return;
    }
    setBusy(true, 'Se procesează XML lipit...');
    try {
      const rates = parseBnrXml(text);
      if (rates.length === 0) { toast('XML nu conține cursuri EUR/HUF.', 'error'); return; }
      await dbBulkPut('cursValutar', rates);
      toast(`Importate ${rates.length} cursuri din text lipit`, 'success');
      renderCursValutar(root);
    } catch (e) {
      console.error('[BNR paste] Eroare:', e);
      toast('Eroare la parsare: ' + e.message, 'error');
    } finally {
      setBusy(false);
    }
  };

  $('#manAdd').onclick = async () => {
    const dataVal = $('#manData').value;
    const valuta = $('#manValuta').value;
    const curs = parseFloat($('#manCurs').value);
    if (!dataVal || !curs) { toast('Completează data și cursul.', 'error'); return; }
    const [y, m, d] = dataVal.split('-').map(Number);
    const demult = valuta === 'HUF_100' ? 100 : 1;
    await dbPut('cursValutar', {
      key: `${dataVal}|${valuta}`, Data: dataVal, An: y, Luna: m, Zi: d,
      Valuta: valuta, Demultiplicator: demult, Curs: curs, Sursa: 'Manual'
    });
    toast('Curs adăugat', 'success');
    renderCursValutar(root);
  };

  $('#cursClear').onclick = async () => {
    if (!confirm('Sigur ștergi toate cursurile?')) return;
    await dbClear('cursValutar');
    toast('Cursuri șterse', 'success');
    renderCursValutar(root);
  };
}

// ---- Upload ----
async function renderUpload(root) {
  root.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Încărcare date eMag</h1>
        <p class="subtitle">Acceptă exportul direct din eMag (Financiar → Facturi) sau șablonul nostru.</p>
      </div>
    </div>

    <div class="cards upload-cards">
      <div class="card upload-drop-card">
        <div id="dropZone" class="drop-zone">
          <div class="drop-icon">📤</div>
          <div class="drop-text"><b>Trage fișierul Excel aici</b> sau apasă pentru a selecta</div>
          <div class="hint">Acceptă .xlsx, .xlsm, .xls — încarcă fișiere multiple, unul după altul, pentru fiecare canal.</div>
          <input type="file" id="excelFile" accept=".xlsx,.xlsm,.xls" hidden/>
        </div>
        <div id="uploadResult"></div>
      </div>
      <div class="card">
        <h3>📥 Export eMag (recomandat)</h3>
        <p class="hint" style="margin-bottom:.5rem">Sheet-ul <code>Worksheet</code> cu 23 coloane. Acceptă headere în:</p>
        <ul class="bullets" style="font-size:.85rem">
          <li>🇷🇴 <b>Română</b> — exporturi RO (<code>ID Seller</code>, <code>Tip factura</code>)</li>
          <li>🇬🇧 <b>Engleză</b> — exporturi BG &amp; HU (<code>Seller ID</code>, <code>Invoice type</code>)</li>
          <li><b>Canal</b> determinat automat din <code>ID Seller</code>. <b>Datele</b> US <code>mm/dd/yyyy</code> convertite automat.</li>
        </ul>
      </div>
      <div class="card">
        <h3>📋 Șablon legacy</h3>
        <p class="hint" style="margin-bottom:.5rem">Sheet-ul <code>EMagRawData</code> cu 26 coloane (Canal/Moneda completate manual).</p>
        <ul class="bullets" style="font-size:.85rem">
          <li>Folosește acest format dacă introduci date manual sau procesezi un export modificat.</li>
        </ul>
      </div>
      <div class="card">
        <h3>⚙️ Setări import</h3>
        <label class="checkbox" style="display:flex;align-items:center;gap:.5rem;font-size:.9rem">
          <input type="checkbox" id="appendMode" checked/>
          <span>Adaugă la datele existente (debifează pentru a suprascrie tot)</span>
        </label>
        <div class="hint" style="margin-top:.5rem;font-size:.78rem">
          ✓ Deduplicarea după <code>Serie/numar factura</code> e activă mereu — același document nu poate fi importat de două ori.
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:1rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
        <h3 style="margin:0">📜 Istoric importuri</h3>
        <span class="hint" id="importHistCount"></span>
      </div>
      <div id="importHistory"></div>
    </div>
  `;

  const dz = $('#dropZone'), fi = $('#excelFile');
  dz.onclick = () => fi.click();
  dz.ondragover = (e) => { e.preventDefault(); dz.classList.add('drag'); };
  dz.ondragleave = () => dz.classList.remove('drag');
  dz.ondrop = (e) => { e.preventDefault(); dz.classList.remove('drag'); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); };
  fi.onchange = () => fi.files[0] && handleFile(fi.files[0]);

  async function handleFile(file) {
    const result = $('#uploadResult');
    result.innerHTML = '<div class="loading">Se citește fișierul...</div>';
    setBusy(true, 'Se procesează Excel-ul...');
    try {
      const r = await readExcelFile(file);
      if (r.error) {
        result.innerHTML = `<div class="error">${r.error}</div>`;
        return;
      }
      // Note: appendMode is no longer relevant for dedup - duplicates are always skipped.
      // But we still honor "overwrite" mode if user wants to clear all rawData first.
      if (!$('#appendMode').checked) {
        await dbClear('rawData');
        await dbClear('importLog');
      }
      const invoiceTypes = await ensureInvoiceTypes(r.rows);
      await dbBulkPut('rawData', r.rows);

      // Save import log entry
      await dbPut('importLog', {
        fileName: file.name,
        fileSize: file.size,
        importedAt: new Date().toISOString(),
        format: r.format,
        lang: r.lang || 'ro',
        rowsImported: r.rows.length,
        skippedDuplicates: r.skippedDuplicates || 0,
        channels: r.channelStats || {},
        batchId: r.batchId
      });

      const total = await dbCount('rawData');

      const fmtBadge = r.format === 'real'
        ? `<span class="badge badge-fbe">Export eMag · headere ${r.lang === 'en' ? '🇬🇧 EN (BG/HU)' : '🇷🇴 RO'}</span>`
        : '<span class="badge badge-mkp">Șablon legacy (EMagRawData)</span>';
      const channelsHtml = Object.entries(r.channelStats).map(([c, n]) =>
        `<li><b>${esc(c)}</b> — ${n} ${n === 1 ? 'factură' : 'facturi'}</li>`
      ).join('');
      const warnHtml = r.warnings.length > 0
        ? `<details style="margin-top:.75rem"><summary class="error" style="cursor:pointer">⚠️ ${r.warnings.length} avertismente</summary><ul style="margin:.5rem 0 0 1.25rem">${r.warnings.map(w=>`<li>${esc(w)}</li>`).join('')}</ul></details>`
        : '';
      const dedupHtml = r.skippedDuplicates > 0
        ? `<div class="info-line" style="margin-top:.5rem;padding:.5rem .75rem;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px;font-size:.85rem">
             ⏭️ <b>${r.skippedDuplicates}</b> ${r.skippedDuplicates === 1 ? 'factură' : 'facturi'} sărite (deja importate prin alt fișier).
           </div>` : '';

      result.innerHTML = `
        <div class="success">
          ✅ Importate <b>${r.rows.length}</b> linii din <code>${esc(file.name)}</code> ${fmtBadge}<br/>
          Total facturi în baza de date: <b>${total}</b>.
        </div>
        ${invoiceTypesNotice(invoiceTypes)}
        ${dedupHtml}
        <div class="card" style="margin-top:.75rem;background:#f9fafb">
          <h3 style="font-size:.9rem;margin-bottom:.5rem">📊 Canale detectate</h3>
          <ul style="padding-left:1.25rem;margin:0;font-size:.875rem">${channelsHtml}</ul>
          ${warnHtml}
        </div>
        <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
          <button class="btn btn-primary" onclick="navigate('rawdata')">Vezi datele încărcate →</button>
          <button class="btn" onclick="navigate('dataset')">Procesează DataSet →</button>
        </div>
      `;
      toast(`Importate ${r.rows.length} facturi${r.skippedDuplicates ? ` · ${r.skippedDuplicates} duplicate sărite` : ''}`, 'success');

      // Refresh import history list
      renderImportHistory();
    } catch (e) {
      result.innerHTML = `<div class="error">${e.message}</div>`;
      console.error(e);
    } finally {
      setBusy(false);
    }
  }

  async function renderImportHistory() {
    const logs = await dbGetAll('importLog');
    logs.sort((a, b) => (b.importedAt || '').localeCompare(a.importedAt || ''));
    const container = $('#importHistory');
    if (!container) return;
    if (logs.length === 0) {
      container.innerHTML = `<div class="empty" style="padding:1rem;color:var(--text-muted);font-size:.85rem">Niciun fișier importat încă.</div>`;
      return;
    }
    container.innerHTML = `
      <div class="table-wrap" style="max-height:340px">
        <table class="data-table compact">
          <thead><tr>
            <th>Data import</th>
            <th>Fișier</th>
            <th>Format</th>
            <th class="num">Importate</th>
            <th class="num">Sărite</th>
            <th>Canale</th>
            <th class="num">Acțiuni</th>
          </tr></thead>
          <tbody>
            ${logs.map(log => {
              const dt = new Date(log.importedAt);
              const dateStr = dt.toLocaleString('ro-RO', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
              const fmtTag = log.format === 'real'
                ? `<span class="badge badge-fbe">eMag ${log.lang === 'en' ? 'EN' : 'RO'}</span>`
                : `<span class="badge badge-mkp">Legacy</span>`;
              const channels = Object.entries(log.channels || {}).map(([c, n]) => `${esc(c)} (${n})`).join('<br/>');
              return `<tr data-log-id="${log.id}">
                <td><small>${dateStr}</small></td>
                <td><code style="font-size:.78rem">${esc(log.fileName)}</code></td>
                <td>${fmtTag}</td>
                <td class="num"><b>${log.rowsImported}</b></td>
                <td class="num">${log.skippedDuplicates ? `<span style="color:var(--warn)">${log.skippedDuplicates}</span>` : '–'}</td>
                <td><small>${channels || '–'}</small></td>
                <td class="num">
                  <button class="btn-icon" data-act="del-log" title="Șterge intrarea (păstrează facturile)">🗑️</button>
                  <button class="btn-icon" data-act="del-batch" title="Șterge intrarea + facturile importate">🗑️📋</button>
                </td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
      <div class="hint" style="margin-top:.5rem;font-size:.8rem">
        🗑️ — șterge doar intrarea din istoric (facturile rămân) · 🗑️📋 — șterge intrarea ȘI facturile aferente
      </div>
    `;

    container.querySelectorAll('[data-act]').forEach(btn => {
      btn.onclick = async () => {
        const tr = btn.closest('tr');
        const id = parseInt(tr.dataset.logId, 10);
        const log = logs.find(l => l.id === id);
        const act = btn.dataset.act;

        if (act === 'del-log') {
          if (!await confirmDialog(`Ștergi intrarea din istoric pentru <b>${esc(log.fileName)}</b>?<br/><span class="hint">Facturile importate rămân în baza de date.</span>`)) return;
          await dbDelete('importLog', id);
          toast('Intrare ștearsă din istoric', 'success');
          renderImportHistory();
        } else if (act === 'del-batch') {
          if (!await confirmDialog(`Ștergi intrarea ȘI cele <b>${log.rowsImported}</b> facturi importate din <b>${esc(log.fileName)}</b>?<br/><span class="hint">Această acțiune este definitivă.</span>`)) return;
          // Delete all rawData rows with matching batchId
          if (log.batchId) {
            const allRaw = await dbGetAll('rawData');
            const toDelete = allRaw.filter(r => r._batchId === log.batchId);
            for (const row of toDelete) await dbDelete('rawData', row.id);
            toast(`Șters import + ${toDelete.length} facturi`, 'success');
          }
          await dbDelete('importLog', id);
          renderImportHistory();
        }
      };
    });
  }

  // Initial render of import history
  renderImportHistory();
}

// ---- Raw Data (Excel-template style + CRUD) ----
const RAW_FIELDS = [
  { name: 'IDSeller', label: 'Marketplace (ID Seller)', type: 'select', required: true,
    hint: 'Selectează canalul. Moneda, Rezidența fiscală și Cota TVA implicită se preiau automat.' },
  { name: 'IDClient', label: 'ID Client', type: 'number' },
  { name: 'IDSupplier', label: 'ID Supplier', type: 'number' },
  { name: 'IDFP', label: 'ID FP', type: 'number' },
  { name: 'Seller', label: 'Cumpărător' },
  { name: 'CIFSeller', label: 'CIF cumpărător' },
  { name: 'ContBancar', label: 'Cont bancar seller' },
  { name: 'EntitateDante', label: 'Furnizor (entitate eMag)' },
  { name: 'CIFDante', label: 'CIF furnizor' },
  { name: 'Document', label: 'Document', type: 'select',
    options: ['Voucher invoice', 'Discount', 'Service', 'Storno', 'Commission', 'Penalty',
              'Adjustment', 'Comision Genius', 'Decont de voucher storno', 'Decont de vouchere',
              'FBE procesare retur', 'Factura Ads post-paid', 'Factura Depozitare',
              'Factura comision', 'Factura comision storno', 'Factura despagubire produse FBE',
              'Factura fulfillment comenzi', 'Factura retragere din fulfillment',
              'Promotie procesare comenzi FBE', ''] },
  { name: 'TipFactura', label: 'Tip factură', required: true,
    hint: 'CHEIE - cod din Nomenclator (FAACP, FCDP, FV, FFR, FS...).' },
  { name: 'SerieNumar', label: 'Serie/număr factură', required: true, placeholder: 'ex: D-MKTP-RO-1001450206' },
  { name: 'ValoareFaraTVA', label: 'Valoare fără TVA', type: 'number', step: '0.01', required: true },
  { name: 'ValoareTVA', label: 'Valoare TVA', type: 'number', step: '0.01' },
  { name: 'CotaTVA', label: 'Cota TVA (%)', type: 'number', step: '1' },
  { name: 'ValoareCuTVA', label: 'Valoare cu TVA', type: 'number', step: '0.01' },
  { name: 'CodTaxaSAP', label: 'Cod taxă SAP' },
  { name: 'BalantaFactura', label: 'Balanță factură', type: 'number', step: '0.01' },
  { name: 'DataEmitere', label: 'Data emitere factură', type: 'date', required: true,
    hint: 'CHEIE - cursul valutar se ia din ziua precedentă (data emiterii − 1).' },
  { name: 'DataScadenta', label: 'Data scadență factură', type: 'date' },
  { name: 'SerieNumarFP', label: 'Serie și număr FP' },
  { name: 'DataPayout', label: 'Data payout', type: 'date' },
  { name: 'MKTPFinance', label: 'MKTP Finance', placeholder: 'ex: Da' }
];

async function renderRawData(root) {
  const data = await dbGetAll('rawData');
  const marketplaces = await dbGetAll('marketplaces');
  const mpById = {};
  marketplaces.forEach(m => { mpById[Number(m.IdSeller)] = m; });
  const canalOptions = marketplaces.map(m => ({
    value: m.IdSeller,
    label: `${m.Marketplace} — ${m.Moneda} (${m.AF}) · ID ${m.IdSeller}`
  }));
  // Distinct canal names for filter dropdown
  const distinctCanale = [...new Set(data.map(r => {
    const mp = mpById[Number(r.IDSeller)];
    return mp ? mp.Marketplace : `(ID ${r.IDSeller})`;
  }))].sort();

  root.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Date eMag (raw)</h1>
        <p class="subtitle">${data.length} facturi · <b>Canal/Monedă/Rezidență</b> derivate automat din ID Seller (lookup în Marketplaces).</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="addBtn">➕ Adaugă factură</button>
        <button class="btn" id="uploadBtn">📤 Import Excel</button>
        <button class="btn btn-danger" id="rawClear" ${data.length === 0 ? 'disabled' : ''}>🗑️ Șterge tot</button>
      </div>
    </div>
    <div class="card">
      <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;flex-wrap:wrap">
        <input type="text" class="filter" placeholder="🔍 Caută în toate coloanele..." id="rawFilter" style="flex:1;min-width:220px"/>
        <select id="canalFilter">
          <option value="">Toate canalele</option>
          ${distinctCanale.map(c => `<option>${esc(c)}</option>`).join('')}
        </select>
        <span class="hint" id="rawCount"></span>
      </div>
      <div class="table-wrap excel-grid">
        <table class="data-table excel-table">
          <colgroup>
            <col style="width:180px"><col style="width:70px"><col style="width:80px">
            <col style="width:90px"><col style="width:90px"><col style="width:100px"><col style="width:80px">
            <col style="width:200px"><col style="width:110px"><col style="width:200px">
            <col style="width:200px"><col style="width:110px">
            <col style="width:130px"><col style="width:90px"><col style="width:200px">
            <col style="width:110px"><col style="width:90px"><col style="width:70px"><col style="width:110px">
            <col style="width:100px"><col style="width:110px">
            <col style="width:110px"><col style="width:110px">
            <col style="width:160px"><col style="width:110px"><col style="width:110px">
            <col style="width:110px">
          </colgroup>
          <thead>
            <tr class="excel-colnum">
              ${Array.from({length:26},(_,i)=>`<th class="colnum">${i+1}</th>`).join('')}
              <th class="colnum sticky-act">⚙️</th>
            </tr>
            <tr>
              <th class="derived" title="Derivat din ID Seller">Canal ƒ</th>
              <th class="derived" title="Derivat din ID Seller">Moneda ƒ</th>
              <th class="derived" title="Derivat din ID Seller">Rezidenta ƒ</th>
              <th class="num">ID Seller</th>
              <th class="num">ID Client</th>
              <th class="num">ID Supplier</th>
              <th class="num">ID FP</th>
              <th>Cumparator</th>
              <th>CIF cumparator</th>
              <th>Cont bancar seller</th>
              <th>Furnizor</th>
              <th class="num">CIF furnizor</th>
              <th>Document</th>
              <th class="key">Tip factura</th>
              <th>Serie/numar factura</th>
              <th class="num">Valoare fara TVA</th>
              <th class="num">Valoare TVA</th>
              <th class="num">Cota TVA</th>
              <th class="num">Valoare cu TVA</th>
              <th>Cod taxa SAP</th>
              <th class="num">Balanta factura</th>
              <th class="key">Data emitere</th>
              <th>Data scadenta</th>
              <th>Serie si numar FP</th>
              <th>Data payout</th>
              <th>MKTP Finance</th>
              <th class="sticky-act">Acțiuni</th>
            </tr>
          </thead>
          <tbody id="rawBody"></tbody>
        </table>
      </div>
      <div class="pagination">
        <button id="prevPage">‹ Anterior</button>
        <span id="pageInfo"></span>
        <button id="nextPage">Următor ›</button>
        <select id="pageSize">
          <option value="25">25/pag</option>
          <option value="50" selected>50/pag</option>
          <option value="100">100/pag</option>
          <option value="250">250/pag</option>
        </select>
      </div>
    </div>
  `;

  let page = 0;
  let PAGE_SIZE = 50;

  const tableView = QcxTable.create({body:'rawBody',key:'rawData',title:'Date eMag',identify:r=>r.id,currency:r=>mpById[Number(r.IDSeller)]?.Moneda || '',onChange:()=>{page=0;PAGE_SIZE=tableView.getPageSize();$('#pageSize').value=String(PAGE_SIZE);renderRows();},columns:[
    {key:'Canal',get:r=>mpById[Number(r.IDSeller)]?.Marketplace || `(ID ${r.IDSeller})`},
    {key:'Moneda',get:r=>mpById[Number(r.IDSeller)]?.Moneda || ''},
    {key:'Rezidenta',get:r=>mpById[Number(r.IDSeller)]?.AF || ''},
    ...['IDSeller','IDClient','IDSupplier','IDFP','Seller','CIFSeller','ContBancar','EntitateDante','CIFDante','Document','TipFactura','SerieNumar'].map(key=>({key})),
    {key:'ValoareFaraTVA',type:'number',total:'sum',currency:true},
    {key:'ValoareTVA',type:'number',total:'sum',currency:true},
    {key:'CotaTVA',type:'number'}, {key:'ValoareCuTVA',type:'number',total:'sum',currency:true},
    {key:'CodTaxaSAP'}, {key:'BalantaFactura',type:'number',currency:true},
    {key:'DataEmitere',type:'date'}, {key:'DataScadenta',type:'date'},
    {key:'SerieNumarFP'}, {key:'DataPayout',type:'date'}, {key:'MKTPFinance'}
  ]});
  PAGE_SIZE = tableView.getPageSize();
  $('#pageSize').value = String(PAGE_SIZE);

  const renderRows = () => {
    const f = ($('#rawFilter').value || '').toLowerCase();
    const cf = $('#canalFilter').value;
    let filtered = data.filter(r => {
      const mp = mpById[Number(r.IDSeller)];
      const canal = mp ? mp.Marketplace : `(ID ${r.IDSeller})`;
      if (cf && canal !== cf) return false;
      if (!f) return true;
      const haystack = [...Object.values(r), canal, mp?.Moneda, mp?.AF];
      return haystack.some(v => String(v||'').toLowerCase().includes(f));
    });
    filtered = tableView.apply(filtered);
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (page >= totalPages) page = totalPages - 1;
    if (page < 0) page = 0;
    const slice = filtered.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE);

    $('#rawBody').innerHTML = slice.map(r => {
      const mp = mpById[Number(r.IDSeller)];
      const canal = mp ? mp.Marketplace : '';
      const moneda = mp ? mp.Moneda : '';
      const rezidenta = mp ? mp.AF : '';
      const t = (v) => v === null || v === undefined || v === '' ? '' : ` title="${escAttr(v)}"`;
      const noMp = !mp ? ' class="row-noMp"' : '';
      return `
      <tr data-id="${r.id}"${noMp}>
        <td class="derived"${t(canal)}>${esc(canal) || '<span class="missing">⚠️ ID necunoscut</span>'}</td>
        <td class="derived"${t(moneda)}>${esc(moneda)}</td>
        <td class="derived"${t(rezidenta)}>${esc(rezidenta)}</td>
        <td class="num"${t(r.IDSeller)}>${esc(r.IDSeller)}</td>
        <td class="num"${t(r.IDClient)}>${esc(r.IDClient)}</td>
        <td class="num"${t(r.IDSupplier)}>${esc(r.IDSupplier)}</td>
        <td class="num"${t(r.IDFP)}>${esc(r.IDFP)}</td>
        <td${t(r.Seller)}>${esc(r.Seller)}</td>
        <td${t(r.CIFSeller)}>${esc(r.CIFSeller)}</td>
        <td${t(r.ContBancar)}>${esc(r.ContBancar)}</td>
        <td${t(r.EntitateDante)}>${esc(r.EntitateDante)}</td>
        <td class="num"${t(r.CIFDante)}>${esc(r.CIFDante)}</td>
        <td${t(r.Document)}>${esc(r.Document)}</td>
        <td class="key"${t(r.TipFactura)}><b>${esc(r.TipFactura)}</b></td>
        <td${t(r.SerieNumar)}>${esc(r.SerieNumar)}</td>
        <td class="num"${t(r.ValoareFaraTVA)}>${fmtNum(r.ValoareFaraTVA)}</td>
        <td class="num"${t(r.ValoareTVA)}>${fmtNum(r.ValoareTVA)}</td>
        <td class="num"${t(r.CotaTVA)}>${esc(r.CotaTVA)}</td>
        <td class="num"${t(r.ValoareCuTVA)}>${fmtNum(r.ValoareCuTVA)}</td>
        <td${t(r.CodTaxaSAP)}>${esc(r.CodTaxaSAP)}</td>
        <td class="num"${t(r.BalantaFactura)}>${fmtNum(r.BalantaFactura)}</td>
        <td class="key"${t(r.DataEmitere)}>${fmtDate(r.DataEmitere)}</td>
        <td${t(r.DataScadenta)}>${fmtDate(r.DataScadenta)}</td>
        <td${t(r.SerieNumarFP)}>${esc(r.SerieNumarFP)}</td>
        <td${t(r.DataPayout)}>${fmtDate(r.DataPayout)}</td>
        <td${t(r.MKTPFinance)}>${esc(r.MKTPFinance)}</td>
        <td class="sticky-act">
          <button class="btn-icon" data-act="edit" title="Editează">✏️</button>
          <button class="btn-icon" data-act="duplicate" title="Duplică">📋</button>
          <button class="btn-icon" data-act="delete" title="Șterge">🗑️</button>
        </td>
      </tr>
    `;
    }).join('') || '<tr><td colspan="27" class="empty">Niciun rezultat. Apasă "Adaugă factură" sau importă un Excel.</td></tr>';

    tableView.rendered(slice);
    $('#prevPage').disabled = page === 0;
    $('#nextPage').disabled = page >= totalPages - 1;
    $('#pageInfo').textContent = `Pagina ${page + 1} / ${totalPages}`;
    $('#rawCount').textContent = `${total} ${total === 1 ? 'rezultat' : 'rezultate'}${cf||f ? ` (din ${data.length})` : ''}`;

    $$('#rawBody [data-act]').forEach(btn => {
      btn.onclick = async () => {
        const id = parseInt(btn.closest('tr').dataset.id, 10);
        const item = data.find(r => r.id === id);
        const act = btn.dataset.act;
        if (act === 'edit') openRawEditor(item, false);
        else if (act === 'duplicate') {
          const copy = { ...item }; delete copy.id;
          openRawEditor(copy, true);
        }
        else if (act === 'delete') {
          if (!await confirmDialog(`Sigur ștergi factura <b>${esc(item.SerieNumar||item.TipFactura)}</b>?`)) return;
          await dbDelete('rawData', id);
          toast('Factură ștearsă', 'success');
          renderRawData(root);
        }
      };
    });
  };
  renderRows();

  $('#rawFilter').oninput = () => { page = 0; renderRows(); };
  $('#canalFilter').onchange = () => { page = 0; renderRows(); };
  $('#prevPage').onclick = () => { page--; renderRows(); };
  $('#nextPage').onclick = () => { page++; renderRows(); };
  $('#pageSize').onchange = (e) => { PAGE_SIZE = parseInt(e.target.value, 10); tableView.setPageSize(PAGE_SIZE); page = 0; renderRows(); };
  $('#uploadBtn').onclick = () => navigate('upload');
  $('#rawClear').onclick = async () => {
    if (!await confirmDialog('Sigur ștergi <b>toate</b> facturile? Această acțiune nu poate fi anulată.')) return;
    await dbClear('rawData');
    toast('Toate datele au fost șterse', 'success');
    renderRawData(root);
  };
  $('#addBtn').onclick = () => openRawEditor({
    IDSeller: marketplaces[0]?.IdSeller || '',
    DataEmitere: new Date().toISOString().slice(0,10),
    MKTPFinance: 'Da'
  }, true);

  function openRawEditor(values, isNew) {
    const fieldsWithCanal = RAW_FIELDS.map(f =>
      f.name === 'IDSeller' ? { ...f, options: canalOptions } : f
    );
    openModal({
      title: isNew ? '➕ Adaugă factură eMag' : `✏️ Editare factură: ${values.SerieNumar||values.TipFactura}`,
      fields: fieldsWithCanal,
      values,
      size: 'xl',
      deleteBtn: !isNew,
      onDelete: async () => {
        await dbDelete('rawData', values.id);
        toast('Factură ștearsă', 'success');
        renderRawData(root);
      },
      onSave: async (formData) => {
        // Coerce IDSeller back to number
        if (formData.IDSeller) formData.IDSeller = Number(formData.IDSeller);
        if (!isNew) formData.id = values.id;
        await dbPut('rawData', formData);
        toast(isNew ? 'Factură adăugată' : 'Factură salvată', 'success');
        renderRawData(root);
      }
    });
  }
}

// ---- DataSet ----
async function renderDataSet(root) {
  const data = await dbGetAll('dataSet');
  const rawData = await dbGetAll('rawData');
  const rawCount = rawData.length;
  const cursCount = await dbCount('cursValutar');
  const canale = [...new Set(data.map(r => r.Canal))].sort();

  // Perioadele disponibile din datele raw (dupa data emiterii)
  const periodCounts = {};
  let rawFaraData = 0;
  rawData.forEach(r => {
    const k = periodKeyOf(r.DataEmitere);
    if (k) periodCounts[k] = (periodCounts[k] || 0) + 1;
    else rawFaraData++;
  });
  const allPeriods = Object.keys(periodCounts).sort();

  root.innerHTML = `
    <div class="page-head">
      <h1>DataSet (procesat)</h1>
      <p class="subtitle">${data.length} înregistrări contabile generate din ${rawCount} facturi raw.</p>
    </div>
    <div class="card">
      <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:.35rem;font-weight:600;cursor:pointer">
          <input type="checkbox" id="perAll" checked/> Toate lunile
        </label>
        <div id="perChips" style="display:flex;gap:.35rem;flex-wrap:wrap">
          ${allPeriods.map(p => `
            <label class="badge" style="display:flex;align-items:center;gap:.3rem;cursor:pointer;opacity:.55" title="${periodCounts[p]} facturi raw">
              <input type="checkbox" class="perChk" value="${p}" disabled/> ${p} <small>(${periodCounts[p]})</small>
            </label>`).join('')}
        </div>
      </div>
      ${rawFaraData > 0 ? `<div class="hint" style="margin-top:.35rem">⚠️ ${rawFaraData} facturi raw fără dată validă — se procesează doar la „Toate lunile".</div>` : ''}
      <div style="display:flex;gap:.5rem;align-items:center;margin-top:.6rem;flex-wrap:wrap">
        <button class="btn btn-primary" id="processBtn">⚡ (Re)procesează datele</button>
        <button class="btn" id="exportBtn" ${data.length === 0 ? 'disabled' : ''}>📥 Export Excel</button>
        <span class="hint">Necesită: ${rawCount} facturi raw + ${cursCount} cursuri.</span>
      </div>
      <div id="processResult"></div>
    </div>

    ${data.length > 0 ? `
    <div class="card" style="margin-top:1rem">
      <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;flex-wrap:wrap">
        <input type="text" class="filter" placeholder="🔍 Caută..." id="dsFilter" style="flex:1;min-width:200px"/>
        <select id="dsCanal"><option value="">Toate canalele</option>${canale.map(c=>`<option>${c}</option>`).join('')}</select>
      </div>
      <div class="table-wrap">
        <table class="data-table compact">
          <thead><tr>
            <th>Canal</th><th>Flux</th><th>CodTipF</th><th>TipTranz.</th>
            <th>Articol</th><th>Cont T</th><th>Cont Tz</th>
            <th>Referință</th><th>Mon.</th><th class="num">Curs</th>
            <th class="num">Valoare</th><th class="num">Ron Val</th><th class="num">Ron TVA</th>
          </tr></thead>
          <tbody id="dsBody"></tbody>
        </table>
      </div>
      <div class="pagination">
        <button id="dsPrev">‹</button><span id="dsPageInfo"></span><button id="dsNext">›</button><select id="dsPageSize" aria-label="Rânduri pe pagină"><option value="25">25/pag</option><option value="50" selected>50/pag</option><option value="100">100/pag</option><option value="250">250/pag</option></select>
      </div>
      <div class="totals" id="dsTotals"></div>
    </div>` : ''}
  `;

  // ---- Selector perioade: "Toate lunile" vs selectie individuala ----
  const perAll = $('#perAll');
  const chips = $$('.perChk');
  const syncChips = () => {
    chips.forEach(c => {
      c.disabled = perAll.checked;
      if (perAll.checked) c.checked = false;
      c.closest('label').style.opacity = perAll.checked ? '.55' : (c.checked ? '1' : '.75');
    });
  };
  perAll.onchange = syncChips;
  chips.forEach(c => c.onchange = () => {
    c.closest('label').style.opacity = c.checked ? '1' : '.75';
  });
  syncChips();

  const selectedPeriods = () => {
    if (perAll.checked) return null;
    return new Set(chips.filter(c => c.checked).map(c => c.value));
  };

  $('#processBtn').onclick = async () => {
    if (rawCount === 0) { toast('Nu există date raw. Încarcă întâi un Excel.', 'error'); return; }
    const periods = selectedPeriods();
    if (periods !== null && periods.size === 0) {
      toast('Selectează cel puțin o lună sau bifează „Toate lunile".', 'error');
      return;
    }
    if (cursCount === 0) {
      if (!await confirmDialog('Nu există cursuri valutare în baza de date.<br/>Continuăm doar cu tranzacții RON? Toate cele non-RON vor genera erori.', 'Curs valutar lipsă')) return;
    }
    if (periods !== null) {
      const lst = [...periods].sort().join(', ');
      if (!await confirmDialog(`Se șterg și se regenerează doar lunile: <b>${esc(lst)}</b>.<br/><span class="hint">Restul DataSet-ului rămâne neatins.</span>`, 'Reprocesare selectivă')) return;
    }
    setBusy(true, 'Se procesează...');
    let result;
    try {
      result = await processAllRawData((cur, tot) => {
        $('#busy-msg').textContent = `Se procesează ${cur}/${tot}...`;
      }, periods);
      console.log('[DataSet] Procesare:', result);
    } catch (e) {
      setBusy(false);
      console.error('[DataSet] Eroare procesare:', e);
      toast('Eroare procesare: ' + e.message, 'error');
      return;
    }
    setBusy(false);

    // Re-render to refresh stats/table FIRST, then inject result message
    await renderDataSet(root);

    const resultDiv = $('#processResult');
    if (resultDiv && result) {
      const errHtml = result.errors.length > 0 ? `
        <details ${result.processed === 0 ? 'open' : ''} style="margin-top:.5rem">
          <summary class="${result.processed === 0 ? 'error' : 'warn-summary'}" style="cursor:pointer;font-weight:500">
            ⚠️ ${result.errors.length} ${result.errors.length === 1 ? 'eroare' : 'erori'} (click pentru detalii)
          </summary>
          <div class="table-wrap" style="margin-top:.5rem;max-height:300px">
            <table class="data-table compact">
              <thead><tr>
                <th>Canal</th><th>ID Seller</th><th>CodTipF</th><th>Serie/Nr</th><th>Eroare</th>
              </tr></thead>
              <tbody>
                ${result.errors.slice(0,200).map(e => `<tr>
                  <td>${esc(e.Canal||'')}</td>
                  <td class="num">${esc(e.IDSeller||'')}</td>
                  <td><b>${esc(e.CodTipFactura||'')}</b></td>
                  <td>${esc(e.SerieNumar||'')}</td>
                  <td style="color:var(--error)">${esc(e._error)}</td>
                </tr>`).join('')}
                ${result.errors.length > 200 ? `<tr><td colspan="5" class="empty">... și încă ${result.errors.length - 200} erori</td></tr>` : ''}
              </tbody>
            </table>
          </div>
        </details>
      ` : '';
      const cls = result.processed === 0 ? 'error' : 'success';
      const icon = result.processed === 0 ? '❌' : '✅';
      const scope = result.selective ? ' <small>(reprocesare selectivă)</small>' : '';
      resultDiv.innerHTML = `<div class="${cls}">${icon} Procesate <b>${result.processed}</b> din <b>${result.total}</b> înregistrări${result.errors.length > 0 ? ` cu <b>${result.errors.length}</b> erori` : ''}${scope}.</div>${invoiceTypesNotice(result.invoiceTypes)}${errHtml}`;
    }
    toast(
      result.processed === 0
        ? `0 procesate · ${result.errors.length} erori`
        : `${result.processed} procesate · ${result.errors.length} erori`,
      result.processed === 0 ? 'error' : (result.errors.length === 0 ? 'success' : 'info')
    );
  };

  if (data.length === 0) return;

  let page = 0; let PAGE_SIZE = 50;
  const tableView = QcxTable.create({body:'dsBody',key:'dataSet',title:'DataSet',identify:r=>r.id,currency:r=>r.Moneda,onChange:()=>{page=0;PAGE_SIZE=tableView.getPageSize();$('#dsPageSize').value=String(PAGE_SIZE);renderRows();},columns:[
    ...['Canal','Flux','CodTipFactura','TipTranzactie','Articol','ContTert','ContTz','Referinta','Moneda'].map(key=>({key})),
    {key:'CursValutar',type:'number',decimals:4}, {key:'Valoare',type:'number',total:'sum',currency:true},
    {key:'Ron_Val',type:'number',total:'sum'}, {key:'Ron_TVA',type:'number',total:'sum'}
  ]});
  PAGE_SIZE = tableView.getPageSize();
  $('#dsPageSize').value = String(PAGE_SIZE);
  const renderRows = () => {
    const f = ($('#dsFilter').value||'').toLowerCase();
    const cf = $('#dsCanal').value;
    let filtered = data.filter(r => {
      if (cf && r.Canal !== cf) return false;
      if (!f) return true;
      return Object.values(r).some(v => String(v||'').toLowerCase().includes(f));
    });
    filtered = tableView.apply(filtered);
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    if (page >= totalPages) page = totalPages - 1;
    if (page < 0) page = 0;
    const slice = filtered.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE);
    $('#dsBody').innerHTML = slice.map(r => `
      <tr>
        <td>${r.Canal}</td>
        <td><span class="badge badge-${r.Flux==='Cumparare | Intrare'?'mkp':'fbe'}">${r.Flux}</span></td>
        <td><b>${r.CodTipFactura}</b></td>
        <td>${r.TipTranzactie}</td>
        <td>${r.Articol}</td>
        <td>${r.ContTert}</td><td>${r.ContTz}</td>
        <td><small>${r.Referinta}</small></td>
        <td>${r.Moneda}</td>
        <td class="num">${fmtNum(r.CursValutar, 4)}</td>
        <td class="num">${fmtNum(r.Valoare)}</td>
        <td class="num"><b>${fmtNum(r.Ron_Val)}</b></td>
        <td class="num">${fmtNum(r.Ron_TVA)}</td>
      </tr>
    `).join('');
    tableView.rendered(slice);
    $('#dsPrev').disabled = page === 0;
    $('#dsNext').disabled = page >= totalPages - 1;
    $('#dsPageInfo').textContent = `Pagina ${page+1} / ${totalPages} (${filtered.length} rânduri)`;
    const tVal = filtered.reduce((a, r) => a + (r.Ron_Val || 0), 0);
    const tTva = filtered.reduce((a, r) => a + (r.Ron_TVA || 0), 0);
    $('#dsTotals').innerHTML = `
      <span>Total Ron Val: <b>${fmtNum(tVal)}</b></span>
      <span>Total Ron TVA: <b>${fmtNum(tTva)}</b></span>
      <span>Total: <b>${fmtNum(tVal + tTva)}</b></span>
    `;
  };
  renderRows();
  $('#dsFilter').oninput = () => { page = 0; renderRows(); };
  $('#dsCanal').onchange = () => { page = 0; renderRows(); };
  $('#dsPrev').onclick = () => { page--; renderRows(); };
  $('#dsNext').onclick = () => { page++; renderRows(); };
  $('#dsPageSize').onchange = e => { PAGE_SIZE = Number(e.target.value); tableView.setPageSize(PAGE_SIZE); page = 0; renderRows(); };

  $('#exportBtn').onclick = () => exportDataSetToExcel(data);
}

function exportDataSetToExcel(data) {
  const headers = ['Canal','Flux','TipOperatiune','CodTipFactura','TipTranzactie','TipDocumentTVA',
    'MKP/FBE','Cost Canal','Natura economica','Cont Tert','Cont Tz','D = C',
    'Articol','Serie','Numar','Data','Referinta','An','Luna','Zi',
    'Valoare','CotaTVA','Valoare TVA','Moneda','CursValutar','Demultiplicator',
    'Ron_Val','Ron_TVA','TipOpsTVA','TipTVA','ValCh','TipTaxare'];
  const rows = data.map(r => [
    r.Canal, r.Flux, r.TipOperatiune, r.CodTipFactura, r.TipTranzactie, r.TipDocumentTVA,
    r.MkpFbe, r.CostCanal, r.NaturaEconomica, r.ContTert, r.ContTz, r.FormulaContabila,
    r.Articol, r.Serie, r.Numar, r.DataEmitere, r.Referinta, r.An, r.Luna, r.Zi,
    r.Valoare, r.CotaTVA, r.ValoareTVA, r.Moneda, r.CursValutar, r.Demultiplicator,
    r.Ron_Val, r.Ron_TVA, r.TipOpsTVA, r.TipTVA, r.ValCh, r.TipTaxare
  ]);
  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'DataSet');
  XLSX.writeFile(wb, `DataSet_${new Date().toISOString().slice(0,10)}.xlsx`);
}

// ---- Centralizator (sintetic) — layout 1:1 cu raportul Excel ----
async function renderCentralizator(root) {
  const data = await dbGetAll('dataSet');
  const marketplaces = await dbGetAll('marketplaces');
  const mpByCanal = {};
  marketplaces.forEach(m => mpByCanal[m.Marketplace] = m);

  if (data.length === 0) {
    root.innerHTML = `
      <div class="page-head"><h1>Centralizator</h1></div>
      <div class="card empty-state" style="text-align:center;padding:3rem">
        <h3 style="margin-bottom:.5rem">📊 Niciun decont disponibil</h3>
        <p style="color:var(--text-muted);margin-bottom:1rem">Procesează întâi DataSet-ul pentru a genera centralizatorul.</p>
        <button class="btn btn-primary" onclick="navigate('dataset')">Mergi la DataSet →</button>
      </div>`;
    return;
  }

  const ani = [...new Set(data.map(r => r.An))].sort((a,b)=>b-a);
  const canale = [...new Set(data.map(r => r.Canal))].sort();
  let curAn = ani[0];
  let curCanal = canale[0];
  const luniFor = (an, canal) => [...new Set(data.filter(r => r.An===an && r.Canal===canal).map(r=>r.Luna))].sort((a,b)=>a-b);
  let lune = luniFor(curAn, curCanal);
  let curLuna = lune[0];

  const renderShell = () => {
    root.innerHTML = `
      <div class="centralizator">
        <div class="cz-banner-row">
          <div class="cz-banner-tools">
            <button class="btn-icon-tool" id="czRefresh" title="Refresh date">🔄</button>
            <button class="btn-icon-tool" id="czPrint" title="Export PDF">🖨️</button>
          </div>
          <div class="cz-anexe-banner" id="czCentralizatorTitle">—</div>
        </div>
        <div class="cz-topbar">
          <div class="cz-titles">
            <div class="cz-title-l">
              <h1>Quasar Comex SRL</h1>
              <div class="cz-mkp-label">Seller</div>
            </div>
            <div class="cz-title-r">
              <h1 id="czPartner">—</h1>
              <div class="cz-mkp-label">Marketplace</div>
            </div>
          </div>
        </div>

        <!-- Title bar (mirrors Decont) -->
        <div class="decont-doc-title">
          <h2>CENTRALIZATOR DOCUMENTE INTRARE</h2>
          <div class="decont-doc-period" id="czPeriod">—</div>
        </div>

        <div class="cz-filters">
          <div class="cz-num-deconturi"><span class="lbl">Nr. deconturi:</span> <span class="val" id="czNumDeconturi">0</span></div>
          <div class="cz-filter-inline">
            <span class="cz-fil-lbl">An</span>
            <span class="cz-fil-val">${selectHtml('czAn', ani, curAn)}</span>
            <span class="cz-fil-lbl">Luna</span>
            <span class="cz-fil-val" id="czLunaCell">${selectHtml('czLuna', lune, curLuna)}</span>
            <span class="cz-fil-lbl">Canal</span>
            <span class="cz-fil-val">${selectHtml('czCanal', canale, curCanal)}</span>
          </div>
          <div class="cz-doc-ref" id="czDocRef">—</div>
        </div>

        <div class="cz-pivot-1-wrap">
          <table class="cz-pivot cz-pivot-1">
            <thead>
              <tr>
                <th>Nr.Crt.</th><th>Problematica</th><th>TipTaxare</th><th>Tip Document TVA</th><th>Flux</th>
                <th class="num">Valoare RON</th><th class="num">TVA RON</th><th class="num">Total RON</th>
                <th class="cz-anexa-col">Anexă</th>
                <th class="cz-decont-col"></th>
              </tr>
            </thead>
            <tbody id="czPivot1Body"></tbody>
            <tfoot id="czPivot1Foot"></tfoot>
          </table>
        </div>

        <div class="cz-section-divider"></div>

        <div class="cz-pivot-2-wrap">
          <div class="cz-currency-tag">- <span id="czCurrencyLabel">RON</span> -</div>
          <table class="cz-pivot cz-pivot-2">
            <thead>
              <tr>
                <th>Nr.Crt.</th><th>Flux</th><th>TipOpsTVA</th><th>TipTaxare</th><th>Tip Document TVA</th>
                <th class="num">Cont Tert</th><th class="num">%TVA</th>
                <th>Natura economica</th><th>Articol</th><th>TipTranzactie</th>
                <th class="num">NrDoc</th><th class="num">Valoare RON</th><th class="num">TVA RON</th><th class="num">Total RON</th>
              </tr>
            </thead>
            <tbody id="czPivot2Body"></tbody>
            <tfoot id="czPivot2Foot"></tfoot>
          </table>
        </div>

        <div class="cz-footer">
          <div class="cz-foot-cell"><span class="lbl">Întocmit</span><span class="cz-issuer-name">smartBIZ Agent</span><span class="cz-line"></span></div>
          <div class="cz-foot-cell"><span class="lbl">Verificat</span><span class="cz-line"></span></div>
          <div class="cz-foot-issuer">EcR Deconturi · ${new Date().toLocaleDateString('ro-RO')}</div>
        </div>
      </div>
    `;
  };

  const update = () => {
    const filtered = data.filter(r => r.An === curAn && r.Luna === curLuna && r.Canal === curCanal);
    const mp = mpByCanal[curCanal];
    $('#czPartner').textContent = mp ? mp.Parteneri : curCanal;
    $('#czCurrencyLabel').textContent = 'RON';
    const docRefId = `DCMK${curAn}.${pad2(curLuna)}-${(mp?.AF || 'XX')}_01`;
    $('#czDocRef').textContent = docRefId;
    $('#czCentralizatorTitle').textContent = `${curAn}_${pad2(curLuna)} ${curCanal} Centralizator`;
    $('#czPeriod').textContent = `în perioada: luna ${curLuna} an: ${curAn}`;

    if (filtered.length === 0) {
      $('#czPivot1Body').innerHTML = '<tr><td colspan="10" class="empty">Niciun rezultat pentru filtrele selectate.</td></tr>';
      $('#czPivot1Foot').innerHTML = '';
      $('#czPivot2Body').innerHTML = '<tr><td colspan="14" class="empty">Niciun rezultat.</td></tr>';
      $('#czPivot2Foot').innerHTML = '';
      $('#czNumDeconturi').textContent = '0';
      return;
    }

    // ===== PIVOT 1 =====
    const p1Map = new Map();
    filtered.forEach(r => {
      const key = `${r.TipOpsTVA}|${r.TipTaxare}|${r.TipDocumentTVA}|${r.Flux}`;
      if (!p1Map.has(key)) p1Map.set(key, {
        Problematica: r.TipOpsTVA || '',
        TipTaxare: r.TipTaxare || '',
        TipDocumentTVA: r.TipDocumentTVA || '',
        Flux: r.Flux || '',
        sumVal: 0, sumTVA: 0
      });
      const e = p1Map.get(key);
      e.sumVal += (r.Ron_Val || 0);
      e.sumTVA += (r.Ron_TVA || 0);
    });
    const pivot1 = [...p1Map.values()].sort((a,b) =>
      (a.Flux === b.Flux ? 0 : a.Flux.startsWith('Cumparare') ? -1 : 1) ||
      a.Problematica.localeCompare(b.Problematica) ||
      a.TipTaxare.localeCompare(b.TipTaxare) ||
      a.TipDocumentTVA.localeCompare(b.TipDocumentTVA)
    );

    let totalP1Val = 0, totalP1TVA = 0, totalP1 = 0;
    $('#czPivot1Body').innerHTML = pivot1.map((r, i) => {
      // Global sequential Nr.Crt (1, 2, 3, ...) AND global anexa idx
      const idx = i + 1;
      const totalRow = r.sumVal + r.sumTVA;
      totalP1Val += r.sumVal;
      totalP1TVA += r.sumTVA;
      totalP1 += totalRow;
      const anexa = `${curAn}_${pad2(curLuna)} ${curCanal} Decont #C${pad2(idx)}`;
      return `<tr>
        <td class="num">${idx}</td>
        <td>${esc(r.Problematica)}</td>
        <td>${esc(r.TipTaxare)}</td>
        <td>${esc(r.TipDocumentTVA)}</td>
        <td>${esc(r.Flux)}</td>
        <td class="num">${fmtNum(r.sumVal)}</td>
        <td class="num">${fmtNum(r.sumTVA)}</td>
        <td class="num"><b>${fmtNum(totalRow)}</b></td>
        <td class="cz-anexa-col">${esc(anexa)}</td>
        <td class="cz-decont-col"><button class="btn-decont" data-idx="${i}" title="Vezi decont detaliat">📋 Decont</button></td>
      </tr>`;
    }).join('');
    $('#czPivot1Foot').innerHTML = `<tr class="cz-total-yellow">
      <td colspan="5" class="cz-total-label"><b>Total general</b></td>
      <td class="num"><b>${fmtNum(totalP1Val)}</b></td>
      <td class="num"><b>${fmtNum(totalP1TVA)}</b></td>
      <td class="num"><b>${fmtNum(totalP1)}</b></td>
      <td></td>
      <td class="cz-decont-col"></td>
    </tr>`;

    // Bind Decont buttons - open Decont with this row's filters preset
    $$('.btn-decont', $('#czPivot1Body')).forEach(btn => {
      btn.onclick = () => {
        const i = parseInt(btn.dataset.idx, 10);
        const r = pivot1[i];
        _decontCtx = {
          an: curAn,
          luna: curLuna,
          canal: curCanal,
          flux: r.Flux,
          tipOpsTVA: r.Problematica,
          tipDocumentTVA: r.TipDocumentTVA,
          tipTaxare: r.TipTaxare,
          decontIdx: i + 1
        };
        navigate('decont');
      };
    });

    // Numar deconturi = numărul de rânduri din pivot 1 (anexe / deconturi)
    $('#czNumDeconturi').textContent = pivot1.length;

    // ===== PIVOT 2 =====
    const p2Map = new Map();
    filtered.forEach(r => {
      const key = [
        r.Flux, r.TipOpsTVA, r.TipTaxare, r.TipDocumentTVA,
        r.ContTert, r.CotaTVA, r.NaturaEconomica, r.Articol, r.TipTranzactie
      ].map(v => v ?? '').join('|');
      if (!p2Map.has(key)) p2Map.set(key, {
        Flux: r.Flux || '',
        TipOpsTVA: r.TipOpsTVA || '',
        TipTaxare: r.TipTaxare || '',
        TipDocumentTVA: r.TipDocumentTVA || '',
        ContTert: r.ContTert ?? '',
        CotaTVA: r.CotaTVA ?? 0,
        NaturaEconomica: r.NaturaEconomica || '',
        Articol: r.Articol || '',
        TipTranzactie: r.TipTranzactie || '',
        NrDoc: 0, sumVal: 0, sumTVA: 0
      });
      const e = p2Map.get(key);
      e.NrDoc++;
      e.sumVal += (r.Ron_Val || 0);
      e.sumTVA += (r.Ron_TVA || 0);
    });

    const pivot2 = [...p2Map.values()].sort((a,b) =>
      (a.Flux === b.Flux ? 0 : a.Flux.startsWith('Cumparare') ? -1 : 1) ||
      a.TipOpsTVA.localeCompare(b.TipOpsTVA) ||
      a.TipTaxare.localeCompare(b.TipTaxare) ||
      a.TipDocumentTVA.localeCompare(b.TipDocumentTVA) ||
      (Number(a.ContTert)||0) - (Number(b.ContTert)||0) ||
      (Number(a.CotaTVA)||0) - (Number(b.CotaTVA)||0) ||
      a.TipTranzactie.localeCompare(b.TipTranzactie)
    );

    // Render pivot 2 with collapsed repeated cells, subtotals per Flux, and grand total
    let prev = {};
    let groupSum = { NrDoc:0, sumVal:0, sumTVA:0, total:0 };
    let grand = { NrDoc:0, sumVal:0, sumTVA:0, total:0 };
    let nrCrt = 0;
    let lastFlux = null;
    let html = '';

    const flushSubtotal = (flux) => {
      if (flux === null) return;
      html += `<tr class="cz-subtotal">
        <td colspan="2"><b>${esc(flux)} Total</b></td>
        <td colspan="8"></td>
        <td class="num"><b>${groupSum.NrDoc}</b></td>
        <td class="num"><b>${fmtNum(groupSum.sumVal)}</b></td>
        <td class="num"><b>${fmtNum(groupSum.sumTVA)}</b></td>
        <td class="num"><b>${fmtNum(groupSum.total)}</b></td>
      </tr>`;
    };

    pivot2.forEach((r, i) => {
      // New flux group?
      if (lastFlux !== null && r.Flux !== lastFlux) {
        flushSubtotal(lastFlux);
        groupSum = { NrDoc:0, sumVal:0, sumTVA:0, total:0 };
        prev = {}; // reset collapse for new flux
        nrCrt = 0;
      }
      lastFlux = r.Flux;
      nrCrt++;

      const total = r.sumVal + r.sumTVA;
      groupSum.NrDoc += r.NrDoc; groupSum.sumVal += r.sumVal; groupSum.sumTVA += r.sumTVA; groupSum.total += total;
      grand.NrDoc += r.NrDoc; grand.sumVal += r.sumVal; grand.sumTVA += r.sumTVA; grand.total += total;

      // Cells that should "collapse" if same as previous row in same flux:
      const collapse = (key) => {
        const v = r[key];
        if (prev[key] === v) return '';
        prev[key] = v;
        return v;
      };
      const fluxCell = collapse('Flux');
      const tipOpsCell = collapse('TipOpsTVA');
      const tipTaxCell = collapse('TipTaxare');
      const tipDocCell = collapse('TipDocumentTVA');
      const contTertCell = (() => {
        if (prev._contTertKey === `${r.TipDocumentTVA}|${r.ContTert}`) return '';
        prev._contTertKey = `${r.TipDocumentTVA}|${r.ContTert}`;
        return r.ContTert;
      })();
      const cotaCell = (() => {
        if (prev._cotaKey === `${r.TipDocumentTVA}|${r.ContTert}|${r.CotaTVA}`) return '';
        prev._cotaKey = `${r.TipDocumentTVA}|${r.ContTert}|${r.CotaTVA}`;
        return r.CotaTVA;
      })();

      html += `<tr>
        <td class="num">${nrCrt}</td>
        <td>${esc(fluxCell)}</td>
        <td>${esc(tipOpsCell)}</td>
        <td>${esc(tipTaxCell)}</td>
        <td>${esc(tipDocCell)}</td>
        <td class="num">${esc(contTertCell)}</td>
        <td class="num">${esc(cotaCell)}</td>
        <td>${esc(r.NaturaEconomica)}</td>
        <td>${esc(r.Articol)}</td>
        <td>${esc(r.TipTranzactie)}</td>
        <td class="num">${r.NrDoc}</td>
        <td class="num">${fmtNum(r.sumVal)}</td>
        <td class="num">${fmtNum(r.sumTVA)}</td>
        <td class="num"><b>${fmtNum(total)}</b></td>
      </tr>`;
    });
    // Flush last subtotal
    flushSubtotal(lastFlux);

    $('#czPivot2Body').innerHTML = html;
    $('#czPivot2Foot').innerHTML = `<tr class="cz-grand-total">
      <td colspan="10"><b>Grand Total</b></td>
      <td class="num"><b>${grand.NrDoc.toFixed(2)}</b></td>
      <td class="num"><b>${fmtNum(grand.sumVal)}</b></td>
      <td class="num"><b>${fmtNum(grand.sumTVA)}</b></td>
      <td class="num"><b>${fmtNum(grand.total)}</b></td>
    </tr>`;
  };

  function selectHtml(id, options, current) {
    return `<select id="${id}">${options.map(o => `<option ${o == current ? 'selected':''}>${o}</option>`).join('')}</select>`;
  }

  renderShell();

  $('#czAn').onchange = (e) => {
    curAn = parseInt(e.target.value, 10);
    lune = luniFor(curAn, curCanal);
    if (!lune.includes(curLuna)) curLuna = lune[0];
    $('#czLunaCell').innerHTML = selectHtml('czLuna', lune, curLuna);
    $('#czLuna').onchange = (ev) => { curLuna = parseInt(ev.target.value, 10); update(); };
    update();
  };
  $('#czLuna').onchange = (e) => { curLuna = parseInt(e.target.value, 10); update(); };
  $('#czCanal').onchange = (e) => {
    curCanal = e.target.value;
    lune = luniFor(curAn, curCanal);
    if (!lune.includes(curLuna)) curLuna = lune[0];
    $('#czLunaCell').innerHTML = selectHtml('czLuna', lune, curLuna);
    $('#czLuna').onchange = (ev) => { curLuna = parseInt(ev.target.value, 10); update(); };
    update();
  };
  $('#czRefresh').onclick = update;
  $('#czPrint').onclick = () => printCentralizator(curAn, curLuna, curCanal);
  update();
}

function printCentralizator(an, luna, canal) {
  const reportName = `Centralizator ${an}.${pad2(luna)} · ${canal}`;

  // Inject/update print CSS for @page footer with dynamic period
  let style = document.getElementById('cz-print-dyn');
  if (!style) {
    style = document.createElement('style');
    style.id = 'cz-print-dyn';
    document.head.appendChild(style);
  }
  // Escape any chars that would break CSS string
  const safeName = reportName.replace(/"/g, '\\"');
  style.textContent = `
    @media print {
      @page {
        margin: 12mm 8mm 18mm 8mm;
      }
      @page {
        @bottom-left {
          content: "${safeName}";
          font-family: 'Segoe UI', Arial, sans-serif;
          font-size: 8pt;
          color: #6b7280;
        }
        @bottom-right {
          content: "Pagina " counter(page) " din " counter(pages);
          font-family: 'Segoe UI', Arial, sans-serif;
          font-size: 8pt;
          color: #6b7280;
        }
      }
    }
  `;

  // Temporarily set document title (used as default PDF filename)
  const oldTitle = document.title;
  document.title = reportName.replace(/\s+/g, '_').replace(/[^\w\.\-_]/g, '');
  document.body.classList.add('cz-printing');

  // Give browser a tick to apply CSS, then trigger print
  setTimeout(() => {
    window.print();
    setTimeout(() => {
      document.title = oldTitle;
      document.body.classList.remove('cz-printing');
    }, 500);
  }, 100);
}

// ====================== DECONT (analytical drill-down for one Pivot 1 row) ======================
async function renderDecont(root) {
  // If no context, build defaults from data
  const ds = await dbGetAll('dataSet');
  const marketplaces = await dbGetAll('marketplaces');
  const mpById = {}; marketplaces.forEach(m => { mpById[m.Marketplace] = m; });

  if (ds.length === 0) {
    root.innerHTML = `
      <div class="page-head"><h1>Decont</h1><p class="subtitle">Raport analitic per linie din Centralizator Pivot 1.</p></div>
      <div class="card"><div class="empty">Nu există date procesate. Procesează DataSet-ul mai întâi.</div></div>`;
    return;
  }

  const ans = [...new Set(ds.map(r => r.An))].sort();
  const lunis = [...new Set(ds.map(r => r.Luna))].sort((a,b) => a-b);
  const canale = [...new Set(ds.map(r => r.Canal))].sort();

  // If no context yet, default to most recent + first valid combination present in data
  if (!_decontCtx) {
    const an = ans[ans.length-1];
    const lunisForAn = [...new Set(ds.filter(r => r.An===an).map(r=>r.Luna))].sort((a,b)=>a-b);
    const luna = lunisForAn[lunisForAn.length-1];
    const canalCandidates = [...new Set(ds.filter(r => r.An===an && r.Luna===luna).map(r=>r.Canal))].sort();
    const canal = canalCandidates[0];
    // Pick the first row matching An+Luna+Canal — guarantees a valid Flux/TipOpsTVA/... combo with rows
    const candidate = ds.find(r => r.An===an && r.Luna===luna && r.Canal===canal);
    _decontCtx = {
      an, luna, canal,
      flux: candidate?.Flux || '',
      tipOpsTVA: candidate?.TipOpsTVA || '',
      tipDocumentTVA: candidate?.TipDocumentTVA || '',
      tipTaxare: candidate?.TipTaxare || '',
      decontIdx: 1
    };
  }

  let curAn = _decontCtx.an;
  let curLuna = _decontCtx.luna;
  let curCanal = _decontCtx.canal;
  let curFlux = _decontCtx.flux;
  let curTipOpsTVA = _decontCtx.tipOpsTVA;
  let curTipDocumentTVA = _decontCtx.tipDocumentTVA;
  let curTipTaxare = _decontCtx.tipTaxare;

  // Helper to get partner info from canal — uses real master data fields (Tara, Localitate, CUI)
  function getPartner(canalName) {
    const mp = mpById[canalName];
    if (!mp) return { name: '—', cui: '—', country: '—', af: '—' };
    const country = [mp.Tara, mp.Localitate].filter(Boolean).join(', ') || '—';
    return {
      name: mp.Parteneri || '—',
      cui: mp.CUI || '—',
      af: mp.AF || '—',
      country
    };
  }

  root.innerHTML = `
    <div class="centralizator decont-page">
      <div class="cz-banner-row">
        <div class="cz-banner-tools">
          <button class="btn-icon-tool" id="dRefresh" title="Refresh">🔄</button>
          <button class="btn-icon-tool" id="dPrint" title="Export PDF">🖨️</button>
          <button class="btn-icon-tool" id="dExportNote" title="Export note contabile (Excel)" style="display:none">📥</button>
          <button class="btn-icon-tool" id="dBackToCz" title="Înapoi la Centralizator">↩️</button>
        </div>
        <div class="cz-anexe-banner" id="dAnexa">—</div>
      </div>

      <!-- Companies header -->
      <div class="cz-titles">
        <div class="cz-title-l">
          <h1>Quasar Comex SRL</h1>
          <small class="decont-co-meta">CUI: 4996264 &nbsp;·&nbsp; AF: RO<br/>Focsani, jud Vrancea</small>
        </div>
        <div class="cz-title-r">
          <h1 id="dPartner">—</h1>
          <small class="decont-co-meta" id="dPartnerMeta">—</small>
        </div>
      </div>

      <!-- Title -->
      <div class="decont-doc-title">
        <h2>DECONT OPERAȚIUNI DERULATE</h2>
        <div class="decont-doc-period" id="dPeriod">—</div>
      </div>

      <!-- Filters -->
      <div class="decont-filters cz-filters">
        <div class="cz-filter-inline">
          <span class="cz-fil-lbl">An</span>
          <span class="cz-fil-val"><select id="dAn">${ans.map(a=>`<option value="${a}"${a===curAn?' selected':''}>${a}</option>`).join('')}</select></span>
          <span class="cz-fil-lbl">Luna</span>
          <span class="cz-fil-val"><select id="dLuna">${lunis.map(l=>`<option value="${l}"${l===curLuna?' selected':''}>${l}</option>`).join('')}</select></span>
          <span class="cz-fil-lbl">Canal</span>
          <span class="cz-fil-val"><select id="dCanal">${canale.map(c=>`<option value="${esc(c)}"${c===curCanal?' selected':''}>${esc(c)}</option>`).join('')}</select></span>
        </div>
        <div class="cz-filter-inline">
          <span class="cz-fil-lbl">Flux</span>
          <span class="cz-fil-val"><select id="dFlux"></select></span>
          <span class="cz-fil-lbl">TipOpsTVA</span>
          <span class="cz-fil-val"><select id="dTipOps"></select></span>
          <span class="cz-fil-lbl">TipDocumentTVA</span>
          <span class="cz-fil-val"><select id="dTipDoc"></select></span>
          <span class="cz-fil-lbl">TipTaxare</span>
          <span class="cz-fil-val"><select id="dTipTax"></select></span>
        </div>
      </div>

      <!-- Tab buttons -->
      <div class="decont-tabs">
        <button class="decont-tab active" data-tab="sint">📊 Sinteză &amp; Detaliu</button>
        <button class="decont-tab" data-tab="note">📒 Note contabile</button>
      </div>

      <!-- PANEL 1: Sinteză + Detaliu -->
      <div class="decont-tab-panel" data-panel="sint">
        <!-- PIVOT 1 - Synthesis -->
        <div class="decont-section-label">Sinteză operațiuni</div>
        <div class="decont-pivot-wrap">
          <table class="cz-pivot decont-pivot-1">
            <thead><tr>
              <th>Nr.Crt.</th><th>TipTranzactie</th><th>Natura economică</th>
              <th>Articol</th><th class="num">Cont Tert</th><th class="num">Cont Tz</th>
              <th>TipTaxare</th><th class="num">%TVA</th><th class="num">NrDoc</th>
              <th class="num">RON_ValNet</th><th class="num">RON_TVA</th><th class="num">Total Decont</th>
            </tr></thead>
            <tbody id="dPivot1Body"></tbody>
            <tfoot id="dPivot1Foot"></tfoot>
          </table>
        </div>

        <div class="cz-section-divider"></div>

        <!-- PIVOT 2 - Detail -->
        <div class="decont-section-label">Detaliu documente</div>
        <div class="decont-pivot-wrap">
          <table class="cz-pivot decont-pivot-2">
            <thead><tr>
              <th>Nr.Crt.</th><th>Articol</th><th>TipTranzactie</th>
              <th>CodTipFactura</th><th>Referință</th><th class="num">CotaTVA</th>
              <th>Moneda</th><th class="num">CursV</th>
              <th class="num col-orig">Valoare <span class="col-curr" id="dValoareCurr">(RON)</span></th>
              <th class="num col-orig">TVA <span class="col-curr" id="dTVACurr">(RON)</span></th>
              <th class="num col-orig">Total <span class="col-curr" id="dTotalCurr">(RON)</span></th>
              <th class="num col-ron">Valoare RON</th>
              <th class="num col-ron">TVA RON</th>
              <th class="num col-ron">Total RON</th>
            </tr></thead>
            <tbody id="dPivot2Body"></tbody>
            <tfoot id="dPivot2Foot"></tfoot>
          </table>
        </div>
      </div>

      <!-- PANEL 2: Note contabile -->
      <div class="decont-tab-panel hidden" data-panel="note">
        <div class="decont-section-label">Note contabile auto-generate (la nivel de Pivot 1)</div>
        <div class="decont-note-info">
          <small>
            O notă contabilă pentru fiecare linie din <b>Sinteză operațiuni</b> (Pivot 1), formată din 2 mișcări: <b>valoarea netă</b> și <b>TVA</b>.<br/>
            Reguli: <b>1_Recunoaștere</b> → D = Cont Tz / C = Cont Tert &nbsp;·&nbsp;
            <b>2_Stornare</b> și <b>3_Discount</b> → invers (D = Cont Tert / C = Cont Tz, valoare absolută).
            TVA pe contul <b>4426</b> cu contra <b>Cont Tert</b> (taxare normală) sau <b>4427</b> (taxare inversă).
          </small>
        </div>
        <div class="decont-pivot-wrap">
          <table class="cz-pivot decont-note-table">
            <thead><tr>
              <th>NDP</th>
              <th>Data</th>
              <th class="num">Cont D</th>
              <th class="num">Cont C</th>
              <th class="num col-ron">Suma RON</th>
              <th>Moneda</th>
              <th class="num">Curs Valutar</th>
              <th class="num col-orig">Suma</th>
              <th>Explicaţii (Articol)</th>
            </tr></thead>
            <tbody id="dNoteBody"></tbody>
            <tfoot id="dNoteFoot"></tfoot>
          </table>
        </div>
      </div>

      <div class="decont-grand-total" id="dGrandTotalBox">
        <span class="lbl">Total general decont</span>
        <span class="val" id="dGrandTotal">0,00</span>
      </div>

      <div class="cz-footer">
        <div class="cz-foot-cell"><span class="lbl">Întocmit</span><span class="cz-issuer-name">smartBIZ Agent</span><span class="cz-line"></span></div>
        <div class="cz-foot-cell"><span class="lbl">Verificat</span><span class="cz-line"></span></div>
        <div class="cz-foot-issuer">AiAll SRL · ${new Date().toLocaleDateString('ro-RO')}</div>
      </div>
    </div>
  `;

  function populateSecondaryFilters() {
    // Get available distinct values within current An+Luna+Canal
    const subset = ds.filter(r => r.An === curAn && r.Luna === Number(curLuna) && r.Canal === curCanal);
    const fluxes = [...new Set(subset.map(r => r.Flux).filter(Boolean))].sort();
    const tipOps = [...new Set(subset.map(r => r.TipOpsTVA).filter(Boolean))].sort();
    const tipDocs = [...new Set(subset.map(r => r.TipDocumentTVA).filter(Boolean))].sort();
    const tipTaxs = [...new Set(subset.map(r => r.TipTaxare).filter(Boolean))].sort();

    if (!fluxes.includes(curFlux)) curFlux = fluxes[0] || '';
    if (!tipOps.includes(curTipOpsTVA)) curTipOpsTVA = tipOps[0] || '';
    if (!tipDocs.includes(curTipDocumentTVA)) curTipDocumentTVA = tipDocs[0] || '';
    if (!tipTaxs.includes(curTipTaxare)) curTipTaxare = tipTaxs[0] || '';

    $('#dFlux').innerHTML = fluxes.map(v => `<option value="${esc(v)}"${v===curFlux?' selected':''}>${esc(v)}</option>`).join('');
    $('#dTipOps').innerHTML = tipOps.map(v => `<option value="${esc(v)}"${v===curTipOpsTVA?' selected':''}>${esc(v)}</option>`).join('');
    $('#dTipDoc').innerHTML = tipDocs.map(v => `<option value="${esc(v)}"${v===curTipDocumentTVA?' selected':''}>${esc(v)}</option>`).join('');
    $('#dTipTax').innerHTML = tipTaxs.map(v => `<option value="${esc(v)}"${v===curTipTaxare?' selected':''}>${esc(v)}</option>`).join('');
  }

  function computeDecontIdx() {
    // Reproduce Centralizator Pivot 1 ordering for current An+Luna+Canal,
    // then find row index matching the current 4 filters → that's the decont anexa number.
    const subset = ds.filter(r => r.An === curAn && r.Luna === Number(curLuna) && r.Canal === curCanal);
    const p1Map = new Map();
    subset.forEach(r => {
      const key = `${r.TipOpsTVA}|${r.TipTaxare}|${r.TipDocumentTVA}|${r.Flux}`;
      if (!p1Map.has(key)) p1Map.set(key, {
        Problematica: r.TipOpsTVA || '',
        TipTaxare: r.TipTaxare || '',
        TipDocumentTVA: r.TipDocumentTVA || '',
        Flux: r.Flux || ''
      });
    });
    const p1 = [...p1Map.values()].sort((a,b) =>
      (a.Flux === b.Flux ? 0 : a.Flux.startsWith('Cumparare') ? -1 : 1) ||
      a.Problematica.localeCompare(b.Problematica) ||
      a.TipTaxare.localeCompare(b.TipTaxare) ||
      a.TipDocumentTVA.localeCompare(b.TipDocumentTVA)
    );
    const idx = p1.findIndex(r =>
      r.Flux === curFlux &&
      r.Problematica === curTipOpsTVA &&
      r.TipDocumentTVA === curTipDocumentTVA &&
      r.TipTaxare === curTipTaxare
    );
    return idx >= 0 ? idx + 1 : 1;
  }

  function render() {
    populateSecondaryFilters();
    const decontIdx = computeDecontIdx();
    _decontCtx.decontIdx = decontIdx;

    // Update partner info
    const partner = getPartner(curCanal);
    $('#dPartner').textContent = partner.name;
    $('#dPartnerMeta').innerHTML = `CUI: ${esc(partner.cui)} &nbsp;·&nbsp; AF: ${esc(partner.af)}<br/>${esc(partner.country)}`;
    $('#dPeriod').textContent = `în perioada: luna ${curLuna} an: ${curAn}`;
    $('#dAnexa').textContent = `${curAn}_${pad2(curLuna)} ${curCanal} Decont #C${pad2(decontIdx)}`;

    // Filter dataSet
    const filtered = ds.filter(r =>
      r.An === curAn &&
      r.Luna === Number(curLuna) &&
      r.Canal === curCanal &&
      (r.Flux || '') === curFlux &&
      (r.TipOpsTVA || '') === curTipOpsTVA &&
      (r.TipDocumentTVA || '') === curTipDocumentTVA &&
      (r.TipTaxare || '') === curTipTaxare
    );

    if (filtered.length === 0) {
      $('#dPivot1Body').innerHTML = '<tr><td colspan="12" class="empty">Niciun rezultat pentru filtrele selectate.</td></tr>';
      $('#dPivot1Foot').innerHTML = '';
      $('#dPivot2Body').innerHTML = '<tr><td colspan="14" class="empty">Niciun rezultat.</td></tr>';
      $('#dPivot2Foot').innerHTML = '';
      $('#dGrandTotal').textContent = '0,00';
      return;
    }

    // ===== Pivot 1: Synthesis grouped by (TipTranzactie + NaturaEconomica + Articol + ContTert + ContTz + TipTaxare + CotaTVA)
    const p1Map = new Map();
    filtered.forEach(r => {
      const key = `${r.TipTranzactie}|${r.NaturaEconomica}|${r.Articol}|${r.ContTert}|${r.ContTz}|${r.TipTaxare}|${r.CotaTVA}`;
      if (!p1Map.has(key)) p1Map.set(key, {
        TipTranzactie: r.TipTranzactie || '',
        NaturaEconomica: r.NaturaEconomica || '',
        Articol: r.Articol || '',
        ContTert: r.ContTert || '',
        ContTz: r.ContTz || '',
        TipTaxare: r.TipTaxare || '',
        CotaTVA: r.CotaTVA || 0,
        Moneda: r.Moneda || 'RON',
        CursValutar: r.CursValutar || 1,
        nrDoc: 0, sumVal: 0, sumTVA: 0,
        sumValOrig: 0, sumTVAOrig: 0
      });
      const e = p1Map.get(key);
      e.nrDoc += 1;
      e.sumVal += (r.Ron_Val || 0);
      e.sumTVA += (r.Ron_TVA || 0);
      e.sumValOrig += (r.Valoare || 0);
      e.sumTVAOrig += (r.ValoareTVA || 0);
    });
    // Sort: TipTranzactie (Recunoastere → Stornare → Discount), then NaturaEconomica, then Articol
    const pivot1 = [...p1Map.values()].sort((a,b) =>
      a.TipTranzactie.localeCompare(b.TipTranzactie) ||
      a.NaturaEconomica.localeCompare(b.NaturaEconomica) ||
      a.Articol.localeCompare(b.Articol)
    );

    let totalNrDoc = 0, totalVal = 0, totalTVA = 0, totalDecont = 0;
    $('#dPivot1Body').innerHTML = pivot1.map((r, i) => {
      const dt = r.sumVal + r.sumTVA;
      totalNrDoc += r.nrDoc;
      totalVal += r.sumVal;
      totalTVA += r.sumTVA;
      // Total Decont shown for Recunoastere only (matches PDF behavior)
      const showTotal = (r.TipTranzactie || '').startsWith('1_');
      if (showTotal) totalDecont += dt;
      return `<tr>
        <td class="num">${i+1}</td>
        <td>${esc(r.TipTranzactie)}</td>
        <td>${esc(r.NaturaEconomica)}</td>
        <td>${esc(r.Articol)}</td>
        <td class="num">${esc(r.ContTert)}</td>
        <td class="num">${esc(r.ContTz)}</td>
        <td>${esc(r.TipTaxare)}</td>
        <td class="num">${r.CotaTVA}</td>
        <td class="num">${fmtNum(r.nrDoc)}</td>
        <td class="num">${fmtNum(r.sumVal)}</td>
        <td class="num">${fmtNum(r.sumTVA)}</td>
        <td class="num">${showTotal ? fmtNum(dt) : ''}</td>
      </tr>`;
    }).join('');
    $('#dPivot1Foot').innerHTML = `<tr class="cz-total-yellow cz-total-emph">
      <td colspan="8" class="cz-total-label"><b>Total general</b></td>
      <td class="num"><b>${fmtNum(totalNrDoc)}</b></td>
      <td class="num"><b>${fmtNum(totalVal)}</b></td>
      <td class="num"><b>${fmtNum(totalTVA)}</b></td>
      <td class="num"></td>
    </tr>`;

    // ===== Pivot 2: Detail by Articol -> TipTranzactie -> CodTipFactura -> Document
    const sorted = [...filtered].sort((a,b) =>
      (a.Articol||'').localeCompare(b.Articol||'') ||
      (a.TipTranzactie||'').localeCompare(b.TipTranzactie||'') ||
      (a.CodTipFactura||'').localeCompare(b.CodTipFactura||'') ||
      (a.Referinta||'').localeCompare(b.Referinta||'')
    );

    // Detect dominant currency for header labels (within a Decont, all rows share the canal & moneda)
    const monedaSet = new Set(filtered.map(r => r.Moneda || 'RON'));
    const dominantMoneda = monedaSet.size === 1 ? [...monedaSet][0] : 'mix';
    const monLbl = dominantMoneda === 'mix' ? '(moneda fact.)' : `(${dominantMoneda})`;
    $('#dValoareCurr').textContent = monLbl;
    $('#dTVACurr').textContent = monLbl;
    $('#dTotalCurr').textContent = monLbl;

    // Group by Articol for subtotals
    const articolGroups = new Map();
    sorted.forEach(r => {
      const k = r.Articol || '—';
      if (!articolGroups.has(k)) articolGroups.set(k, []);
      articolGroups.get(k).push(r);
    });

    const rowsHtml = [];
    let grandValRaw = 0, grandTVARaw = 0, grandTotalRaw = 0,
        grandValRON = 0, grandTVARON = 0, grandTotalRON = 0;
    [...articolGroups.entries()].forEach(([articol, rows]) => {
      let prevTipTranz = null, prevCodTip = null;
      let nr = 0;
      let groupValRaw = 0, groupTVARaw = 0, groupTotalRaw = 0,
          groupValRON = 0, groupTVARON = 0, groupTotalRON = 0;
      rows.forEach((r, idx) => {
        nr++;
        // Cell collapsing (per group)
        const showArticol = idx === 0;
        const showTipTranz = idx === 0 || r.TipTranzactie !== prevTipTranz;
        const showCodTip = idx === 0 || r.TipTranzactie !== prevTipTranz || r.CodTipFactura !== prevCodTip;
        prevTipTranz = r.TipTranzactie;
        prevCodTip = r.CodTipFactura;

        const valRaw = r.Valoare || 0;
        const valTVA = r.ValoareTVA || 0;
        const ronVal = r.Ron_Val || 0;
        const ronTVA = r.Ron_TVA || 0;
        const totalOrig = valRaw + valTVA;          // Total in invoice currency
        const totalRON  = ronVal + ronTVA;          // Total in RON

        groupValRaw  += valRaw;   groupTVARaw  += valTVA;  groupTotalRaw  += totalOrig;
        groupValRON  += ronVal;   groupTVARON  += ronTVA;  groupTotalRON  += totalRON;
        grandValRaw  += valRaw;   grandTVARaw  += valTVA;  grandTotalRaw  += totalOrig;
        grandValRON  += ronVal;   grandTVARON  += ronTVA;  grandTotalRON  += totalRON;

        rowsHtml.push(`<tr>
          <td class="num">${nr}</td>
          <td>${showArticol ? esc(articol) : ''}</td>
          <td>${showTipTranz ? esc(r.TipTranzactie) : ''}</td>
          <td>${showCodTip ? esc(r.CodTipFactura) : ''}</td>
          <td>${esc(r.Referinta)}</td>
          <td class="num">${r.CotaTVA}</td>
          <td>${esc(r.Moneda)}</td>
          <td class="num">${fmtNum(r.CursValutar, 4)}</td>
          <td class="num col-orig">${fmtNum(valRaw)}</td>
          <td class="num col-orig">${fmtNum(valTVA)}</td>
          <td class="num col-orig">${fmtNum(totalOrig)}</td>
          <td class="num col-ron">${fmtNum(ronVal)}</td>
          <td class="num col-ron">${fmtNum(ronTVA)}</td>
          <td class="num col-ron">${fmtNum(totalRON)}</td>
        </tr>`);
      });
      // Subtotal row per Articol — semantically correct currency-aware sums.
      // Layout: 3 orig columns (Val/TVA/Total) contiguous, then 3 RON columns
      // (Val/TVA/Total) contiguous — no more intercalation.
      rowsHtml.push(`<tr class="decont-articol-total">
        <td></td>
        <td colspan="7"><b>${esc(articol)} Total</b></td>
        <td class="num col-orig"><b>${fmtNum(groupValRaw)}</b></td>
        <td class="num col-orig"><b>${fmtNum(groupTVARaw)}</b></td>
        <td class="num col-orig"><b>${fmtNum(groupTotalRaw)}</b></td>
        <td class="num col-ron"><b>${fmtNum(groupValRON)}</b></td>
        <td class="num col-ron"><b>${fmtNum(groupTVARON)}</b></td>
        <td class="num col-ron"><b>${fmtNum(groupTotalRON)}</b></td>
      </tr>`);
    });

    $('#dPivot2Body').innerHTML = rowsHtml.join('');
    $('#dPivot2Foot').innerHTML = `<tr class="cz-total-yellow cz-total-emph">
      <td colspan="8" class="cz-total-label"><b>Total general</b></td>
      <td class="num col-orig"><b>${fmtNum(grandValRaw)}</b></td>
      <td class="num col-orig"><b>${fmtNum(grandTVARaw)}</b></td>
      <td class="num col-orig"><b>${fmtNum(grandTotalRaw)}</b></td>
      <td class="num col-ron"><b>${fmtNum(grandValRON)}</b></td>
      <td class="num col-ron"><b>${fmtNum(grandTVARON)}</b></td>
      <td class="num col-ron"><b>${fmtNum(grandTotalRON)}</b></td>
    </tr>`;

    $('#dGrandTotal').textContent = fmtNum(grandTotalRON);

    // ===== Note contabile generated at Pivot 1 level (one note per group) =====
    renderNoteContabile(pivot1);
  }

  // Last computed note rows (for Excel export with proper numeric values)
  let _lastNoteRows = [];

  // Generate journal entries (note contabile) per Pivot 1 group (one note pair per group: net + TVA)
  function renderNoteContabile(pivot1) {
    _lastNoteRows = [];
    if (!pivot1 || pivot1.length === 0) {
      $('#dNoteBody').innerHTML = '<tr><td colspan="9" class="empty">Nicio operațiune.</td></tr>';
      $('#dNoteFoot').innerHTML = '';
      return;
    }

    // Decont identifier prefix (e.g., "C01") used for NrDecont-Crt label
    const decontPrefix = `C${pad2(_decontCtx.decontIdx || 1)}`;
    // Posting date for all journal entries = last day of the decont's month (dd.mm.yyyy)
    const decontDate = `${pad2(new Date(curAn, curLuna, 0).getDate())}.${pad2(curLuna)}.${curAn}`;

    const html = [];
    let nrCrt = 0;
    let totalSumaRON = 0;

    pivot1.forEach(g => {
      const isReversal = !(g.TipTranzactie || '').startsWith('1_');
      const isInversa  = (g.TipTaxare || '').toLowerCase().includes('invers');
      const valNet = Math.abs(g.sumVal || 0);
      const valTVA = Math.abs(g.sumTVA || 0);
      const valNetOrig = Math.abs(g.sumValOrig || 0);
      const valTVAOrig = Math.abs(g.sumTVAOrig || 0);
      const curs = g.CursValutar || 1;

      // Net line accounts
      const dNet = isReversal ? g.ContTert : g.ContTz;
      const cNet = isReversal ? g.ContTz   : g.ContTert;
      // VAT line accounts: counter is 4427 for taxare inversa, ContTert for normala
      const tvaCounter = isInversa ? '4427' : g.ContTert;
      const dTVA = isReversal ? tvaCounter : '4426';
      const cTVA = isReversal ? '4426'     : tvaCounter;

      const tipShort = (g.TipTranzactie || '').replace(/^[0-9]_/, '');
      const explNet = `${tipShort}: ${g.Articol || ''}`;
      const explTVA = `TVA ${g.CotaTVA}%${isInversa ? ' (taxare inversă)' : ''}${isReversal ? ' — stornare' : ''}: ${g.Articol || ''}`;

      // Net movement
      if (valNet > 0) {
        nrCrt++;
        const ndp = `${decontPrefix}-${nrCrt}`;
        html.push(`<tr class="note-net-row">
          <td class="num"><b>${ndp}</b></td>
          <td>${decontDate}</td>
          <td class="num"><b>${esc(dNet)}</b></td>
          <td class="num"><b>${esc(cNet)}</b></td>
          <td class="num col-ron"><b>${fmtNum(valNet)}</b></td>
          <td>${esc(g.Moneda || 'RON')}</td>
          <td class="num">${fmtNum(curs, 4)}</td>
          <td class="num col-orig">${fmtNum(valNetOrig)}</td>
          <td>${esc(explNet)}</td>
        </tr>`);
        _lastNoteRows.push([ndp, decontDate, dNet, cNet, valNet, g.Moneda || 'RON', curs, valNetOrig, explNet]);
        totalSumaRON += valNet;
      }
      // VAT movement
      if (valTVA > 0) {
        nrCrt++;
        const ndp = `${decontPrefix}-${nrCrt}`;
        html.push(`<tr class="note-vat-row">
          <td class="num"><b>${ndp}</b></td>
          <td>${decontDate}</td>
          <td class="num"><b>${esc(dTVA)}</b></td>
          <td class="num"><b>${esc(cTVA)}</b></td>
          <td class="num col-ron"><b>${fmtNum(valTVA)}</b></td>
          <td>${esc(g.Moneda || 'RON')}</td>
          <td class="num">${fmtNum(curs, 4)}</td>
          <td class="num col-orig">${fmtNum(valTVAOrig)}</td>
          <td>${esc(explTVA)}</td>
        </tr>`);
        _lastNoteRows.push([ndp, decontDate, dTVA, cTVA, valTVA, g.Moneda || 'RON', curs, valTVAOrig, explTVA]);
        totalSumaRON += valTVA;
      }
    });

    $('#dNoteBody').innerHTML = html.join('');
    $('#dNoteFoot').innerHTML = `<tr class="cz-total-yellow cz-total-emph">
      <td colspan="4" class="cz-total-label"><b>Total D = Total C</b></td>
      <td class="num col-ron"><b>${fmtNum(totalSumaRON)}</b></td>
      <td colspan="4"></td>
    </tr>`;
  }

  // Export current note contabile rows to Excel
  function exportNoteToExcel() {
    if (!_lastNoteRows.length) { toast('Nicio notă de exportat.', 'warn'); return; }
    const headers = ['NDP', 'Data', 'Cont D', 'Cont C', 'Suma RON', 'Moneda', 'Curs Valutar', 'Suma', 'Explicații'];
    const total = _lastNoteRows.reduce((s, r) => s + (r[4] || 0), 0);
    const footer = ['', '', '', 'TOTAL', total, '', '', '', ''];
    const ws = XLSX.utils.aoa_to_sheet([headers, ..._lastNoteRows, footer]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Note contabile');
    const fname = `Note_C${pad2(_decontCtx.decontIdx || 1)}_${curAn}.${pad2(curLuna)}_${curCanal}.xlsx`.replace(/[\s/\\]+/g, '_');
    XLSX.writeFile(wb, fname);
  }

  // Bind events
  $('#dAn').onchange = e => { curAn = Number(e.target.value); render(); };
  $('#dLuna').onchange = e => { curLuna = Number(e.target.value); render(); };
  $('#dCanal').onchange = e => { curCanal = e.target.value; render(); };
  $('#dFlux').onchange = e => { curFlux = e.target.value; render(); };
  $('#dTipOps').onchange = e => { curTipOpsTVA = e.target.value; render(); };
  $('#dTipDoc').onchange = e => { curTipDocumentTVA = e.target.value; render(); };
  $('#dTipTax').onchange = e => { curTipTaxare = e.target.value; render(); };
  $('#dRefresh').onclick = () => render();
  $('#dBackToCz').onclick = () => navigate('centralizator');
  $('#dPrint').onclick = () => printDecont();
  $('#dExportNote').onclick = () => exportNoteToExcel();

  // Tab switching
  $$('.decont-tab').forEach(btn => {
    btn.onclick = () => {
      const tab = btn.dataset.tab;
      $$('.decont-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
      $$('.decont-tab-panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== tab));
      // Show Excel export only when Note contabile tab is active
      $('#dExportNote').style.display = (tab === 'note') ? '' : 'none';
    };
  });

  function printDecont() {
    document.body.classList.add('cz-printing');
    document.body.classList.add('decont-printing');
    let style = document.getElementById('decont-print-page-css');
    if (!style) {
      style = document.createElement('style');
      style.id = 'decont-print-page-css';
      document.head.appendChild(style);
    }
    const safeName = `Decont ${curAn}.${pad2(curLuna)} · ${curCanal} · #C${pad2(_decontCtx.decontIdx || 1)}`.replace(/"/g, '\\"');
    style.textContent = `
      @media print {
        @page { margin: 12mm 8mm 18mm 8mm; }
        @page {
          @bottom-left { content: "${safeName}"; font-family: 'Inter', Arial, sans-serif; font-size: 8pt; color: #6b7280; }
          @bottom-right { content: "Pagina " counter(page) " din " counter(pages); font-family: 'Inter', Arial, sans-serif; font-size: 8pt; color: #6b7280; }
        }
      }`;
    const oldTitle = document.title;
    document.title = safeName.replace(/\s+/g,'_').replace(/[^\w\.\-_]/g,'');
    setTimeout(() => {
      window.print();
      setTimeout(() => {
        document.title = oldTitle;
        document.body.classList.remove('cz-printing');
        document.body.classList.remove('decont-printing');
      }, 500);
    }, 100);
  }

  render();
}

// ====================== JURNAL CONTABIL (note contabile agregate filtrate An/Lună/Articol) ======================
async function renderJurnal(root) {
  const ds = await dbGetAll('dataSet');
  const marketplaces = await dbGetAll('marketplaces');

  if (ds.length === 0) {
    root.innerHTML = `
      <div class="page-head"><h1>Jurnal contabil</h1><p class="subtitle">Note contabile agregate per articol pe perioada selectată.</p></div>
      <div class="card"><div class="empty">Nu există date procesate. Procesează DataSet-ul mai întâi.</div></div>`;
    return;
  }

  const ans = [...new Set(ds.map(r => r.An))].sort();
  let curAn = ans[ans.length - 1];
  let curLuna = '';     // '' = toate lunile
  let curArticol = '';  // '' = toate articolele

  let _lastNoteRows = [];
  let tableView = null;

  function getLunis() {
    return [...new Set(ds.filter(r => r.An === curAn).map(r => r.Luna))].sort((a, b) => a - b);
  }
  function getArticole() {
    return [...new Set(
      ds.filter(r => r.An === curAn && (curLuna === '' || r.Luna === Number(curLuna)))
        .map(r => r.Articol).filter(Boolean)
    )].sort();
  }

  function buildUI() {
    const lunis = getLunis();
    const articole = getArticole();
    // If current Luna/Articol is no longer in available list, reset to (toate)
    if (curLuna !== '' && !lunis.includes(Number(curLuna))) curLuna = '';
    if (curArticol !== '' && !articole.includes(curArticol)) curArticol = '';

    root.innerHTML = `
      <div class="centralizator decont-page">
        <div class="cz-banner-row">
          <div class="cz-banner-tools">
            <button class="btn-icon-tool" id="jRefresh" title="Refresh">🔄</button>
            <button class="btn-icon-tool" id="jPrint" title="Export PDF">🖨️</button>
            <button class="btn-icon-tool" id="jExportXls" title="Export Excel">📥</button>
          </div>
          <div class="cz-anexe-banner" id="jPeriodBanner">—</div>
        </div>

        <div class="cz-titles">
          <div class="cz-title-l">
            <h1>Quasar Comex SRL</h1>
            <div class="cz-mkp-label">Seller</div>
          </div>
          <div class="cz-title-r">
            <h1>Jurnal contabil</h1>
            <div class="cz-mkp-label">Note contabile agregate</div>
          </div>
        </div>

        <div class="decont-doc-title">
          <h2>JURNAL CONTABIL</h2>
          <div class="decont-doc-period" id="jDocPeriod">—</div>
        </div>

        <div class="decont-filters cz-filters">
          <div class="cz-filter-inline">
            <span class="cz-fil-lbl">An</span>
            <span class="cz-fil-val"><select id="jAn">${ans.map(a => `<option value="${a}"${a===curAn?' selected':''}>${a}</option>`).join('')}</select></span>
            <span class="cz-fil-lbl">Luna</span>
            <span class="cz-fil-val"><select id="jLuna"><option value=""${curLuna===''?' selected':''}>(toate)</option>${lunis.map(l => `<option value="${l}"${String(l)===String(curLuna)?' selected':''}>${l}</option>`).join('')}</select></span>
            <span class="cz-fil-lbl">Articol</span>
            <span class="cz-fil-val"><select id="jArticol"><option value=""${curArticol===''?' selected':''}>(toate)</option>${articole.map(a => `<option value="${esc(a)}"${a===curArticol?' selected':''}>${esc(a)}</option>`).join('')}</select></span>
          </div>
        </div>

        <div class="decont-section-label">Note contabile</div>
        <div class="decont-pivot-wrap">
          <table class="cz-pivot decont-note-table">
            <thead><tr>
              <th>NDP</th>
              <th>Data</th>
              <th class="num">Cont D</th>
              <th class="num">Cont C</th>
              <th class="num col-ron">Suma RON</th>
              <th>Moneda</th>
              <th class="num">Curs Valutar</th>
              <th class="num col-orig">Suma</th>
              <th>Explicaţii (Articol)</th>
            </tr></thead>
            <tbody id="jNoteBody"></tbody>
            <tfoot id="jNoteFoot"></tfoot>
          </table>
        </div>

        <div class="decont-grand-total">
          <span class="lbl">Total general jurnal</span>
          <span class="val" id="jGrandTotal">0,00</span>
        </div>

        <div class="cz-footer">
          <div class="cz-foot-cell"><span class="lbl">Întocmit</span><span class="cz-issuer-name">smartBIZ Agent</span><span class="cz-line"></span></div>
          <div class="cz-foot-cell"><span class="lbl">Verificat</span><span class="cz-line"></span></div>
          <div class="cz-foot-issuer">AiAll SRL · ${new Date().toLocaleDateString('ro-RO')}</div>
        </div>
      </div>
    `;

    $('#jAn').onchange      = e => { curAn = Number(e.target.value); curLuna = ''; curArticol = ''; buildUI(); };
    $('#jLuna').onchange    = e => { curLuna = e.target.value; curArticol = ''; buildUI(); };
    $('#jArticol').onchange = e => { curArticol = e.target.value; render(); };
    $('#jRefresh').onclick   = () => buildUI();
    $('#jPrint').onclick     = () => printJurnal();
    $('#jExportXls').onclick = () => exportJurnalToExcel();

    tableView = QcxTable.create({body:'jNoteBody',key:'jurnal',title:'Jurnal contabil',identify:r=>r[0],currency:r=>r[5],onChange:()=>renderNotes(),columns:[
      {key:'0',label:'NDP'},{key:'1',label:'Data',type:'date'},{key:'2',label:'Cont D'}, {key:'3',label:'Cont C'},
      {key:'4',label:'Suma RON',type:'number',total:'sum'},{key:'5',label:'Moneda'},
      {key:'6',label:'Curs Valutar',type:'number',decimals:4},{key:'7',label:'Suma',type:'number',total:'sum',currency:true},{key:'8',label:'Explicații'}
    ]});
    render();
  }

  function render() {
    const filtered = ds.filter(r =>
      r.An === curAn &&
      (curLuna === '' || r.Luna === Number(curLuna)) &&
      (curArticol === '' || (r.Articol || '') === curArticol)
    );

    // Pivot 1: same grouping logic as renderDecont (but without Canal/Flux/etc filters)
    const p1Map = new Map();
    filtered.forEach(r => {
      const key = `${r.TipTranzactie}|${r.NaturaEconomica}|${r.Articol}|${r.ContTert}|${r.ContTz}|${r.TipTaxare}|${r.CotaTVA}|${r.Moneda}`;
      if (!p1Map.has(key)) p1Map.set(key, {
        TipTranzactie: r.TipTranzactie || '',
        NaturaEconomica: r.NaturaEconomica || '',
        Articol: r.Articol || '',
        ContTert: r.ContTert || '',
        ContTz: r.ContTz || '',
        TipTaxare: r.TipTaxare || '',
        CotaTVA: r.CotaTVA || 0,
        Moneda: r.Moneda || 'RON',
        CursValutar: r.CursValutar || 1,
        nrDoc: 0, sumVal: 0, sumTVA: 0,
        sumValOrig: 0, sumTVAOrig: 0
      });
      const e = p1Map.get(key);
      e.nrDoc += 1;
      e.sumVal += (r.Ron_Val || 0);
      e.sumTVA += (r.Ron_TVA || 0);
      e.sumValOrig += (r.Valoare || 0);
      e.sumTVAOrig += (r.ValoareTVA || 0);
    });
    const pivot1 = [...p1Map.values()].sort((a, b) =>
      a.TipTranzactie.localeCompare(b.TipTranzactie) ||
      a.NaturaEconomica.localeCompare(b.NaturaEconomica) ||
      a.Articol.localeCompare(b.Articol)
    );

    const periodLabel = curLuna === '' ? `Anul ${curAn}` : `${pad2(curLuna)}.${curAn}`;
    const titlePeriod = curArticol ? `${periodLabel} · ${curArticol}` : periodLabel;
    $('#jPeriodBanner').textContent = titlePeriod;
    $('#jDocPeriod').textContent = titlePeriod;

    _lastNoteRows = [];

    if (pivot1.length === 0) {
      $('#jNoteBody').innerHTML = '<tr><td colspan="9" class="empty">Niciun rezultat pentru filtrele selectate.</td></tr>';
      $('#jNoteFoot').innerHTML = '';
      $('#jGrandTotal').textContent = '0,00';
      renderNotes();
      return;
    }

    // Posting date: last day of month if Luna is selected, last day of year otherwise
    const decontDate = curLuna === ''
      ? `31.12.${curAn}`
      : `${pad2(new Date(curAn, Number(curLuna), 0).getDate())}.${pad2(curLuna)}.${curAn}`;
    // NDP prefix: J{An}{Luna} or J{An}
    const ndpPrefix = curLuna === '' ? `J${curAn}` : `J${curAn}${pad2(curLuna)}`;

    let nrCrt = 0;
    let totalSumaRON = 0;

    pivot1.forEach(g => {
      const isReversal = !(g.TipTranzactie || '').startsWith('1_');
      const isInversa  = (g.TipTaxare || '').toLowerCase().includes('invers');
      const valNet = Math.abs(g.sumVal || 0);
      const valTVA = Math.abs(g.sumTVA || 0);
      const valNetOrig = Math.abs(g.sumValOrig || 0);
      const valTVAOrig = Math.abs(g.sumTVAOrig || 0);
      const curs = g.CursValutar || 1;

      const dNet = isReversal ? g.ContTert : g.ContTz;
      const cNet = isReversal ? g.ContTz   : g.ContTert;
      const tvaCounter = isInversa ? '4427' : g.ContTert;
      const dTVA = isReversal ? tvaCounter : '4426';
      const cTVA = isReversal ? '4426'     : tvaCounter;

      const tipShort = (g.TipTranzactie || '').replace(/^[0-9]_/, '');
      const explNet = `${tipShort}: ${g.Articol || ''}`;
      const explTVA = `TVA ${g.CotaTVA}%${isInversa ? ' (taxare inversă)' : ''}${isReversal ? ' — stornare' : ''}: ${g.Articol || ''}`;

      if (valNet > 0) {
        nrCrt++;
        const ndp = `${ndpPrefix}-${nrCrt}`;
        _lastNoteRows.push([ndp, decontDate, dNet, cNet, valNet, g.Moneda || 'RON', curs, valNetOrig, explNet]);
        totalSumaRON += valNet;
      }
      if (valTVA > 0) {
        nrCrt++;
        const ndp = `${ndpPrefix}-${nrCrt}`;
        _lastNoteRows.push([ndp, decontDate, dTVA, cTVA, valTVA, g.Moneda || 'RON', curs, valTVAOrig, explTVA]);
        totalSumaRON += valTVA;
      }
    });

    $('#jNoteFoot').innerHTML = `<tr class="cz-total-yellow cz-total-emph">
      <td colspan="4" class="cz-total-label"><b>Total D = Total C</b></td>
      <td class="num col-ron"><b>${fmtNum(totalSumaRON)}</b></td>
      <td colspan="4"></td>
    </tr>`;
    $('#jGrandTotal').textContent = fmtNum(totalSumaRON);
    renderNotes();
  }

  function renderNotes() {
    const rows = tableView.apply(_lastNoteRows);
    $('#jNoteBody').innerHTML = rows.map(r=>`<tr>${r.map((v,i)=>`<td${[4,6,7].includes(i)?' class="num"':''}>${esc([4,7].includes(i)?fmtNum(v):i===6?fmtNum(v,4):v)}</td>`).join('')}</tr>`).join('') || '<tr><td colspan="9" class="empty">Niciun rezultat.</td></tr>';
    tableView.rendered(rows);
  }

  function exportJurnalToExcel() {
    if (!_lastNoteRows.length) { toast('Nicio notă de exportat.', 'warn'); return; }
    const headers = ['NDP', 'Data', 'Cont D', 'Cont C', 'Suma RON', 'Moneda', 'Curs Valutar', 'Suma', 'Explicații'];
    const total = _lastNoteRows.reduce((s, r) => s + (r[4] || 0), 0);
    const footer = ['', '', '', 'TOTAL', total, '', '', '', ''];
    const ws = XLSX.utils.aoa_to_sheet([headers, ..._lastNoteRows, footer]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Jurnal');
    const periodTag = curLuna === '' ? `${curAn}` : `${curAn}_${pad2(curLuna)}`;
    const articolTag = curArticol ? `_${curArticol.replace(/[^\w]+/g,'_')}` : '';
    XLSX.writeFile(wb, `Jurnal_${periodTag}${articolTag}.xlsx`);
  }

  function printJurnal() {
    document.body.classList.add('cz-printing');
    document.body.classList.add('decont-printing');
    let style = document.getElementById('jurnal-print-page-css');
    if (!style) {
      style = document.createElement('style');
      style.id = 'jurnal-print-page-css';
      document.head.appendChild(style);
    }
    const periodTag = curLuna === '' ? `${curAn}` : `${pad2(curLuna)}.${curAn}`;
    const safeName = `Jurnal contabil ${periodTag}${curArticol ? ' · ' + curArticol : ''}`.replace(/"/g, '\\"');
    style.textContent = `
      @media print {
        @page { margin: 12mm 8mm 18mm 8mm; }
        @page {
          @bottom-left { content: "${safeName}"; font-family: 'Inter', Arial, sans-serif; font-size: 8pt; color: #6b7280; }
          @bottom-right { content: "Pagina " counter(page) " din " counter(pages); font-family: 'Inter', Arial, sans-serif; font-size: 8pt; color: #6b7280; }
        }
      }`;
    const oldTitle = document.title;
    document.title = safeName.replace(/\s+/g, '_').replace(/[^\w\.\-_]/g, '');
    setTimeout(() => {
      window.print();
      setTimeout(() => {
        document.title = oldTitle;
        document.body.classList.remove('cz-printing');
        document.body.classList.remove('decont-printing');
      }, 500);
    }, 100);
  }

  buildUI();
}

// ====================== RAPORT DINAMIC (pivot configurabil din DataSet) ======================
async function renderRaportDinamic(root) {
  const ds = await dbGetAll('dataSet');
  if (ds.length === 0) {
    root.innerHTML = `
      <div class="page-head"><h1>Raport dinamic</h1><p class="subtitle">Tabel pivotant configurabil din DataSet — alegi coloane, filtre și grupare.</p></div>
      <div class="card"><div class="empty">Nu există date procesate. Procesează DataSet-ul mai întâi.</div></div>`;
    return;
  }

  // All available columns from DataSet schema
  const ALL_COLS = [
    { key: 'Canal',            label: 'Canal',            type: 'text', default: true },
    { key: 'Flux',             label: 'Flux',             type: 'text' },
    { key: 'TipOperatiune',    label: 'Tip Operațiune',   type: 'text' },
    { key: 'CodTipFactura',    label: 'Cod Tip Factură',  type: 'text', default: true },
    { key: 'TipTranzactie',    label: 'Tip Tranzacție',   type: 'text' },
    { key: 'TipDocumentTVA',   label: 'Tip Doc TVA',      type: 'text' },
    { key: 'MkpFbe',           label: 'MKP/FBE',          type: 'text' },
    { key: 'CostCanal',        label: 'Cost Canal',       type: 'text' },
    { key: 'NaturaEconomica',  label: 'Natura economică', type: 'text', default: true },
    { key: 'ContTert',         label: 'Cont Tert',        type: 'text', default: true },
    { key: 'ContTz',           label: 'Cont Tz',          type: 'text', default: true },
    { key: 'FormulaContabila', label: 'D = C',            type: 'text' },
    { key: 'Articol',          label: 'Articol',          type: 'text', default: true },
    { key: 'Serie',            label: 'Serie',            type: 'text' },
    { key: 'Numar',            label: 'Număr',            type: 'text' },
    { key: 'DataEmitere',      label: 'Data emitere',     type: 'date' },
    { key: 'Referinta',        label: 'Referință',        type: 'text' },
    { key: 'An',               label: 'An',               type: 'num',  default: true },
    { key: 'Luna',             label: 'Luna',             type: 'num',  default: true },
    { key: 'Zi',               label: 'Zi',               type: 'num' },
    { key: 'Valoare',          label: 'Valoare',          type: 'num',  sumable: true, default: true },
    { key: 'CotaTVA',          label: '%TVA',             type: 'num',  default: true },
    { key: 'ValoareTVA',       label: 'Valoare TVA',      type: 'num',  sumable: true },
    { key: 'Moneda',           label: 'Monedă',           type: 'text', default: true },
    { key: 'CursValutar',      label: 'Curs',             type: 'num' },
    { key: 'Demultiplicator',  label: 'Demultiplicator',  type: 'num' },
    { key: 'Ron_Val',          label: 'RON Val',          type: 'num',  sumable: true, default: true },
    { key: 'Ron_TVA',          label: 'RON TVA',          type: 'num',  sumable: true },
    { key: 'TipOpsTVA',        label: 'Tip Ops TVA',      type: 'text' },
    { key: 'TipTVA',           label: 'Tip TVA',          type: 'text' },
    { key: 'ValCh',            label: 'Val Ch',           type: 'num',  sumable: true },
    { key: 'TipTaxare',        label: 'Tip Taxare',       type: 'text' },
    { key: '_count',           label: 'Nr documente',     type: 'num',  virtual: true, default: true },
  ];
  const colByKey = Object.fromEntries(ALL_COLS.map(c => [c.key, c]));

  // Pre-compute unique values per column (for filter dropdowns)
  const uniqueValues = {};
  ALL_COLS.forEach(c => {
    const set = new Set();
    ds.forEach(r => {
      const v = r[c.key];
      if (v !== undefined && v !== null && v !== '') set.add(v);
    });
    uniqueValues[c.key] = [...set].sort((a, b) => {
      // Numeric columns: coerce to Number so values stored as strings still
      // sort numerically (1, 2, 10 — not 1, 10, 2)
      if (c.type === 'num') {
        const na = Number(a), nb = Number(b);
        if (!isNaN(na) && !isNaN(nb)) return na - nb;
      }
      if (typeof a === 'number' && typeof b === 'number') return a - b;
      return String(a).localeCompare(String(b));
    });
  });

  // State (in-memory, per session)
  function freshState() {
    // Build cols then sort alphabetically by label — alphabetical is the
    // default order shown in the configurator. The `order` field tracks the
    // user-chosen position; it's applied on click of "Aplică".
    const cols = ALL_COLS.map(c => ({
      key: c.key, visible: !!c.default, filter: '', group: false, subtotal: false,
      // 'value' = column is treated as a measure: aggregated in groupings,
      // shown in totals/subtotals. Defaults from static `sumable` flag.
      value: !!c.sumable,
      // Per-column sort direction for multi-sort: 'none' | 'asc' | 'desc'
      sortDir: 'none'
    }));
    cols.sort((a, b) => {
      const la = colByKey[a.key]?.label || '';
      const lb = colByKey[b.key]?.label || '';
      return la.localeCompare(lb, 'ro');
    });
    // Assign 1..N order numbers based on alphabetical position
    cols.forEach((c, i) => { c.order = i + 1; });
    return {
      cols,
      sortKey: null,
      sortDir: 'asc',
      compact: true,   // suppress repeating values in non-numeric columns
      // Name of the currently applied saved configuration (or null = custom/unselected).
      // Set when applyConfig() runs or when saveCurrentConfig() persists a new entry.
      activeConfigName: null
    };
  }
  let state = freshState();

  // Helper: re-numerate `order` field on each col to match its current array
  // position. Called after drag-drop reorder and after applying user-typed
  // order numbers, so the visible numbers stay clean (1..N, no gaps).
  function renumberOrder() {
    state.cols.forEach((c, i) => { c.order = i + 1; });
  }

  // ---- Saved configurations (CRUD) ----
  // Stored under a single settings key as an array. All configs are global
  // (visible to all users). Only admins can save/rename/delete; non-admins
  // can apply existing configs (no DB write involved).
  const SAVED_CFG_KEY = 'raportDinamic.savedConfigs';
  let _savedConfigs = [];
  // Per-session sort state for the saved-configs table — persists across
  // buildUI rebuilds. Default: most-recently-updated first.
  let _savedSort = { key: 'updatedAt', dir: 'desc' };
  // Per-session filter for the column-configurator search box. Persists
  // across buildUI rebuilds so the user's typing isn't lost when other
  // state changes trigger a rebuild.
  let _colSearch = '';
  // Tracks the name of the most recently applied saved config — survives
  // edits (unlike state.activeConfigName which flips to null on edits).
  // Used by the "Save changes to X" button so the user can persist edits
  // back to the config they were working from.
  let _lastAppliedConfig = null;
  // Persists open/closed state of each <details> across buildUI rebuilds.
  // 'parent' wraps both inner panels (open by default — collapse to hide all).
  // 'config' = column configurator (open by default).
  // 'saved'  = saved configurations (closed by default to reduce clutter).
  const _detailsOpen = { parent: true, config: true, saved: false };

  async function loadSavedConfigs() {
    try {
      const rec = await dbGet('settings', SAVED_CFG_KEY);
      if (rec && Array.isArray(rec.list)) return rec.list;
    } catch (_) { /* not yet stored — empty list */ }
    return [];
  }
  async function persistSavedConfigs() {
    // Server enforces admin-only write — non-admins get a 403 and see a toast.
    await dbPut('settings', { key: SAVED_CFG_KEY, list: _savedConfigs });
  }

  function snapshotState() {
    // Deep-copy the current state so saved configs are independent of future edits.
    return JSON.parse(JSON.stringify({
      cols:    state.cols,
      sortKey: state.sortKey,
      sortDir: state.sortDir,
      compact: state.compact
    }));
  }
  function applyConfig(cfg) {
    const saved = cfg && cfg.state;
    if (!saved || !Array.isArray(saved.cols)) {
      toast('Configurația e coruptă sau goală.', 'error');
      return;
    }
    // Reconcile saved columns with current ALL_COLS (schema may have
    // gained/lost columns since the config was created).
    const savedKeys = new Set(saved.cols.map(c => c && c.key).filter(Boolean));
    const newCols = [];
    for (const sc of saved.cols) {
      if (sc && colByKey[sc.key]) {
        newCols.push({
          key:      sc.key,
          visible:  !!sc.visible,
          filter:   sc.filter || '',
          group:    !!sc.group,
          subtotal: !!sc.subtotal,
          value:    !!sc.value,
          sortDir:  sc.sortDir || 'none',
          order:    typeof sc.order === 'number' ? sc.order : 0  // backfill below
        });
      }
    }
    // Append any current cols not in the saved snapshot, with their defaults.
    for (const c of ALL_COLS) {
      if (!savedKeys.has(c.key)) {
        newCols.push({
          key:      c.key,
          visible:  !!c.default,
          filter:   '',
          group:    false,
          subtotal: false,
          value:    !!c.sumable,
          sortDir:  'none',
          order:    0   // backfill below
        });
      }
    }
    // Renumerate order to match the array position (1..N) — this also
    // backfills order for old configs saved before the field existed.
    newCols.forEach((c, i) => { c.order = i + 1; });
    state = {
      cols:    newCols,
      sortKey: saved.sortKey || null,
      sortDir: saved.sortDir || 'asc',
      compact: typeof saved.compact === 'boolean' ? saved.compact : true,
      activeConfigName: cfg.name || null
    };
    // Track this as the last applied config — survives edits so the user
    // can save changes back to it via the "Salvează modificări" button.
    _lastAppliedConfig = cfg.name || null;
    buildUI();
    // Auto-run the report so the user immediately sees the result of the loaded config.
    state.sortKey = null;
    render();
    toast(`Configurație aplicată: "${cfg.name}".`, 'success');
  }

  async function saveCurrentConfig(name) {
    const trimmed = (name || '').trim();
    if (!trimmed) {
      toast('Introdu un nume pentru configurație.', 'error');
      return;
    }
    const existingIdx = _savedConfigs.findIndex(c => c.name === trimmed);
    const now = new Date().toISOString();
    const snapshot = snapshotState();
    if (existingIdx >= 0) {
      if (!await confirmDialog(`Există deja o configurație numită <b>${esc(trimmed)}</b>. O suprascrii?`)) return;
      _savedConfigs[existingIdx] = {
        ...(_savedConfigs[existingIdx]),
        state:     snapshot,
        updatedAt: now
      };
    } else {
      _savedConfigs.push({
        id:        'cfg_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        name:      trimmed,
        state:     snapshot,
        createdAt: now,
        updatedAt: now
      });
    }
    try {
      await persistSavedConfigs();
      // Mark this as the active configuration in state — the user just told
      // us this snapshot has a name, so the dropdown should reflect that.
      state.activeConfigName = trimmed;
      buildUI();
      toast(existingIdx >= 0 ? 'Configurație suprascrisă.' : 'Configurație salvată.', 'success');
    } catch (err) {
      // Reload from server to avoid showing stale local state on failure
      _savedConfigs = await loadSavedConfigs();
      buildUI();
      toast(err.message, 'error');
    }
  }

  // Save current state into the last-applied saved config, without prompting
  // for a name. Used by the "↻ Salvează modificările" button which appears
  // when the user has loaded a config and made edits. Resyncs the active
  // marker so the dropdown stops showing "Custom".
  async function saveToLastAppliedConfig() {
    if (!_lastAppliedConfig) {
      toast('Nicio configurație activă de actualizat.', 'warn');
      return;
    }
    const idx = _savedConfigs.findIndex(c => c.name === _lastAppliedConfig);
    if (idx < 0) {
      // Config was deleted while user was editing — nothing to save into.
      toast('Configurația activă nu mai există. Salvează ca nouă.', 'error');
      _lastAppliedConfig = null;
      buildUI();
      return;
    }
    const target = _savedConfigs[idx];
    _savedConfigs[idx] = {
      ...target,
      state:     snapshotState(),
      updatedAt: new Date().toISOString()
    };
    try {
      await persistSavedConfigs();
      // State now matches the saved config again — sync the active marker
      // so the dropdown shows the config name (no longer "Custom").
      state.activeConfigName = target.name;
      buildUI();
      toast(`Modificările salvate în „${target.name}".`, 'success');
    } catch (err) {
      _savedConfigs = await loadSavedConfigs();
      buildUI();
      toast(err.message, 'error');
    }
  }

  async function renameConfig(id) {
    const cfg = _savedConfigs.find(c => c.id === id);
    if (!cfg) return;
    const newName = window.prompt('Nume nou pentru configurație:', cfg.name);
    if (newName === null) return; // user cancelled
    const trimmed = newName.trim();
    if (!trimmed || trimmed === cfg.name) return;
    if (_savedConfigs.some(c => c.id !== id && c.name === trimmed)) {
      toast('Există deja o configurație cu acest nume.', 'error');
      return;
    }
    const oldName = cfg.name;
    cfg.name = trimmed;
    cfg.updatedAt = new Date().toISOString();
    try {
      await persistSavedConfigs();
      // If the renamed config was the currently active one, keep the active
      // marker in sync with the new name.
      if (state.activeConfigName === oldName) state.activeConfigName = trimmed;
      if (_lastAppliedConfig === oldName) _lastAppliedConfig = trimmed;
      buildUI();
      toast('Configurație redenumită.', 'success');
    } catch (err) {
      _savedConfigs = await loadSavedConfigs();
      buildUI();
      toast(err.message, 'error');
    }
  }

  async function deleteConfig(id) {
    const cfg = _savedConfigs.find(c => c.id === id);
    if (!cfg) return;
    if (!await confirmDialog(`Ștergi configurația <b>${esc(cfg.name)}</b>?`)) return;
    const wasActive = state.activeConfigName === cfg.name;
    _savedConfigs = _savedConfigs.filter(c => c.id !== id);
    try {
      await persistSavedConfigs();
      // Drop the active marker if we just deleted the active configuration.
      if (wasActive) state.activeConfigName = null;
      // Same for last-applied — if it was the deleted config, drop it so
      // the "Save changes" button stops appearing.
      if (_lastAppliedConfig === cfg.name) _lastAppliedConfig = null;
      buildUI();
      toast('Configurație ștearsă.', 'success');
    } catch (err) {
      _savedConfigs = await loadSavedConfigs();
      buildUI();
      toast(err.message, 'error');
    }
  }

  // Mark a saved config as the default — only ONE config can be default at
  // any time, so toggling one ON automatically clears all others. Toggling
  // an already-default config OFF leaves no default (page will land on
  // "Custom" on next visit). Persisted to the same settings entry so the
  // marker survives reloads.
  async function setDefaultConfig(id) {
    const target = _savedConfigs.find(c => c.id === id);
    if (!target) return;
    const wasDefault = !!target.isDefault;
    // Single-default invariant: clear flag on every config first
    _savedConfigs.forEach(c => { c.isDefault = false; });
    // Toggle: if it was the default, leave it cleared; otherwise set it
    if (!wasDefault) target.isDefault = true;
    target.updatedAt = new Date().toISOString();
    try {
      await persistSavedConfigs();
      buildUI();
      toast(wasDefault ? 'Default eliminat.' : `"${target.name}" e acum raportul implicit.`, 'success');
    } catch (err) {
      _savedConfigs = await loadSavedConfigs();
      buildUI();
      toast(err.message, 'error');
    }
  }

  // Pre-load saved configs before the first UI build so the list is populated immediately.
  _savedConfigs = await loadSavedConfigs();

  function buildUI() {
    const rows = state.cols.map(s => {
      const c = colByKey[s.key];
      const isVirtual = !!c.virtual;
      const isNum = c.type === 'num';
      const opts = isVirtual ? '' : uniqueValues[c.key].slice(0, 500).map(v => {
        const sv = String(v);
        return `<option value="${esc(sv)}"${s.filter === sv ? ' selected' : ''}>${esc(sv)}</option>`;
      }).join('');
      const filterCell = isVirtual
        ? `<span style="color:var(--text-muted);font-size:.8rem">—</span>`
        : `<select data-col="${c.key}" data-prop="filter" class="${s.filter ? 'r-filter-active' : ''}"><option value="">(toate)</option>${opts}</select>`;
      const groupCell = isVirtual
        ? `<span style="color:var(--text-muted);font-size:.8rem">—</span>`
        : `<input type="checkbox" data-col="${c.key}" data-prop="group"${s.group ? ' checked' : ''}>`;
      // Subtotal toggle is only enabled when this col is marked as group
      const subtotalCell = isVirtual
        ? `<span style="color:var(--text-muted);font-size:.8rem">—</span>`
        : `<input type="checkbox" data-col="${c.key}" data-prop="subtotal"${s.subtotal ? ' checked' : ''}${!s.group ? ' disabled' : ''}>`;
      // Value (measure) toggle: only valid for numeric, non-virtual columns
      const valueCell = (isVirtual || !isNum)
        ? `<span style="color:var(--text-muted);font-size:.8rem">—</span>`
        : `<input type="checkbox" data-col="${c.key}" data-prop="value"${s.value ? ' checked' : ''}>`;
      // Order input — user types desired position; applied on click of "Aplică".
      // Number input ranges 1..N where N is total cols. The actual array
      // reordering happens in #rApply, then renumberOrder() keeps numbers
      // contiguous (1..N) for next interaction.
      const orderCell = `<input type="number" min="1" max="${ALL_COLS.length}" data-col="${c.key}" data-prop="order" value="${s.order || ''}" class="r-order-input" title="Poziția în raport (1..${ALL_COLS.length}). Aplică-se la „Aplică".">`;
      // Sort direction button (cycles: none → asc → desc → none). Disabled when col is hidden.
      const sortLabel = s.sortDir === 'asc' ? '↑ ASC' : s.sortDir === 'desc' ? '↓ DESC' : '—';
      const sortCell = `<button type="button" class="r-sort-btn${s.sortDir && s.sortDir !== 'none' ? ' r-sort-active' : ''}" data-sort-col="${c.key}"${!s.visible ? ' disabled' : ''}>${sortLabel}</button>`;
      return `<tr data-key="${c.key}" draggable="true">
        <td><span class="r-drag-handle" title="Trage pentru a reordona">⋮⋮</span>${esc(c.label)}${s.value ? ' <span style="color:var(--text-muted);font-size:.75rem">Σ</span>' : ''}${isVirtual ? ' <span style="color:var(--text-muted);font-size:.75rem">(virtual)</span>' : ''}</td>
        <td>${orderCell}</td>
        <td><input type="checkbox" data-col="${c.key}" data-prop="visible"${s.visible ? ' checked' : ''}></td>
        <td>${filterCell}</td>
        <td>${groupCell}</td>
        <td>${subtotalCell}</td>
        <td>${valueCell}</td>
        <td>${sortCell}</td>
      </tr>`;
    }).join('');

    root.innerHTML = `
      <div class="page-head">
        <h1>Raport dinamic<button type="button" class="page-help-btn" id="rPageHelpBtn" title="Ce poți face pe această pagină" aria-label="Ajutor pentru această pagină">i</button></h1>
        <div class="page-help-popover" id="rPageHelpPopover" hidden role="dialog" aria-labelledby="rPageHelpTitle">
          <div class="page-help-popover-head">
            <span class="page-help-popover-title" id="rPageHelpTitle">Cum folosești pagina</span>
            <button type="button" class="page-help-popover-close" id="rPageHelpClose" aria-label="Închide">×</button>
          </div>
          <div class="page-help-popover-body">
            Bifează coloanele vizibile, aplică filtre și opțional grupează. <b>Σ</b> marchează coloanele numerice sumabile.
          </div>
        </div>
      </div>

      <div class="card r-config">
        <details ${_detailsOpen.parent ? 'open' : ''} data-section="parent">
          <summary>🔧 Configurare raport</summary>
          <div class="r-config-grid">
        <details ${_detailsOpen.config ? 'open' : ''} data-section="config">
          <summary>⚙️ Configurare coloane (${ALL_COLS.length} disponibile)</summary>
          <div class="r-cols-search">
            <input type="text" id="rColSearch" placeholder="Caută coloană…" value="${esc(_colSearch || '')}" autocomplete="off" spellcheck="false">
            <button type="button" id="rColSearchClear" class="r-cols-search-clear" title="Șterge filtrul" aria-label="Șterge filtrul"${_colSearch ? '' : ' hidden'}>×</button>
          </div>
          <div class="r-cols-table-wrap">
            <table class="r-cols-table">
              <thead>
                <tr>
                  <th>Coloană</th>
                  <th class="r-col-actions">
                    <div class="r-col-title">Ordine</div>
                    <div class="r-mini-actions">
                      <button type="button" class="r-mini-btn" id="rOrderAlpha" title="Resetează la ordinea alfabetică">A→Z</button>
                    </div>
                  </th>
                  <th class="r-col-actions">
                    <div class="r-col-title">Vizibil</div>
                    <div class="r-mini-actions">
                      <button type="button" class="r-mini-btn" id="rSelectAll" title="Bifează toate">toate</button>
                      <span class="r-mini-sep">·</span>
                      <button type="button" class="r-mini-btn" id="rDeselectAll" title="Debifează toate">niciuna</button>
                    </div>
                  </th>
                  <th>Filtru</th>
                  <th class="r-col-actions">
                    <div class="r-col-title">Grupează</div>
                    <div class="r-mini-actions">
                      <button type="button" class="r-mini-btn" id="rGroupAll" title="Grupează toate">toate</button>
                      <span class="r-mini-sep">·</span>
                      <button type="button" class="r-mini-btn" id="rGroupNone" title="Anulează gruparea">niciuna</button>
                    </div>
                  </th>
                  <th class="r-col-actions">
                    <div class="r-col-title">Subtotal</div>
                    <div class="r-mini-actions">
                      <button type="button" class="r-mini-btn" id="rSubAll" title="Subtotal pentru toate cele grupate">toate</button>
                      <span class="r-mini-sep">·</span>
                      <button type="button" class="r-mini-btn" id="rSubNone" title="Niciun subtotal">niciuna</button>
                    </div>
                  </th>
                  <th class="r-col-actions">
                    <div class="r-col-title">Valoare</div>
                    <div class="r-mini-actions">
                      <button type="button" class="r-mini-btn" id="rValAll" title="Marchează ca valoare toate coloanele numerice">toate</button>
                      <span class="r-mini-sep">·</span>
                      <button type="button" class="r-mini-btn" id="rValNone" title="Demarchează toate">niciuna</button>
                    </div>
                  </th>
                  <th class="r-col-actions">
                    <div class="r-col-title">Sortare</div>
                    <div class="r-mini-actions">
                      <button type="button" class="r-mini-btn" id="rSortAsc" title="ASC pe toate coloanele vizibile">↑ toate</button>
                      <span class="r-mini-sep">·</span>
                      <button type="button" class="r-mini-btn" id="rSortNone" title="Anulează sortarea">niciuna</button>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
        </details>

        <details ${_detailsOpen.saved ? 'open' : ''} data-section="saved" class="r-saved-details">
          <summary>💾 Configurații salvate (${_savedConfigs.length})</summary>

          <div class="r-saved-create admin-only">
            <input type="text" id="rSavedName" placeholder="Numele noii configurații..." maxlength="80" />
            <button type="button" class="btn btn-primary" id="rSaveCurrent" title="Salvează state-ul curent ca raport nou (cere nume)">💾 New</button>
            ${_lastAppliedConfig && _savedConfigs.find(c => c.name === _lastAppliedConfig)
              ? `<button type="button" class="btn btn-secondary r-save-existing" id="rSaveExisting" title="Suprascrie „${esc(_lastAppliedConfig)}" cu modificările curente">↻ Update <span class="r-save-existing-name">„${esc(_lastAppliedConfig)}"</span></button>`
              : ''}
          </div>

          ${_savedConfigs.length === 0
            ? '<div class="empty" style="padding:1rem">Nu există configurații salvate.</div>'
            : `<div class="r-saved-table-wrap">
                <table class="r-saved-table data-table">
                  <thead><tr>
                    <th class="r-default-col" title="Raport implicit (auto-aplicat la încărcarea paginii)">★</th>
                    <th class="r-sort-h" data-sort-h="name">Nume${_savedSort.key === 'name' ? ` <span class="r-sort-arr">${_savedSort.dir === 'asc' ? '▲' : '▼'}</span>` : ''}</th>
                    <th class="num r-sort-h" data-sort-h="visN">Coloane${_savedSort.key === 'visN' ? ` <span class="r-sort-arr">${_savedSort.dir === 'asc' ? '▲' : '▼'}</span>` : ''}</th>
                    <th class="num r-sort-h" data-sort-h="filtN">Filtre${_savedSort.key === 'filtN' ? ` <span class="r-sort-arr">${_savedSort.dir === 'asc' ? '▲' : '▼'}</span>` : ''}</th>
                    <th class="num r-sort-h" data-sort-h="grpN">Grupări${_savedSort.key === 'grpN' ? ` <span class="r-sort-arr">${_savedSort.dir === 'asc' ? '▲' : '▼'}</span>` : ''}</th>
                    <th class="r-sort-h" data-sort-h="updatedAt">Actualizat${_savedSort.key === 'updatedAt' ? ` <span class="r-sort-arr">${_savedSort.dir === 'asc' ? '▲' : '▼'}</span>` : ''}</th>
                    <th class="num" style="width:180px">Acțiuni</th>
                  </tr></thead>
                  <tbody id="rSavedBody">
                    ${(() => {
                      // Compute derived stats per row first so sorting can use them.
                      const rows = _savedConfigs.map(c => {
                        const cols = Array.isArray(c.state && c.state.cols) ? c.state.cols : [];
                        return {
                          c,
                          visN:  cols.filter(x => x.visible).length,
                          filtN: cols.filter(x => x.filter).length,
                          grpN:  cols.filter(x => x.group).length,
                          name:  c.name || '',
                          updatedAt: c.updatedAt || ''
                        };
                      });
                      const k = _savedSort.key, d = _savedSort.dir === 'asc' ? 1 : -1;
                      rows.sort((a, b) => {
                        const av = a[k], bv = b[k];
                        if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * d;
                        return String(av).localeCompare(String(bv), 'ro') * d;
                      });
                      return rows.map(({ c, visN, filtN, grpN }) => {
                        const updated = (c.updatedAt || '').slice(0, 16).replace('T', ' ');
                        const isDef = !!c.isDefault;
                        return `<tr data-id="${esc(c.id)}"${isDef ? ' class="r-default-row"' : ''}>
                          <td class="r-default-col"><button class="r-default-btn" data-act="default" title="${isDef ? 'Elimină marcaj implicit' : 'Setează ca raport implicit'}" aria-pressed="${isDef ? 'true' : 'false'}">${isDef ? '★' : '☆'}</button></td>
                          <td><b>${esc(c.name)}</b>${isDef ? ' <span class="r-default-tag" title="Raport implicit">implicit</span>' : ''}</td>
                          <td class="num">${visN}</td>
                          <td class="num">${filtN}</td>
                          <td class="num">${grpN}</td>
                          <td><span style="font-size:.78rem;color:var(--text-muted)">${esc(updated)}</span></td>
                          <td class="num">
                            <button class="btn-icon" data-act="apply"  title="Aplică această configurație">🔄</button>
                            <button class="btn-icon" data-act="edit"   title="Redenumește">✏️</button>
                            <button class="btn-icon" data-act="delete" title="Șterge">🗑️</button>
                          </td>
                        </tr>`;
                      }).join('');
                    })()}
                  </tbody>
                </table>
              </div>`}
        </details>
          </div><!-- /.r-config-grid -->
        </details><!-- /parent -->

        <div class="r-actions">
          <button class="btn btn-primary" id="rApply">🔄 Aplică</button>
          <button class="btn" id="rReset" title="Comută afișarea valorilor duplicate consecutive">↺ ${state.compact ? 'Afișează duplicate' : 'Compactează'}</button>
          <button class="btn" id="rExportXls">📥 Export Excel</button>
        </div>
      </div>

      <div class="card r-results" id="rResults" style="margin-top:1rem">
        <div class="empty">Apasă "Aplică" pentru a genera raportul.</div>
      </div>
    `;

    // Bind config changes
    root.querySelectorAll('[data-col]').forEach(el => {
      el.onchange = () => {
        const key = el.dataset.col;
        const prop = el.dataset.prop;
        const s = state.cols.find(x => x.key === key);
        if (prop === 'order') {
          // Move the edited column to the user-typed position. Splice-based
          // approach (instead of sort-by-order) so the just-edited column
          // always wins over any column already at that position — fixes
          // the tie-breaker bug where typing "1" would leave the original
          // first column at 1 and bump the edited one to 2.
          const n = parseInt(el.value, 10);
          const fromIdx = state.cols.findIndex(c => c.key === key);
          if (fromIdx < 0) return;
          // Clamp target position to [1, N]
          const targetPos = Math.max(1, Math.min(state.cols.length,
            Number.isFinite(n) && n > 0 ? n : 1));
          // Remove the col from its current position, then insert at the
          // target index. This pushes any column at that slot one down.
          const item = state.cols.splice(fromIdx, 1)[0];
          state.cols.splice(targetPos - 1, 0, item);
          renumberOrder();
          state.activeConfigName = null;
          buildUI();
          return;
        }
        s[prop] = el.type === 'checkbox' ? el.checked : el.value;
        // Any manual change diverges from the saved snapshot — clear the
        // active marker so the dropdown shows "Custom" on next render.
        state.activeConfigName = null;
        // When unsetting group, also clear subtotal (keeping state coherent)
        if (prop === 'group') {
          if (!s.group) s.subtotal = false;
          buildUI();   // rebuild to update subtotal checkbox disabled state
        }
        // When toggling 'value', rebuild so Σ marker updates next to col name
        if (prop === 'value') buildUI();
        // When unsetting visible, also clear sort direction (sort needs visible col)
        if (prop === 'visible') {
          if (!s.visible) s.sortDir = 'none';
          buildUI();   // rebuild to update sort button disabled state
        }
        // Filter dropdown — toggle active style live (avoid rebuild, keep focus)
        if (prop === 'filter') {
          el.classList.toggle('r-filter-active', !!s.filter);
        }
      };
    });

    // Per-column sort cycle buttons (none → asc → desc → none)
    root.querySelectorAll('.r-sort-btn[data-sort-col]').forEach(btn => {
      btn.onclick = (e) => {
        e.preventDefault();
        const key = btn.dataset.sortCol;
        const s = state.cols.find(x => x.key === key);
        if (!s || !s.visible) return;
        const cycle = { 'none': 'asc', 'asc': 'desc', 'desc': 'none' };
        s.sortDir = cycle[s.sortDir || 'none'];
        state.activeConfigName = null;   // manual edit
        buildUI();
      };
    });

    $('#rApply').onclick = () => {
      // Reorder cols based on user-typed `order` numbers — stable sort with
      // ties broken by original array position. Then renumber to keep the
      // visible numbers contiguous (1..N) for next interaction.
      state.cols.sort((a, b) => (a.order || 999) - (b.order || 999));
      renumberOrder();
      // Apply triggers auto-sort: clear manual sortKey so render() falls back to
      // multi-key sort across visible non-numeric columns
      state.sortKey = null;
      buildUI();   // rebuild configurator to reflect the new column order
      render();
    };
    $('#rReset').onclick = () => {
      // Toggle suppression of repeating non-numeric values
      state.compact = !state.compact;
      state.activeConfigName = null;   // compact is part of saved snapshot
      buildUI();   // rebuild to update button label
    };
    $('#rSelectAll').onclick = (e) => {
      e.preventDefault();
      state.cols.forEach(c => c.visible = true);
      state.activeConfigName = null;
      buildUI();
    };
    // Reset column array to alphabetical order (by label, RO locale).
    // Acts on the array; renumberOrder() then makes order numbers 1..N.
    $('#rOrderAlpha').onclick = (e) => {
      e.preventDefault();
      state.cols.sort((a, b) => {
        const la = colByKey[a.key]?.label || '';
        const lb = colByKey[b.key]?.label || '';
        return la.localeCompare(lb, 'ro');
      });
      renumberOrder();
      state.activeConfigName = null;
      buildUI();
    };
    $('#rDeselectAll').onclick = (e) => {
      e.preventDefault();
      state.cols.forEach(c => { c.visible = false; c.sortDir = 'none'; });
      state.activeConfigName = null;
      buildUI();
    };
    $('#rGroupAll').onclick = (e) => {
      e.preventDefault();
      // Group makes sense only for non-numeric, non-virtual columns
      state.cols.forEach(c => {
        const def = colByKey[c.key];
        if (def && def.type !== 'num' && !def.virtual) c.group = true;
      });
      state.activeConfigName = null;
      buildUI();
    };
    $('#rGroupNone').onclick = (e) => {
      e.preventDefault();
      state.cols.forEach(c => { c.group = false; c.subtotal = false; });
      state.activeConfigName = null;
      buildUI();
    };
    $('#rSubAll').onclick = (e) => {
      e.preventDefault();
      // Subtotal only valid where group is on
      state.cols.forEach(c => { if (c.group) c.subtotal = true; });
      state.activeConfigName = null;
      buildUI();
    };
    $('#rSubNone').onclick = (e) => {
      e.preventDefault();
      state.cols.forEach(c => c.subtotal = false);
      state.activeConfigName = null;
      buildUI();
    };
    $('#rValAll').onclick = (e) => {
      e.preventDefault();
      // Value (measure) only valid for numeric, non-virtual columns
      state.cols.forEach(c => {
        const def = colByKey[c.key];
        if (def && def.type === 'num' && !def.virtual) c.value = true;
      });
      state.activeConfigName = null;
      buildUI();
    };
    $('#rValNone').onclick = (e) => {
      e.preventDefault();
      state.cols.forEach(c => c.value = false);
      state.activeConfigName = null;
      buildUI();
    };
    $('#rSortAsc').onclick = (e) => {
      e.preventDefault();
      // Set ASC on all visible columns
      state.cols.forEach(c => { if (c.visible) c.sortDir = 'asc'; });
      state.activeConfigName = null;
      buildUI();
    };
    $('#rSortNone').onclick = (e) => {
      e.preventDefault();
      state.cols.forEach(c => c.sortDir = 'none');
      state.activeConfigName = null;
      buildUI();
    };
    $('#rExportXls').onclick = exportToExcel;

    // ---- Saved configurations: toggle + create + per-row actions ----
    // Persist <details> open/closed state across buildUI rebuilds
    root.querySelectorAll('details[data-section]').forEach(d => {
      d.addEventListener('toggle', () => {
        _detailsOpen[d.dataset.section] = d.open;
      });
    });

    // Save current configuration (admin-only)
    const saveBtn  = document.getElementById('rSaveCurrent');
    const nameEl   = document.getElementById('rSavedName');
    if (saveBtn && nameEl) {
      saveBtn.onclick = async () => {
        await saveCurrentConfig(nameEl.value);
      };
      // Pressing Enter in the name input triggers save
      nameEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); saveBtn.click(); }
      });
    }
    // Save changes back to the last-applied config (admin-only). Only
    // present in DOM when there's a config to save into.
    const saveExistingBtn = document.getElementById('rSaveExisting');
    if (saveExistingBtn) {
      saveExistingBtn.onclick = async () => {
        await saveToLastAppliedConfig();
      };
    }

    // Per-row actions on the saved-configs table
    root.querySelectorAll('#rSavedBody [data-act]').forEach(btn => {
      btn.onclick = async () => {
        const tr = btn.closest('tr');
        if (!tr) return;
        const id = tr.dataset.id;
        const act = btn.dataset.act;
        if (act === 'apply')   { applyConfig(_savedConfigs.find(c => c.id === id)); return; }
        if (act === 'edit')    { await renameConfig(id); return; }
        if (act === 'delete')  { await deleteConfig(id); return; }
        if (act === 'default') { await setDefaultConfig(id); return; }
      };
    });

    // Sortable headers on the saved-configs table — click a header to cycle
    // its sort direction (asc → desc) on that key. Switching keys resets to
    // asc (or desc for the date column, which feels more natural).
    root.querySelectorAll('.r-sort-h[data-sort-h]').forEach(th => {
      th.onclick = () => {
        const k = th.dataset.sortH;
        if (_savedSort.key === k) {
          _savedSort.dir = _savedSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
          _savedSort.key = k;
          _savedSort.dir = (k === 'updatedAt') ? 'desc' : 'asc';
        }
        buildUI();
      };
    });

    // Drag & drop reorder of column rows
    let dragKey = null;
    root.querySelectorAll('.r-cols-table tbody tr').forEach(tr => {
      tr.ondragstart = (e) => {
        dragKey = tr.dataset.key;
        tr.classList.add('r-dragging');
        e.dataTransfer.effectAllowed = 'move';
        // Firefox needs setData to actually start drag
        try { e.dataTransfer.setData('text/plain', dragKey); } catch (_) {}
      };
      tr.ondragend = () => {
        tr.classList.remove('r-dragging');
        root.querySelectorAll('.r-cols-table tbody tr').forEach(t => t.classList.remove('r-drop-above', 'r-drop-below'));
        dragKey = null;
      };
      tr.ondragover = (e) => {
        if (!dragKey || tr.dataset.key === dragKey) return;
        e.preventDefault();
        const rect = tr.getBoundingClientRect();
        const above = e.clientY < rect.top + rect.height / 2;
        tr.classList.toggle('r-drop-above', above);
        tr.classList.toggle('r-drop-below', !above);
      };
      tr.ondragleave = () => {
        tr.classList.remove('r-drop-above', 'r-drop-below');
      };
      tr.ondrop = (e) => {
        e.preventDefault();
        const targetKey = tr.dataset.key;
        if (!dragKey || targetKey === dragKey) return;
        const above = tr.classList.contains('r-drop-above');
        const fromIdx = state.cols.findIndex(c => c.key === dragKey);
        if (fromIdx < 0) return;
        const item = state.cols.splice(fromIdx, 1)[0];
        let toIdx = state.cols.findIndex(c => c.key === targetKey);
        if (toIdx < 0) toIdx = state.cols.length;
        if (!above) toIdx += 1;
        state.cols.splice(toIdx, 0, item);
        renumberOrder();   // sync order field with new positions
        state.activeConfigName = null;   // column order is part of saved snapshot
        buildUI();
      };
    });

    // ---- Column search box ----
    // Filters rows in the configurator by label or key, case-insensitive.
    // Uses display:none toggling instead of rebuilding the table, so the
    // input keeps focus while the user types. The query persists in
    // _colSearch so it survives buildUI rebuilds (triggered by checkbox
    // toggles, resort, drag-drop, etc.).
    const searchEl = document.getElementById('rColSearch');
    const clearBtn = document.getElementById('rColSearchClear');
    if (searchEl) {
      searchEl.oninput = () => {
        _colSearch = searchEl.value;
        if (clearBtn) clearBtn.hidden = !_colSearch;
        applyColSearch();
      };
    }
    if (clearBtn) {
      clearBtn.onclick = () => {
        _colSearch = '';
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        applyColSearch();
      };
    }
    // Apply the current filter to the freshly-rendered rows.
    applyColSearch();

    // Initial render
    render();
  }

  // Hide table rows that don't match _colSearch. Doesn't touch state.cols —
  // search is a pure visual filter; ordering/resort still use the full set.
  function applyColSearch() {
    const q = (_colSearch || '').trim().toLowerCase();
    const tbody = document.querySelector('.r-cols-table tbody');
    if (!tbody) return;
    let visible = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      if (!q) { tr.style.display = ''; visible++; return; }
      const key = (tr.dataset.key || '').toLowerCase();
      // First td contains the column label (plus the drag handle char)
      const labelTd = tr.querySelector('td');
      const label = (labelTd?.textContent || '').toLowerCase();
      const match = label.includes(q) || key.includes(q);
      tr.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    // Update summary count visually so the user knows N matches
    const summary = document.querySelector('details[data-section="config"] > summary');
    if (summary) {
      const baseTxt = `⚙️ Configurare coloane (${ALL_COLS.length} disponibile`;
      summary.textContent = q
        ? `${baseTxt} · ${visible} potrivite cu „${_colSearch}")`
        : `${baseTxt})`;
    }
  }

  function getOutputData() {
    // Step 1: filter rows (skip virtual cols, they have no source value)
    const activeFilters = state.cols.filter(c => c.filter !== '' && !colByKey[c.key]?.virtual);
    const filtered = ds.filter(r => {
      for (const f of activeFilters) {
        if (String(r[f.key]) !== String(f.filter)) return false;
      }
      return true;
    });

    const visibleCols = state.cols.filter(c => c.visible).map(c => colByKey[c.key]);
    const groupKeys   = state.cols.filter(c => c.group && !colByKey[c.key]?.virtual).map(c => c.key);
    // Aggregable measure columns: bifate ca 'value' în config, numerice și ne-virtuale
    const sumableKeys = visibleCols
      .filter(c => c.type === 'num' && !c.virtual)
      .filter(c => state.cols.find(s => s.key === c.key)?.value)
      .map(c => c.key);

    if (groupKeys.length === 0) {
      // No grouping — project visible cols; _count = 1 per row (so NrDoc still works as a count)
      const rows = filtered.map(r => {
        const o = { _count: 1 };
        visibleCols.forEach(c => { if (c.key !== '_count') o[c.key] = r[c.key]; });
        return o;
      });
      return { cols: visibleCols, rows, isGrouped: false, sumableKeys };
    }

    // Group by groupKeys, sum sumable cols, count rows into _count
    const map = new Map();
    filtered.forEach(r => {
      const k = groupKeys.map(gk => String(r[gk] ?? '')).join('|||');
      if (!map.has(k)) {
        const o = { _count: 0 };
        groupKeys.forEach(gk => o[gk] = r[gk]);
        sumableKeys.forEach(sk => { if (sk !== '_count') o[sk] = 0; });
        map.set(k, o);
      }
      const e = map.get(k);
      e._count += 1;
      sumableKeys.forEach(sk => { if (sk !== '_count') e[sk] = (e[sk] || 0) + (Number(r[sk]) || 0); });
    });
    return { cols: visibleCols, rows: [...map.values()], isGrouped: true, sumableKeys, groupKeys };
  }

  function fmtCell(c, v) {
    if (v === null || v === undefined || v === '') return '';
    if (c.type === 'date') return fmtDate(v);
    if (c.type === 'num' && c.sumable) return fmtNum(v);
    if (c.type === 'num') return esc(v);
    return esc(v);
  }

  function render() {
    const out = getOutputData();
    const resEl = $('#rResults');
    if (out.cols.length === 0) {
      resEl.innerHTML = '<div class="empty">Bifează cel puțin o coloană vizibilă.</div>';
      return;
    }
    if (out.rows.length === 0) {
      resEl.innerHTML = '<div class="empty">Niciun rezultat pentru filtrele aplicate.</div>';
      return;
    }

    // Compute group order (for sort and subtotal injection)
    // Order = order in state.cols where group=true (and not virtual)
    const groupOrder = state.cols
      .filter(c => c.group && !colByKey[c.key]?.virtual)
      .map(c => c.key);
    const subtotalKeys = state.cols
      .filter(c => c.group && c.subtotal && !colByKey[c.key]?.virtual)
      .map(c => c.key);

    // Apply sort: manual (click on header) takes precedence; otherwise auto-sort
    // multi-key. Auto-sort prefixes the group keys (so subtotals can be inserted)
    // and chains the remaining visible non-numeric columns.
    const sortCol = out.cols.find(c => c.key === state.sortKey);
    if (sortCol || state.sortKey === '_count') {
      const dir = state.sortDir === 'asc' ? 1 : -1;
      // For numeric columns, coerce to Number so string-stored numbers
      // (e.g. "10" "2") still sort numerically.
      const sortColDef = state.sortKey === '_count'
        ? { type: 'num' }
        : colByKey[state.sortKey];
      const isNumSort = sortColDef && sortColDef.type === 'num';
      out.rows.sort((a, b) => {
        let va = a[state.sortKey];
        let vb = b[state.sortKey];
        if (va == null && vb == null) return 0;
        if (va == null) return 1;
        if (vb == null) return -1;
        if (isNumSort) {
          const na = Number(va), nb = Number(vb);
          if (!isNaN(na) && !isNaN(nb)) return (na - nb) * dir;
        }
        if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
        return String(va).localeCompare(String(vb)) * dir;
      });
    } else {
      // Auto-sort: priority order =
      //  1. Per-column sort directions set in config (in visible left-to-right order)
      //  2. Fallback: prefix on group keys, then on remaining non-num visible cols
      const configSorts = state.cols
        .filter(c => c.visible && c.sortDir && c.sortDir !== 'none')
        .map(c => ({ key: c.key, dir: c.sortDir }));

      let sortKeys, dirByKey;
      if (configSorts.length > 0) {
        sortKeys = configSorts.map(s => s.key);
        dirByKey = Object.fromEntries(configSorts.map(s => [s.key, s.dir]));
      } else {
        const tail = out.cols
          .filter(c => c.type !== 'num' && !groupOrder.includes(c.key))
          .map(c => c.key);
        sortKeys = [...groupOrder, ...tail];
        dirByKey = {};   // all default asc (fallback)
      }

      if (sortKeys.length > 0) {
        out.rows.sort((a, b) => {
          for (const k of sortKeys) {
            const va = a[k], vb = b[k];
            const dirMul = (dirByKey[k] === 'desc') ? -1 : 1;
            if (va == null && vb == null) continue;
            if (va == null) return 1 * dirMul;
            if (vb == null) return -1 * dirMul;
            const def = colByKey[k];
            if (def && def.type === 'num') {
              const na = Number(va), nb = Number(vb);
              if (!isNaN(na) && !isNaN(nb)) {
                if (na !== nb) return (na < nb ? -1 : 1) * dirMul;
                continue;
              }
            }
            const cmp = String(va).localeCompare(String(vb));
            if (cmp !== 0) return cmp * dirMul;
          }
          return 0;
        });
      }
    }

    // Header sort icon: manual sort (state.sortKey) wins; otherwise show
    // config-driven multi-sort with index (↑1, ↓2 etc.)
    const configSortOrder = state.cols
      .filter(c => c.visible && c.sortDir && c.sortDir !== 'none')
      .map(c => c.key);
    const sortIcon = (k) => {
      if (state.sortKey === k) return state.sortDir === 'asc' ? ' ↑' : ' ↓';
      const idx = configSortOrder.indexOf(k);
      if (idx >= 0) {
        const cs = state.cols.find(c => c.key === k);
        const arrow = cs.sortDir === 'asc' ? '↑' : '↓';
        return ` ${arrow}${configSortOrder.length > 1 ? (idx + 1) : ''}`;
      }
      return '';
    };

    // Header
    let head = '<tr>';
    out.cols.forEach(c => {
      const cls = c.type === 'num' ? 'num' : '';
      head += `<th class="${cls}" data-sort-key="${c.key}">${esc(c.label)}${sortIcon(c.key)}</th>`;
    });
    head += '</tr>';

    // Set of column keys that are aggregable measures (user-marked as 'value')
    const valueKeys = new Set(out.sumableKeys);

    // Build interleaved render list with subtotal markers.
    // For each subtotal-enabled group level, emit a subtotal row when its
    // value changes (compared to previous data row). Subtotals cascade from
    // deepest (last-added group key) to shallowest at each break point.
    const sumKeys = [...valueKeys];
    const renderItems = []; // { type: 'data', row } or { type: 'subtotal', level, ... }
    if (subtotalKeys.length === 0) {
      // No subtotals — just data rows in order
      out.rows.forEach(r => renderItems.push({ type: 'data', row: r }));
    } else {
      // Accumulators per level (index aligned with groupOrder)
      const acc = groupOrder.map(() => ({ sums: {}, count: 0 }));
      const resetLevel = (lvl) => { acc[lvl] = { sums: {}, count: 0 }; };
      let prevRow = null;
      out.rows.forEach((r, i) => {
        if (prevRow) {
          // Find topmost level where the group key changed
          let breakLvl = -1;
          for (let lvl = 0; lvl < groupOrder.length; lvl++) {
            if (r[groupOrder[lvl]] !== prevRow[groupOrder[lvl]]) { breakLvl = lvl; break; }
          }
          if (breakLvl >= 0) {
            // Emit subtotals from deepest to breakLvl (inclusive)
            for (let lvl = groupOrder.length - 1; lvl >= breakLvl; lvl--) {
              if (subtotalKeys.includes(groupOrder[lvl])) {
                renderItems.push({
                  type: 'subtotal', level: lvl,
                  groupKey: groupOrder[lvl],
                  label: prevRow[groupOrder[lvl]],
                  sums: { ...acc[lvl].sums }, count: acc[lvl].count
                });
              }
              resetLevel(lvl);
            }
          }
        }
        // Accumulate this row in all levels
        const cnt = (Number(r._count) || 1);
        sumKeys.forEach(k => {
          const v = Number(r[k]) || 0;
          for (let lvl = 0; lvl < groupOrder.length; lvl++) {
            acc[lvl].sums[k] = (acc[lvl].sums[k] || 0) + v;
          }
        });
        for (let lvl = 0; lvl < groupOrder.length; lvl++) acc[lvl].count += cnt;
        renderItems.push({ type: 'data', row: r });
        prevRow = r;
      });
      // Emit final subtotals (after last data row)
      if (prevRow) {
        for (let lvl = groupOrder.length - 1; lvl >= 0; lvl--) {
          if (subtotalKeys.includes(groupOrder[lvl])) {
            renderItems.push({
              type: 'subtotal', level: lvl,
              groupKey: groupOrder[lvl],
              label: prevRow[groupOrder[lvl]],
              sums: { ...acc[lvl].sums }, count: acc[lvl].count
            });
          }
        }
      }
    }

    // Suppress repeating values: applied per-row using break-on-parent semantics.
    // Rules:
    //  - If a DIMENSION column to the left changes value vs previous row, then
    //    THAT column AND all dimension columns to its right must show their
    //    values (no suppress), regardless of whether they match the previous row.
    //  - MEASURE columns (marked as 'Valoare') and virtual columns (_count) are
    //    never suppressed (always show). Numeric columns NOT marked as value
    //    are treated as dimensions — they ARE subject to suppress.
    //  - A subtotal row resets the previous-row tracker → first data row after a
    //    subtotal always shows all values.
    const suppressed = new Map(); // itemIdx -> Set of colKeys to blank
    if (state.compact) {
      let prev = null;
      renderItems.forEach((it, i) => {
        if (it.type === 'subtotal') { prev = null; return; }
        const r = it.row;
        if (prev) {
          let breakHit = false;
          out.cols.forEach(c => {
            if (c.virtual || valueKeys.has(c.key)) return;  // measures + virtuals never suppressed
            if (!breakHit && r[c.key] !== prev[c.key]) breakHit = true;
            if (!breakHit) {
              // Same as previous AND no break to the left → suppress this cell
              if (r[c.key] === prev[c.key]) {
                if (!suppressed.has(i)) suppressed.set(i, new Set());
                suppressed.get(i).add(c.key);
              }
            }
            // If breakHit, don't suppress (force show)
          });
        }
        prev = r;
      });
    }

    // Body: render data rows + subtotal rows
    const body = renderItems.map((it, i) => {
      if (it.type === 'subtotal') {
        let html = `<tr class="r-subtotal r-subtotal-l${it.level}">`;
        out.cols.forEach(c => {
          const cls = c.type === 'num' ? 'num' : '';
          if (c.key === it.groupKey) {
            html += `<td><b>Subtotal: ${esc(it.label ?? '')}</b></td>`;
          } else if (valueKeys.has(c.key)) {
            html += `<td class="${cls}"><b>${fmtNum(it.sums[c.key] || 0)}</b></td>`;
          } else if (c.virtual && c.key === '_count') {
            html += `<td class="num"><b>${it.count}</b></td>`;
          } else {
            html += `<td></td>`;
          }
        });
        return html + '</tr>';
      }
      // data row
      const r = it.row;
      let html = '<tr>';
      const sup = suppressed.get(i);
      out.cols.forEach(c => {
        const cls = c.type === 'num' ? 'num' : '';
        const v = c.key === '_count' ? r._count : r[c.key];
        const cell = sup && sup.has(c.key) ? '' : fmtCell(c, v);
        html += `<td class="${cls}">${cell}</td>`;
      });
      return html + '</tr>';
    }).join('');

    // Footer with totals
    let foot = '<tr class="cz-total-yellow cz-total-emph">';
    let labelPlaced = false;
    out.cols.forEach((c) => {
      const cls = c.type === 'num' ? 'num' : '';
      if (valueKeys.has(c.key)) {
        const sum = out.rows.reduce((s, r) => s + (Number(r[c.key]) || 0), 0);
        foot += `<td class="${cls}"><b>${fmtNum(sum)}</b></td>`;
      } else if (c.virtual && c.key === '_count') {
        // Virtual count column: show grand total of documents
        const sum = out.rows.reduce((s, r) => s + (Number(r._count) || 0), 0);
        foot += `<td class="num"><b>${sum}</b></td>`;
      } else if (!labelPlaced) {
        foot += `<td><b>TOTAL${out.isGrouped ? ` (${out.rows.length} grupuri)` : ` (${out.rows.length} rd.)`}</b></td>`;
        labelPlaced = true;
      } else {
        foot += '<td></td>';
      }
    });
    foot += '</tr>';

    // Build the saved-config quick selector — replaces the old summary at
    // the top of the results. The currently applied saved config (if any)
    // is shown selected; "Custom" means the snapshot doesn't match any
    // saved config (or the user just edited columns/filters manually).
    const activeName = state.activeConfigName || '';
    const cfgOptions = _savedConfigs
      .map(c => `<option value="${esc(c.id)}"${c.name === activeName ? ' selected' : ''}>${esc(c.name)}</option>`)
      .join('');
    const noCfgs = _savedConfigs.length === 0;

    // Active filter chips — shows each "Coloană: Valoare" applied as a
    // filter, with × to clear it. Helps users see at a glance which
    // selections shape the report below. Skips virtual cols (no source
    // value to filter on) and empty filters.
    const activeFilters = state.cols
      .filter(c => c.filter !== '' && !colByKey[c.key]?.virtual)
      .map(c => ({
        key:   c.key,
        label: colByKey[c.key]?.label || c.key,
        value: c.filter
      }));
    // The info button + popover live to the right of the selector. Button
    // shows count badge; popover lists each "Coloană: Valoare" with × to
    // clear it. Hidden entirely when no filters are active.
    const filtersInfoHtml = activeFilters.length === 0 ? '' : `
      <div class="r-filters-info">
        <button type="button" class="r-filters-info-btn" id="rFiltersInfoBtn" title="${activeFilters.length} filtru/filtre active — click pentru detalii">
          <span class="r-filters-info-icon">i</span>
          <span class="r-filters-info-count">${activeFilters.length}</span>
          <span class="r-filters-info-label">${activeFilters.length === 1 ? 'filtru' : 'filtre'}</span>
        </button>
        <div class="r-filters-info-popover" id="rFiltersInfoPopover" hidden>
          <div class="r-filters-info-popover-head">
            <span class="r-filters-info-popover-title">Filtre active</span>
            <button type="button" class="r-filters-clear-all" id="rFiltersClearAll" title="Elimină toate filtrele">curăță toate</button>
          </div>
          <div class="r-filters-info-popover-body">
            ${activeFilters.map(f => `
              <div class="r-filter-row">
                <span class="r-filter-row-label">${esc(f.label)}:</span>
                <span class="r-filter-row-value" title="${esc(f.value)}">${esc(f.value)}</span>
                <button type="button" class="r-filter-chip-clear" data-col="${esc(f.key)}" title="Elimină filtrul">×</button>
              </div>`).join('')}
          </div>
        </div>
      </div>`;

    resEl.innerHTML = `
      <div class="r-active-report">
        <select class="r-ar-select" id="rActiveSelect"${noCfgs ? ' disabled title="Nu există configurații salvate"' : ''}>
          <option value=""${activeName ? '' : ' selected'}>${noCfgs ? '— nicio configurație salvată —' : '— Custom (neselectat) —'}</option>
          ${cfgOptions}
        </select>
        ${filtersInfoHtml}
      </div>
      <div class="r-table-wrap">
        <table class="cz-pivot r-table">
          <thead>${head}</thead>
          <tbody>${body}</tbody>
          <tfoot>${foot}</tfoot>
        </table>
      </div>
      <div class="r-summary-bottom">
        <b>${out.rows.length.toLocaleString('ro-RO')}</b> ${out.isGrouped ? 'grupuri' : 'rânduri'} afișate
        <span class="sep">·</span>
        din <b>${ds.length.toLocaleString('ro-RO')}</b> înregistrări totale${activeFilterCount() ? `<span class="sep">·</span> ${activeFilterCount()} filtru/filtre active` : ''}
      </div>
    `;

    // Wire the quick-selector: changing the dropdown applies that config
    // (or, if cleared to "Custom", just clears the active marker).
    const sel = resEl.querySelector('#rActiveSelect');
    if (sel) {
      sel.onchange = () => {
        const id = sel.value;
        if (!id) {
          state.activeConfigName = null;
          // Selecting "Custom" explicitly = user wants to break from any
          // saved config. Clear the last-applied tracker so the
          // "Salvează modificări" button stops appearing.
          _lastAppliedConfig = null;
          buildUI();   // hide the "Save changes" button
          render();
          return;
        }
        const cfg = _savedConfigs.find(c => c.id === id);
        if (cfg) applyConfig(cfg);
      };
    }

    // Filter chip × — removes a single filter from state, then re-renders
    // both the configurator (so the dropdown returns to "(toate)") and
    // the report itself.
    resEl.querySelectorAll('.r-filter-chip-clear').forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();   // don't bubble to the outside-click closer
        const key = btn.dataset.col;
        const s = state.cols.find(c => c.key === key);
        if (s) s.filter = '';
        state.activeConfigName = null;   // diverged from saved config
        buildUI();   // refresh the filter dropdown styling
        render();    // refresh the report (and the chips themselves)
      };
    });
    // "Curăță toate" — single click wipes every active filter.
    const clearAllBtn = document.getElementById('rFiltersClearAll');
    if (clearAllBtn) {
      clearAllBtn.onclick = (e) => {
        e.stopPropagation();
        state.cols.forEach(c => { c.filter = ''; });
        state.activeConfigName = null;
        buildUI();
        render();
      };
    }
    // Toggle the filters popover. Clicking the i-button opens/closes it.
    // Clicking anywhere else closes it. The handler is added once and
    // cleaned up on the next render() (since DOM is rebuilt).
    const infoBtn = document.getElementById('rFiltersInfoBtn');
    const popover = document.getElementById('rFiltersInfoPopover');
    if (infoBtn && popover) {
      infoBtn.onclick = (e) => {
        e.stopPropagation();
        popover.hidden = !popover.hidden;
      };
      // Outside-click to close. Listener is added at document level; since
      // render() rebuilds DOM, stale listeners are harmless (the elements
      // they target no longer exist), but let's be defensive and use
      // { once: false } and attach only when the popover is shown.
      document.addEventListener('click', (e) => {
        if (popover.hidden) return;
        if (!popover.contains(e.target) && e.target !== infoBtn && !infoBtn.contains(e.target)) {
          popover.hidden = true;
        }
      });
    }

    // Bind sort on header click
    resEl.querySelectorAll('th[data-sort-key]').forEach(th => {
      th.onclick = () => {
        const k = th.dataset.sortKey;
        if (state.sortKey === k) {
          state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortKey = k;
          state.sortDir = 'asc';
        }
        render();
      };
    });
  }

  function activeFilterCount() {
    return state.cols.filter(c => c.filter !== '').length;
  }

  function exportToExcel() {
    const out = getOutputData();
    if (out.cols.length === 0) { toast('Bifează cel puțin o coloană.', 'warn'); return; }
    if (out.rows.length === 0) { toast('Niciun rezultat de exportat.', 'warn'); return; }

    const headers = out.cols.map(c => c.label);

    const dataRows = out.rows.map(r => out.cols.map(c => {
      const v = r[c.key];
      if (c.type === 'num') return typeof v === 'number' ? v : (Number(v) || 0);
      return v ?? '';
    }));

    // Footer with totals
    const valueSet = new Set(out.sumableKeys);
    const footerRow = [];
    let labelPlaced = false;
    out.cols.forEach(c => {
      if (valueSet.has(c.key)) {
        footerRow.push(out.rows.reduce((s, r) => s + (Number(r[c.key]) || 0), 0));
      } else if (c.virtual && c.key === '_count') {
        footerRow.push(out.rows.reduce((s, r) => s + (Number(r._count) || 0), 0));
      } else if (!labelPlaced) {
        footerRow.push('TOTAL');
        labelPlaced = true;
      } else {
        footerRow.push('');
      }
    });

    const ws = XLSX.utils.aoa_to_sheet([headers, ...dataRows, footerRow]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Raport');
    XLSX.writeFile(wb, `Raport_dinamic_${new Date().toISOString().slice(0,10)}.xlsx`);
  }

  buildUI();

  // Page-help superscript ⓘ — toggles a small popover with the page hint.
  // Wired here (after buildUI created the page header DOM) so the elements
  // exist. Outside-click and × close the popover; Escape too.
  const helpBtn   = document.getElementById('rPageHelpBtn');
  const helpPop   = document.getElementById('rPageHelpPopover');
  const helpClose = document.getElementById('rPageHelpClose');
  if (helpBtn && helpPop) {
    helpBtn.onclick = (e) => {
      e.stopPropagation();
      helpPop.hidden = !helpPop.hidden;
    };
    if (helpClose) {
      helpClose.onclick = (e) => { e.stopPropagation(); helpPop.hidden = true; };
    }
    document.addEventListener('click', (e) => {
      if (helpPop.hidden) return;
      if (!helpPop.contains(e.target) && e.target !== helpBtn) {
        helpPop.hidden = true;
      }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !helpPop.hidden) helpPop.hidden = true;
    });
  }

  // If any saved config is marked as the default, auto-apply it now.
  // Otherwise the user stays on freshState (selector shows "Custom").
  // Done after the initial buildUI so applyConfig only triggers one extra
  // render — and only when there's actually a default to apply.
  const defaultCfg = _savedConfigs.find(c => c.isDefault);
  if (defaultCfg) applyConfig(defaultCfg);
}

// ---- Settings ----
async function renderSettings(root) {
  root.innerHTML = `
    <div class="page-head">
      <h1>Setări</h1>
      <p class="subtitle">Administrare bază de date locală.</p>
    </div>
    <div class="card">
      <h3>🗑️ Resetare baze de date</h3>
      <p class="hint">Atenție: aceste acțiuni sunt definitive.</p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
        <button class="btn btn-danger" id="clearRaw">Șterge raw data</button>
        <button class="btn btn-danger" id="clearDS">Șterge DataSet</button>
        <button class="btn btn-danger" id="clearCurs">Șterge cursuri</button>
        <button class="btn btn-danger" id="clearAll">Șterge TOT</button>
      </div>
    </div>
    <div class="card" style="margin-top:1rem">
      <h3>📊 Date master</h3>
      <p class="hint">Resetează tabelele cu master-data (Nomenclator + Marketplaces) la valorile predefinite.</p>
      <button class="btn" id="resetMaster">Resetează Nomenclator + Marketplaces</button>
    </div>
  `;
  $('#clearRaw').onclick = async () => { if(confirm('Sigur?')){ await dbClear('rawData'); toast('Raw data șters','success'); } };
  $('#clearDS').onclick = async () => { if(confirm('Sigur?')){ await dbClear('dataSet'); toast('DataSet șters','success'); } };
  $('#clearCurs').onclick = async () => { if(confirm('Sigur?')){ await dbClear('cursValutar'); toast('Cursuri șterse','success'); } };
  $('#clearAll').onclick = async () => {
    if (!confirm('Confirmi ștergerea COMPLETĂ a bazei de date?')) return;
    await Promise.all(['rawData','dataSet','cursValutar','nomenclator','marketplaces'].map(s=>dbClear(s)));
    await ensureMasterData();
    toast('Bază de date resetată','success');
  };
  $('#resetMaster').onclick = async () => {
    await dbClear('nomenclator'); await dbClear('marketplaces');
    await ensureMasterData();
    toast('Master data resetată','success');
  };
}

// ---- Users (admin only) ----
// Defense in depth: navigate() also redirects non-admins to dashboard.
// The server enforces admin checks on every endpoint regardless.
async function renderUsers(root) {
  if (!isAdmin()) {
    root.innerHTML = '<div class="error">Acces restricționat — necesită privilegii de administrator.</div>';
    return;
  }

  let users = [];
  try {
    users = await adminUsersList();
  } catch (err) {
    root.innerHTML = `<div class="error">Eroare la încărcare: ${esc(err.message)}</div>`;
    return;
  }

  const meId = (_currentUser && _currentUser.id) || 0;
  const adminCount = users.filter(u => u.is_admin).length;

  root.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Utilizatori</h1>
        <p class="subtitle">${users.length} cont${users.length === 1 ? '' : 'uri'} · ${adminCount} administrator${adminCount === 1 ? '' : 'i'}. Datele fiecărui utilizator sunt izolate.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="addUserBtn">➕ Adaugă utilizator</button>
      </div>
    </div>
    <div class="card">
      <input type="text" class="filter" placeholder="🔍 Caută email sau nume..." id="usersFilter"/>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr>
            <th style="width:60px">ID</th>
            <th>Email</th>
            <th>Nume</th>
            <th style="width:120px">Rol</th>
            <th>Creat la</th>
            <th class="num" style="width:200px">Acțiuni</th>
          </tr></thead>
          <tbody id="usersBody"></tbody>
        </table>
      </div>
    </div>
  `;

  function adminBadge(u) {
    return u.is_admin
      ? '<span class="badge" style="background:#1F4788;color:#fff">admin</span>'
      : '<span class="badge" style="background:#e2e8f0;color:#475569">user</span>';
  }
  function meTag(u) {
    return u.id === meId
      ? ' <span style="font-size:.7rem;color:var(--text-muted);font-weight:600">(tu)</span>'
      : '';
  }

  const renderRows = (filter = '') => {
    const f = filter.toLowerCase();
    // Filter on both email and name so admins can search by either.
    const filtered = users.filter(u => !f
      || (u.email || '').toLowerCase().includes(f)
      || (u.name  || '').toLowerCase().includes(f));
    const isLastAdmin = (u) => u.is_admin && adminCount <= 1;

    $('#usersBody').innerHTML = filtered.map(u => {
      const selfClass = u.id === meId ? ' style="background:rgba(45,139,142,.04)"' : '';
      // Disable risky actions on yourself / on the last admin
      const disDelete = (u.id === meId) ? 'disabled title="Nu te poți șterge pe tine"' :
                        (isLastAdmin(u))  ? 'disabled title="Nu poți șterge ultimul admin"' : '';
      const disDemote = (u.id === meId) ? 'disabled title="Nu te poți retrograda pe tine"' :
                        (isLastAdmin(u))  ? 'disabled title="Nu poți retrograda ultimul admin"' : '';
      const nameCell = u.name && u.name.trim()
        ? esc(u.name)
        : '<span style="color:var(--text-muted);font-style:italic">—</span>';
      return `
        <tr data-id="${u.id}"${selfClass}>
          <td><code>${u.id}</code></td>
          <td><b>${esc(u.email)}</b>${meTag(u)}</td>
          <td>${nameCell}</td>
          <td>${adminBadge(u)}</td>
          <td><span style="font-size:.78rem;color:var(--text-muted)">${esc(u.created_at || '')}</span></td>
          <td class="num">
            <button class="btn-icon" data-act="edit"   title="Editează email și nume">✏️</button>
            <button class="btn-icon" data-act="passwd" title="Resetează parola">🔑</button>
            <button class="btn-icon" data-act="${u.is_admin ? 'demote' : 'promote'}" ${u.is_admin ? disDemote : ''} title="${u.is_admin ? 'Retrogradează la user' : 'Promovează la admin'}">${u.is_admin ? '⬇️' : '⬆️'}</button>
            <button class="btn-icon" data-act="delete" ${disDelete} title="Șterge utilizator">🗑️</button>
          </td>
        </tr>
      `;
    }).join('') || '<tr><td colspan="6" class="empty">Niciun utilizator.</td></tr>';

    $$('#usersBody [data-act]').forEach(btn => {
      btn.onclick = async () => {
        if (btn.disabled) return;
        const tr = btn.closest('tr');
        const id = parseInt(tr.dataset.id, 10);
        const u = users.find(x => x.id === id);
        if (!u) return;
        const act = btn.dataset.act;
        if (act === 'edit')    return openUserEditEmail(u);
        if (act === 'passwd')  return openUserPasswd(u);
        if (act === 'promote') return changeAdminFlag(u, true);
        if (act === 'demote')  return changeAdminFlag(u, false);
        if (act === 'delete')  return doDelete(u);
      };
    });
  };

  renderRows();
  $('#usersFilter').oninput = (e) => renderRows(e.target.value);
  $('#addUserBtn').onclick = () => openUserCreate();

  function refresh() { renderUsers(root); }

  function openUserCreate() {
    openModal({
      title: '➕ Adaugă utilizator',
      size: 'sm',
      fields: [
        { name: 'email',    label: 'Email',    type: 'text',     required: true, placeholder: 'persoana@aiall.ro' },
        { name: 'name',     label: 'Nume',     type: 'text',     placeholder: 'opțional, ex: Ion Popescu', hint: 'Apare în meniul utilizatorului dacă e completat.' },
        { name: 'password', label: 'Parolă',   type: 'password', required: true, placeholder: 'min. 8 caractere', autocomplete: 'new-password' },
        { name: 'is_admin', label: 'Administrator', type: 'checkbox', hint: 'Are acces la modulul de utilizatori și poate edita/șterge date.' }
      ],
      values: { is_admin: false },
      onSave: async (data) => {
        await adminUsersCreate({
          email:    data.email,
          name:     data.name,
          password: data.password,
          is_admin: data.is_admin
        });
        toast('Utilizator creat', 'success');
        refresh();
      }
    });
  }

  function openUserEditEmail(u) {
    openModal({
      title: `✏️ Editează: ${esc(u.email)}`,
      size: 'sm',
      fields: [
        { name: 'email', label: 'Email', type: 'text', required: true },
        { name: 'name',  label: 'Nume',  type: 'text', placeholder: 'opțional', hint: 'Lasă gol pentru a șterge numele.' }
      ],
      values: { email: u.email, name: u.name || '' },
      onSave: async (data) => {
        await adminUsersUpdate(u.id, { email: data.email, name: data.name });
        toast('Utilizator actualizat', 'success');
        refresh();
      }
    });
  }

  function openUserPasswd(u) {
    openModal({
      title: `🔑 Resetează parola: ${u.email}`,
      size: 'sm',
      fields: [
        { name: 'password',  label: 'Parolă nouă',     type: 'password', required: true, placeholder: 'min. 8 caractere', autocomplete: 'new-password' },
        { name: 'password2', label: 'Confirmă parola', type: 'password', required: true, autocomplete: 'new-password' }
      ],
      values: {},
      onSave: async (data) => {
        if (data.password !== data.password2) throw new Error('Parolele nu coincid');
        if ((data.password || '').length < 8) throw new Error('Parola trebuie să aibă cel puțin 8 caractere');
        await adminUsersPasswd(u.id, data.password);
        toast('Parolă schimbată', 'success');
      }
    });
  }

  async function changeAdminFlag(u, makeAdmin) {
    const verb = makeAdmin ? 'promovezi' : 'retrogradezi';
    const role = makeAdmin ? 'administrator' : 'utilizator obișnuit';
    if (!await confirmDialog(`Sigur ${verb} contul <b>${esc(u.email)}</b> la rolul de <b>${role}</b>?`)) return;
    try {
      await adminUsersUpdate(u.id, { is_admin: makeAdmin });
      toast(makeAdmin ? 'Promovat la admin' : 'Retrogradat la user', 'success');
      refresh();
    } catch (err) {
      toast(err.message, 'error');
    }
  }

  async function doDelete(u) {
    if (!await confirmDialog(
      `Sigur ștergi contul <b>${esc(u.email)}</b>?<br><br>` +
      `<span style="color:var(--error)">Atenție: <b>se șterg definitiv toate datele</b> acestui utilizator (nomenclator, marketplaces, rawData, dataSet, settings, importLog).</span>`
    )) return;
    try {
      await adminUsersDelete(u.id);
      toast('Utilizator șters (cu toate datele lui)', 'success');
      refresh();
    } catch (err) {
      toast(err.message, 'error');
    }
  }
}

// ---- Auth UI ----
// _setupMode: false → normal login; true → first-admin bootstrap
let _setupMode = false;

function applyLoginMode(mode) {
  _setupMode = !!mode;
  const titleEl   = document.getElementById('loginTitle');
  const banner    = document.getElementById('setupBanner');
  const confirm   = document.getElementById('confirmField');
  const submitBtn = document.getElementById('loginSubmit');
  const foot      = document.getElementById('loginFoot');
  const pwInput   = document.getElementById('loginPassword');

  if (_setupMode) {
    if (titleEl)   titleEl.textContent = 'Configurare inițială';
    if (banner)    banner.hidden = false;
    if (confirm)   confirm.hidden = false;
    if (submitBtn) submitBtn.textContent = 'Creează cont admin';
    if (foot)      foot.hidden = true;
    if (pwInput)   pwInput.setAttribute('autocomplete', 'new-password');
  } else {
    if (titleEl)   titleEl.textContent = 'Autentificare';
    if (banner)    banner.hidden = true;
    if (confirm)   confirm.hidden = true;
    if (submitBtn) submitBtn.textContent = 'Intră în cont';
    if (foot)      foot.hidden = false;
    if (pwInput)   pwInput.setAttribute('autocomplete', 'current-password');
  }
}

async function showLoginOverlay(errorMsg) {
  const ov = document.getElementById('loginOverlay');
  if (!ov) return;
  ov.classList.add('open');
  const errEl = document.getElementById('loginError');

  // Detect whether the DB is empty (needs bootstrap) — decide which form
  // mode to show. Failures here fall through to normal login mode.
  try {
    const status = await authStatus();
    applyLoginMode(!!(status && status.needs_bootstrap));
  } catch (_) {
    applyLoginMode(false);
  }

  if (errorMsg) {
    errEl.textContent = errorMsg;
    errEl.classList.add('show');
  } else {
    errEl.classList.remove('show');
  }
  // focus email field
  setTimeout(() => {
    const email = document.getElementById('loginEmail');
    if (email && !email.value) email.focus();
    else {
      const pw = document.getElementById('loginPassword');
      if (pw) pw.focus();
    }
  }, 30);
}
function hideLoginOverlay() {
  const ov = document.getElementById('loginOverlay');
  if (ov) ov.classList.remove('open');
}
// Compute 1–2 letter initials for the user avatar.
//   "Ion Popescu"   → "IP"
//   "Maria"         → "M"
//   (no name) email → first letter of local-part, e.g. "admin@x.ro" → "A"
function userInitials(user) {
  if (!user) return '·';
  const name = (user.name || '').trim();
  if (name) {
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }
  const email = (user.email || '').trim();
  if (email) {
    const localPart = email.split('@')[0] || email;
    return (localPart.charAt(0) || '?').toUpperCase();
  }
  return '·';
}

function setUserBlock(user) {
  const menu          = document.getElementById('userMenu');
  const initialsEl    = document.getElementById('userAvatarInitials');
  const avatarLargeEl = document.getElementById('userAvatarLarge');
  const dotEl         = document.getElementById('userAvatarDot');
  const nameEl        = document.getElementById('userDropdownName');
  const emailEl       = document.getElementById('userDropdownEmail');
  const roleAdmin     = document.getElementById('userRoleAdmin');
  const roleReadonly  = document.getElementById('userRoleReadonly');
  const avatarBtn     = document.getElementById('userAvatarBtn');

  if (!menu) return;

  if (!user || !user.email) {
    menu.hidden = true;
    closeUserDropdown();
    return;
  }

  const initials = userInitials(user);
  if (initialsEl)    initialsEl.textContent    = initials;
  if (avatarLargeEl) avatarLargeEl.textContent = initials;
  if (avatarBtn)     avatarBtn.title           = (user.name || user.email);

  if (nameEl) {
    if (user.name && user.name.trim()) {
      nameEl.textContent = user.name;
      nameEl.hidden = false;
    } else {
      nameEl.textContent = '';
      nameEl.hidden = true;
    }
  }
  if (emailEl) {
    emailEl.textContent = user.email;
    emailEl.title = user.email;
  }
  if (roleAdmin)    roleAdmin.hidden    = !user.is_admin;
  if (roleReadonly) roleReadonly.hidden = !!user.is_admin;
  if (dotEl)        dotEl.hidden        = !!user.is_admin;

  menu.hidden = false;
}

function openUserDropdown() {
  const dd  = document.getElementById('userDropdown');
  const btn = document.getElementById('userAvatarBtn');
  if (!dd || !btn) return;
  dd.hidden = false;
  btn.setAttribute('aria-expanded', 'true');
}
function closeUserDropdown() {
  const dd  = document.getElementById('userDropdown');
  const btn = document.getElementById('userAvatarBtn');
  if (!dd || !btn) return;
  dd.hidden = true;
  btn.setAttribute('aria-expanded', 'false');
}
function toggleUserDropdown() {
  const dd = document.getElementById('userDropdown');
  if (!dd) return;
  if (dd.hidden) openUserDropdown(); else closeUserDropdown();
}

// Wires up login form & logout button. Idempotent.
function wireAuthUI(onLoginSuccess) {
  const form = document.getElementById('loginForm');
  const submitBtn = document.getElementById('loginSubmit');
  const errEl = document.getElementById('loginError');
  if (form && !form._wired) {
    form._wired = true;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      if (!email || !password) return;

      // Bootstrap mode requires confirm-password match
      if (_setupMode) {
        const confirmPwd = document.getElementById('loginConfirm').value;
        if (password !== confirmPwd) {
          errEl.textContent = 'Parolele nu coincid';
          errEl.classList.add('show');
          return;
        }
        if (password.length < 8) {
          errEl.textContent = 'Parola trebuie să aibă cel puțin 8 caractere';
          errEl.classList.add('show');
          return;
        }
      }

      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = _setupMode ? 'Se creează contul...' : 'Se autentifică...';
      errEl.classList.remove('show');
      try {
        const user = _setupMode
          ? await authBootstrap(email, password)
          : await authLogin(email, password);

        // Clear sensitive fields before navigation
        document.getElementById('loginPassword').value = '';
        const c = document.getElementById('loginConfirm');
        if (c) c.value = '';

        // Force a full reload — the PHP session gate sees the new cookie
        // and serves the full app (or mini-page) appropriately. This
        // rebuilds all client-side state (admin flag, master data, charts,
        // CSS classes) cleanly and matches the mini-page login behavior.
        // The user's reference to `user`, onLoginSuccess, etc. is dropped
        // intentionally — the page is being replaced.
        void user;
        if (typeof onLoginSuccess === 'function') void onLoginSuccess;
        window.location.reload();
        return; // navigation in progress — stop further work in this turn
      } catch (err) {
        errEl.textContent = err.message || (_setupMode ? 'Creare cont eșuată' : 'Autentificare eșuată');
        errEl.classList.add('show');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    });
  }
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn && !logoutBtn._wired) {
    logoutBtn._wired = true;
    logoutBtn.onclick = async () => {
      closeUserDropdown();
      if (!confirm('Ieși din cont?')) return;
      try { await authLogout(); } catch (_) { /* ignore */ }
      setCurrentUser(null);
      setUserBlock(null);
      // Clear any visible app content & show login again
      const main = document.querySelector('#main');
      if (main) main.innerHTML = '';
      showLoginOverlay();
    };
  }

  // User avatar → dropdown toggle
  const avatarBtn = document.getElementById('userAvatarBtn');
  if (avatarBtn && !avatarBtn._wired) {
    avatarBtn._wired = true;
    avatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleUserDropdown();
    });
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      const menu = document.getElementById('userMenu');
      if (!menu || menu.hidden) return;
      if (!menu.contains(e.target)) closeUserDropdown();
    });
    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeUserDropdown();
    });
  }
}

// ---- Init ----
async function init() {
  // Wire login/logout once, regardless of auth state
  wireAuthUI(afterLoginInit);

  // If a server call ever comes back 401, surface the login overlay
  // (the wrapper invokes _onAuthLost; we set it here)
  onAuthLost(() => {
    setCurrentUser(null);
    setUserBlock(null);
    showLoginOverlay('Sesiune expirată. Te rugăm să te autentifici din nou.');
  });

  // Theme + sidebar wiring runs early so login screen also respects theme
  wireShellChrome();

  // Check auth status; show login if not authenticated, else continue
  let user = null;
  try { user = await authMe(); } catch (e) { /* network errors fall through */ }

  if (user) {
    setCurrentUser(user);
    setUserBlock(user);
    await afterLoginInit(user);
  } else {
    showLoginOverlay();
  }
}

// Runs after a user is confirmed authenticated (either via authMe at startup
// or after a successful login). Performs all the work init() used to do.
async function afterLoginInit(user) {
  await ensureMasterData();
  // wire up nav (idempotent — overwrites prior handlers)
  $$('.nav-item').forEach(el => el.onclick = () => navigate(el.dataset.route));

  // initial route — location.hash may not be available in sandboxed iframes
  let hash = 'dashboard';
  try { hash = (location.hash || '').replace('#', '') || 'dashboard'; } catch (e) {}
  navigate(routes[hash] ? hash : 'dashboard');
}

// Sidebar/theme/info wiring — extracted so it runs before authentication
// (so the login screen also respects saved theme, etc.)
function wireShellChrome() {
  wireHelpSystem();
  const app = document.getElementById('app');
  const toggle = document.getElementById('sidebarToggle');
  try {
    if (localStorage.getItem('sidebarCollapsed') === '1') app.classList.add('sidebar-collapsed');
  } catch (e) { /* localStorage may be unavailable in iframes */ }
  if (toggle && !toggle._wired) {
    toggle._wired = true;
    toggle.onclick = () => {
      app.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('sidebarCollapsed', app.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (e) {}
    };
  }

  // Theme toggle (light/dark) — respect existing data-theme on html, fall back to localStorage, then 'light'
  const html = document.documentElement;
  let savedTheme = html.getAttribute('data-theme');
  if (!savedTheme) {
    try { savedTheme = localStorage.getItem('sbTheme'); } catch (e) {}
  }
  html.setAttribute('data-theme', savedTheme || 'light');
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn && !themeBtn._wired) {
    themeBtn._wired = true;
    themeBtn.onclick = () => {
      const cur = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', cur);
      try { localStorage.setItem('sbTheme', cur); } catch (e) {}
    };
  }

  // Info modal "Despre EcR Deconturi"
  const infoBtn = document.getElementById('infoBtn');
  const infoOverlay = document.getElementById('infoOverlay');
  const closeInfoBtn = document.getElementById('closeInfoBtn');
  if (infoBtn && infoOverlay && !infoBtn._wired) {
    infoBtn._wired = true;
    infoBtn.onclick = () => infoOverlay.classList.add('open');
    closeInfoBtn.onclick = () => infoOverlay.classList.remove('open');
    infoOverlay.addEventListener('mousedown', (e) => {
      if (e.target === infoOverlay) infoOverlay.classList.remove('open');
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') infoOverlay.classList.remove('open');
    });
  }
}

window.navigate = navigate;
window.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
