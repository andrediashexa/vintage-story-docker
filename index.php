<?php
// index.php - UI simples para controlar /home/vintagestory/server/server.sh
// Use com cautela. Rodar via HTTPS + autenticação, e configurar sudoers.

session_start();

// === CONFIG ===
$SCRIPT_PATH = '/home/vintagestory/server/server.sh';
$USE_SUDO = true;
$SUDO_BIN = '/usr/bin/sudo -n'; // não-interativo (falha sem pedir senha)
$ENABLE_BASIC_AUTH = true;
$ALLOWED_USER = 'admin';
// Dica: ideal é salvar o hash fixo (gerado antes) em vez de recalcular aqui.
// Para demo, deixei assim:
$ALLOWED_PASS_HASH = password_hash('CHANGE-IT', PASSWORD_DEFAULT);

// Presets do dropdown (simples — só insere na textarea)
$PRESET_COMMANDS = [
    '— Úteis —' => [
        '/help',
        '/list clients',
        '/stats',
        '/autosavenow',
        '/stop',
    ],
    'Servidor' => [
        '/serverconfig advertise on',
        '/serverconfig whitelistmode off',
        '/serverconfig whitelistmode on',
        '/announce Bem-vindos ao servidor!',
    ],
    'Tempo/Clima' => [
        '/time set day',
        '/time set night',
        '/time add 1:00',
        '/weather stoprain',
        '/nexttempstorm',
        '/nexttempstorm now',
    ],
    'Administração' => [
        '/kick NOME_JOGADOR Motivo opcional',
        '/ban NOME_JOGADOR Motivo opcional',
        '/unban NOME_JOGADOR',
        '/tp NOME_JOGADOR',
        '/gamemode creative',
        '/gamemode survival',
    ],
    'Waypoints/Land' => [
        '/waypoint list',
        '/waypoint add red NovoPonto',
        '/land list',
        '/land info',
    ],
];
// =====================

// Auth básica (opcional)
if ($ENABLE_BASIC_AUTH) {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="Painel Server"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Autenticação requerida.';
        exit;
    } else {
        if ($_SERVER['PHP_AUTH_USER'] !== $ALLOWED_USER || !password_verify($_SERVER['PHP_AUTH_PW'], $ALLOWED_PASS_HASH)) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Credenciais inválidas.';
            exit;
        }
    }
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];

$output = '';
$error = '';
$allowed_actions = ['start', 'stop', 'command'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf) {
        $error = 'Token CSRF inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        if (!in_array($action, $allowed_actions, true)) {
            $error = 'Ação inválida.';
        } else {
            // Monta comando
            $base = escapeshellcmd($SCRIPT_PATH);
            if ($action === 'start' || $action === 'stop') {
                $cmd = $base . ' ' . escapeshellarg($action);
            } else { // command
                $usercmd = trim((string)($_POST['command_text'] ?? ''));
                if ($usercmd === '') {
                    $error = 'Comando vazio.';
                } else {
                    $cmd = $base . ' command ' . escapeshellarg($usercmd);
                }
            }
            if (!$error) {
                if ($USE_SUDO) {
                    $cmd = $SUDO_BIN . ' ' . $cmd;
                }
                // Captura saída e exit code
                $output = shell_exec($cmd . ' 2>&1');
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Painel Server - Gepeteco</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    body{font-family: Inter, Roboto, Arial; max-width:900px;margin:40px auto;padding:20px;color:#111}
    h1{margin-bottom:6px}
    .row{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
    .controls{display:flex;gap:12px;margin:12px 0}
    button{padding:10px 18px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
    .play{background:#2ecc71;color:#fff}
    .stop{background:#e74c3c;color:#fff}
    .run{background:#3498db;color:#fff}
    select, textarea, input[type=text]{width:100%;padding:10px;border-radius:8px;border:1px solid #ddd}
    textarea{height:140px}
    pre{background:#111;color:#0f0;padding:12px;border-radius:6px;overflow:auto}
    .note{color:#666;font-size:0.9rem}
    .error{color:#9b2c2c}
    .card{border:1px solid #eee;border-radius:12px;padding:14px;margin-top:14px}
    label{font-size:0.95rem;color:#333;margin:6px 0;display:block}
</style>
</head>
<body>
    <h1>Painel Server</h1>
    <p class="note">Play / Stop chamam: <code><?= htmlspecialchars($SCRIPT_PATH) ?> start|stop</code>. O campo de comando chama: <code><?= htmlspecialchars($SCRIPT_PATH) ?> command "texto"</code>. Usa <code>sudo -n</code>.</p>

    <?php if ($error): ?>
        <p class="error"><strong>Erro:</strong> <?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="controls">
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="start">
            <button class="play" type="submit">▶ Play</button>
        </form>

        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="stop">
            <button class="stop" type="submit">■ Stop</button>
        </form>
    </div>

    <div class="card">
        <form method="post" id="cmdForm">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="command">

            <label for="preset">Comandos pré-definidos</label>
            <select id="preset">
                <option value="">— selecione —</option>
                <?php foreach ($PRESET_COMMANDS as $group => $items): ?>
                    <optgroup label="<?= htmlspecialchars($group) ?>">
                        <?php foreach ($items as $cmd): ?>
                            <option value="<?= htmlspecialchars($cmd) ?>"><?= htmlspecialchars($cmd) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>

            <label for="cmd" style="margin-top:10px">Comando (livre) — será passado para <code>server.sh command "texto"</code></label>
            <textarea id="cmd" name="command_text" placeholder='ex: /announce Servidor online!'><?= isset($_POST['command_text']) ? htmlspecialchars($_POST['command_text']) : '' ?></textarea>

            <div class="row">
                <button class="run" type="submit">Executar comando</button>
            </div>
            <p class="note" style="margin-top:8px">Dica: os comandos do Vintage Story começam com <code>/</code>. Ex.: <code>/serverconfig advertise on</code>, <code>/time set day</code>, <code>/list clients</code>.</p>
        </form>
    </div>

    <h3>Saída</h3>
    <?php if ($output !== ''): ?>
        <pre><?= htmlspecialchars($output) ?></pre>
    <?php else: ?>
        <p class="note">Nenhuma saída para exibir — execute um comando.</p>
    <?php endif; ?>

    <hr>
    <p class="note">
        Segurança: use HTTPS + auth, e limite no <code>sudoers</code> somente estes comandos.<br>
        Se o servidor exigir <code>dotnet</code> no <code>PATH</code>, ajuste no seu <code>server.sh</code> (export <code>DOTNET_ROOT</code> e <code>PATH</code>) para rodar bem via sudo.
    </p>

<script>
document.getElementById('preset').addEventListener('change', function(){
    const sel = this.value || '';
    const ta = document.getElementById('cmd');
    if (sel) {
        // Insere no textarea (substitui o conteúdo atual)
        ta.value = sel;
        ta.focus();
    }
});
</script>
</body>
</html>
