<?php
// Samostatná stránka online kompilátora (pre voľné spúšťanie kódu bez úlohy)
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_login();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Online kompilátor – E-Learn</title>
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<header class="page-header">
    <div class="logo">E-<span>Learn</span></div>
    <nav class="breadcrumbs">
        <a href="index.php">Kurzy</a>
        <span>›</span>
        <span>Online kompilátor</span>
    </nav>
    <div class="user-info">
        <?= h(current_user_name() ?? '') ?>
        <a href="dashboard.php" class="btn btn-secondary" style="margin-left:8px;">Dashboard</a>
    </div>
</header>

<main class="container">
    <h1>Online kompilátor</h1>
    <p class="muted">Vyberte jazyk, napíšte kód a stlačte Spustiť. Výstup zobrazí Judge0.</p>

    <div class="code-task-block" style="max-width: 900px;">
        <div class="code-task-editor">
            <label class="code-task-lang-label">Jazyk:</label>
            <select id="compiler-lang">
                <option value="71">Python</option>
                <option value="62">Java</option>
                <option value="54">C++</option>
            </select>
            <br><br>
            <textarea id="compiler-code" class="code-task-code" rows="16" placeholder="Sem napíšte svoj kód..."></textarea>
            <br>
            <label class="code-task-lang-label">Vstup (stdin), voliteľný:</label>
            <textarea id="compiler-stdin" rows="3" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-subtle); background: var(--bg-surface); color: var(--text-main); font-family: monospace; margin-bottom:12px;"></textarea>
            <button type="button" class="btn code-task-run" id="compiler-run">Spustiť</button>
            <pre id="compiler-out" class="code-task-output" aria-live="polite" style="margin-top:12px;"></pre>
        </div>
    </div>
</main>

<script>
    (function() {
        var out = document.getElementById('compiler-out');
        document.getElementById('compiler-run').addEventListener('click', function() {
            out.textContent = 'Spúšťam…';
            out.classList.remove('result-ok', 'result-bad');

            var payload = {
                language_id: Number(document.getElementById('compiler-lang').value),
                source_code: document.getElementById('compiler-code').value,
                stdin: document.getElementById('compiler-stdin').value
            };

            fetch('api/judge0-run.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) {
                        out.textContent = data.error + (data.detail ? '\n' + data.detail : '') + (data.http ? ' (HTTP ' + data.http + ')' : '');
                        out.classList.add('result-bad');
                    } else {
                        out.textContent = data.output !== undefined && data.output !== '' ? data.output : '(žiadny výstup)';
                    }
                })
                .catch(function(err) {
                    out.textContent = 'Chyba: ' + err.message;
                    out.classList.add('result-bad');
                });
        });
    })();
</script>
</body>
</html>
