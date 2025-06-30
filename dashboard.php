<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

session_start();
$DATA_DIR      = __DIR__ . '/data/';
$DESTROYED_LOG = __DIR__ . '/destroyed.log';
$EXPIRED_LOG   = __DIR__ . '/expired.log';
$EXPIRED_COUNT = __DIR__ . '/expired.count';

// Utility: Load all SHA256 hashes from log file
function loadHashesFromLog($file) {
    if (!file_exists($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $hashes = [];
    foreach ($lines as $line) {
        if (preg_match('/([a-f0-9]{64})$/i', $line, $m)) {
            $hashes[] = $m[1];
        }
    }
    return $hashes;
}
$destroyed_hashes = loadHashesFromLog($DESTROYED_LOG);
$expired_hashes   = loadHashesFromLog($EXPIRED_LOG);

// Expiration map (readonly, does not alter actual files)
$expires_map = [];
if (file_exists(__DIR__.'/expires.log')) {
    $lines = file(__DIR__.'/expires.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = explode(';', $line);
        if (count($cols) >= 2) {
            $expires_map[strtolower($cols[2] ?? $cols[0])] = (int)$cols[1]; // id or hash
        }
    }
}
function abbreviateId($id) {
    return strlen($id) <= 12 ? $id : substr($id,0,5).'...'.substr($id,-5);
}
function timestampToDate($ts) {
    if (!$ts) return '-';
    return date('Y-m-d H:i', $ts);
}
function messageHash($id) {
    return hash('sha256', $id);
}

// --- Destroy message (manual action) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'destroy') {
    $id = preg_replace('/[^a-f0-9]/i', '', $_POST['id']);
    $file = $DATA_DIR . $id . '.txt';
    $hashfile = $DATA_DIR . $id . '.hash';

    if (file_exists($file)) {
        unlink($file);
        if (file_exists($hashfile)) unlink($hashfile);

        // Register as expired if not already logged
        $hash = messageHash($id);

        $alreadyExpired = false;
        if (file_exists($EXPIRED_LOG)) {
            $alreadyExpired = preg_match('/' . preg_quote($hash, '/') . '/i', file_get_contents($EXPIRED_LOG));
        }
        if (!$alreadyExpired) {
            file_put_contents($EXPIRED_LOG, date('c') . " EXPIRED $hash\n", FILE_APPEND | LOCK_EX);

            // Increment expired.count
            $total = 1;
            if (file_exists($EXPIRED_COUNT)) $total = ((int)file_get_contents($EXPIRED_COUNT)) + 1;
            file_put_contents($EXPIRED_COUNT, $total, LOCK_EX);
        }
    }

    // Redirect to avoid POST resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// --- Load file (upload) ---
if (isset($_FILES['userfile']) && $_FILES['userfile']['error'] === UPLOAD_ERR_OK) {
    $file_txt = file_get_contents($_FILES['userfile']['tmp_name']);
    $lines = explode("\n", $file_txt);
    $messages = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $parts = explode(',', $line, 2);
        $str = trim($parts[0]);
        $tag = isset($parts[1]) ? trim($parts[1]) : '';
        if (preg_match('/[?&]id=([a-f0-9]{16,32})/i', $str, $m)) {
            $id = strtolower($m[1]);
        } elseif (preg_match('/^[a-f0-9]{16,32}$/i', $str)) {
            $id = strtolower($str);
        } else {
            continue;
        }
        $link = (strpos($str, 'http') === 0) ? $str : '';
        $messages[] = ['id'=>$id, 'link'=>$link, 'tag'=>$tag];
    }
    // Store results in session, redirect for GET
    $_SESSION['dashboard_msgs'] = $messages;
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// --- Read loaded results (from session) ---
$messages = [];
if (isset($_SESSION['dashboard_msgs'])) {
    $messages = $_SESSION['dashboard_msgs'];
    // Clear session so F5 doesn't re-show data
    unset($_SESSION['dashboard_msgs']);
}

// File statistics
$stats = ['active'=>0, 'read'=>0, 'expired'=>0];
foreach ($messages as $msg) {
    $id = $msg['id'];
    $hash = messageHash($id);
    if (in_array($hash, $destroyed_hashes)) $stats['read']++;
    elseif (in_array($hash, $expired_hashes)) $stats['expired']++;
    else $stats['active']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PrivateTalk – Local Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <meta name="description" content="Private dashboard to manage and monitor your PrivateTalk messages. No data sent to third parties.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="pt-main">

    <!-- Header -->
    <header class="pt-header">
        <h1><a href="./">PrivateTalk - Local Dashboard</a></h1>
        <span class="pt-subtitle">
            Private management of your self-destructing message links.
        </span>
    </header>

    <!-- Main Card -->
    <div class="dashboard-box">
        <div class="info-nota">
            Upload a <b>.txt</b> file with the <b>ID</b> or <b>links</b> of your messages, separated by comma and optional tag.<br>
            Example: <code>49dce4fd21f0b126,Urgent work</code> or <code>https://site.com/?id=49dce4fd21f0b126,Private backup</code>.<br>
            <span class="info-borda-azul">Nothing is sent to third parties — all processing is local in this panel.</span>
        </div>
        <!-- Margin below form handled by CSS class, not inline -->
        <form method="post" enctype="multipart/form-data" class="mb-18">
            <label class="upload-btn">
                Select file…
                <input type="file" name="userfile" accept=".txt,.csv" required onchange="document.getElementById('file-name').textContent = this.files[0]?.name || '' ; this.form.submit();">
            </label>
            <span id="file-name"></span>
        </form>
        <?php if ($messages): ?>
        <!-- File stats row with color and margin classes -->
        <div class="mt-16 mb-18 fs-109">
            <b>File stats:</b>
            <span class="stats-verde">Active: <?=$stats['active']?></span> &nbsp;|&nbsp;
            <span class="stats-azul">Read: <?=$stats['read']?></span> &nbsp;|&nbsp;
            <span class="stats-laranja">Expired: <?=$stats['expired']?></span>
        </div>
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <tr>
                    <th>ID</th>
                    <th>Hash</th>
                    <th>Link</th>
                    <th>Tag</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th class="acao">Action</th>
                </tr>
                <?php foreach ($messages as $msg):
                    $id = $msg['id'];
                    $hash = messageHash($id);
                    $linkHtml = $msg['link'] ? '<button type="button" onclick="navigator.clipboard.writeText(\''.$msg['link'].'\')">Copy Link</button>' : '-';
                    $expiresHtml = (isset($expires_map[$id]) && time() < $expires_map[$id]) ? timestampToDate($expires_map[$id]) : '-';
                    $status = 'active';
                    if (in_array($hash, $destroyed_hashes)) $status = 'read';
                    elseif (in_array($hash, $expired_hashes)) $status = 'expired';
                ?>
                <tr>
                    <td><span title="<?=htmlspecialchars($id)?>"><?=abbreviateId($id)?></span></td>
                    <td>
                        <span><?=substr($hash,0,5).'...'.substr($hash,-5)?></span>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$hash?>')">Copy hash</button>
                    </td>
                    <td><?=$linkHtml?></td>
                    <td><?=htmlspecialchars($msg['tag'] ?: '-')?></td>
                    <td><?=ucfirst($status)?></td>
                    <td><?=$expiresHtml?></td>
                    <td class="acao">
                        <?php if ($status==='active'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="destroy">
                                <input type="hidden" name="id" value="<?=htmlspecialchars($id)?>">
                                <button type="submit" onclick="return confirm('Delete this message?')">Destroy now</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php elseif (isset($_FILES['userfile'])): ?>
            <!-- Error message with external class, no inline CSS -->
            <div class="erro-vermelho">File does not contain valid IDs.</div>
        <?php endif; ?>
        <!-- Bottom privacy note with classes for margin and font size -->
        <div class="fs-096 mt-24 txt-cinza">
            <b>Full privacy:</b> All processing is local on the server, never sent elsewhere.<br>
            <span class="stats-vermelho">After a refresh (F5), you need to re-upload your file for full privacy.</span>
        </div>
    </div>

    <!-- Footer -->
    <footer class="pt-footer">
        <div>
            &copy; <?=date('Y')?> <b>PrivateTalk</b> v1.1.0 — <a href="https://github.com/jokerjosue/PrivateTalk" target="_blank">Open-Source Code</a>
        </div>
        <div>
            Created by <a href="https://bitcointalk.org/index.php?action=profile;u=97582" target="_blank">@joker_josue</a> | <a href="https://bitcointalk.org/index.php?topic=5547913.msg65520925#msg65520925" target="_blank">Bitcointalk</a>
        </div>
    </footer>

</div>
</body>
</html>
