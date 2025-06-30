<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$DATA_DIR = __DIR__ . '/data';
if (!file_exists($DATA_DIR)) mkdir($DATA_DIR);

$expirations = [
    ''    => 'Never (only deleted after reading)',
    '1h'  => '1 hour',
    '4h'  => '4 hours',
    '8h'  => '8 hours',
    '24h' => '24 hours',
    '3d'  => '3 days',
    '7d'  => '7 days'
];
function expirationToTimestamp($expiration) {
    if (is_numeric($expiration)) return (int)$expiration;
    switch($expiration) {
        case '1h':  return time() + 3600;
        case '4h':  return time() + 4*3600;
        case '8h':  return time() + 8*3600;
        case '24h': return time() + 24*3600;
        case '3d':  return time() + 3*24*3600;
        case '7d':  return time() + 7*24*3600;
        default:    return null;
    }
}
function messageHash($id) {
    return hash('sha256', $id);
}
function registerCreated() {
    $file = __DIR__.'/created.count';
    $total = 1;
    if (file_exists($file)) $total = ((int)file_get_contents($file)) + 1;
    file_put_contents($file, $total, LOCK_EX);
}
function registerDestroyed($id) {
    $hash = messageHash($id);
    $line = date('c') . " DESTROYED $hash";
    file_put_contents(__DIR__.'/destroyed.log', $line."\n", FILE_APPEND | LOCK_EX);
    $file = __DIR__.'/destroyed.count';
    $total = 1;
    if (file_exists($file)) $total = ((int)file_get_contents($file)) + 1;
    file_put_contents($file, $total, LOCK_EX);
}
function registerExpired($id) {
    $hash = messageHash($id);
    $line = date('c') . " EXPIRED $hash";
    file_put_contents(__DIR__.'/expired.log', $line."\n", FILE_APPEND | LOCK_EX);
    $file = __DIR__.'/expired.count';
    $total = 1;
    if (file_exists($file)) $total = ((int)file_get_contents($file)) + 1;
    file_put_contents($file, $total, LOCK_EX);
}
function publicStats() {
    $created    = file_exists(__DIR__.'/created.count')   ? (int)file_get_contents(__DIR__.'/created.count')   : 0;
    $destroyed  = file_exists(__DIR__.'/destroyed.count') ? (int)file_get_contents(__DIR__.'/destroyed.count') : 0;
    $expired    = file_exists(__DIR__.'/expired.count')   ? (int)file_get_contents(__DIR__.'/expired.count')   : 0;
    return [$created, $destroyed, $expired];
}
$EXPIRES_FILE = __DIR__.'/expires.log';

function addGlobalExpiration($id, $expiration) {
    global $EXPIRES_FILE;
    if (!$expiration) return;
    $expires_at = expirationToTimestamp($expiration);
    if (!$expires_at || $expires_at < time()) return;
    $hash = messageHash($id);
    file_put_contents($EXPIRES_FILE, "$hash;$expires_at;$id\n", FILE_APPEND | LOCK_EX);
}
function clearExpiredMessages() {
    global $EXPIRES_FILE, $DATA_DIR;
    if (!file_exists($EXPIRES_FILE)) return;
    $lines = file($EXPIRES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new   = [];
    foreach ($lines as $line) {
        list($hash, $expires_at, $id) = explode(';', $line);
        if (time() > (int)$expires_at) {
            $file = "$DATA_DIR/$id.txt";
            $hashfile = "$DATA_DIR/$id.hash";
            if (file_exists($file)) {
                unlink($file);
                if (file_exists($hashfile)) unlink($hashfile);
                registerExpired($id);
            }
        } else {
            $new[] = $line;
        }
    }
    file_put_contents($EXPIRES_FILE, implode("\n", $new) . (count($new)? "\n" : ""), LOCK_EX);
}
clearExpiredMessages();
function saveMessage($id, $msg, $timelock_ini = 0, $timelock_end = 0) {
    global $DATA_DIR;
    $meta = "$timelock_ini|$timelock_end";
    file_put_contents("$DATA_DIR/$id.txt", $meta."\n".$msg);
}
function readAndDeleteMessage($id, $clientHash = '') {
    global $DATA_DIR;
    $file = "$DATA_DIR/$id.txt";
    $hashfile = "$DATA_DIR/$id.hash";
    if (!file_exists($file)) return false;
    // Hash check (major security!)
    if (!file_exists($hashfile)) return false;
    $savedHash = trim(file_get_contents($hashfile));
    if (empty($clientHash) || strtolower($clientHash) !== strtolower($savedHash)) {
        return ['error' => 'Invalid link or key.'];
    }
    $content = file_get_contents($file);
    $parts = explode("\n", $content, 2);
    $meta = isset($parts[0]) ? $parts[0] : "0|0";
    $msg = isset($parts[1]) ? $parts[1] : "";
    list($timelock_ini, $timelock_end) = explode('|', $meta . '|');
    $timelock_ini = (int)$timelock_ini;
    $timelock_end = (int)$timelock_end;
    $now = time();
    // Time-lock: not yet available
    if ($timelock_ini > 0 && $now < $timelock_ini) {
        return ['error' => 'Message is locked. It will be available after '.date('Y-m-d H:i', $timelock_ini).'!'];
    }
    // Time-lock: window ended
    if ($timelock_end > 0 && $now > $timelock_end) {
        // Auto-delete as expired (unread)
        unlink($file);
        if (file_exists($hashfile)) unlink($hashfile);
        registerExpired($id);
        return ['error' => 'Message has expired. The reading window is over!'];
    }
    // Normal read
    unlink($file);
    if (file_exists($hashfile)) unlink($hashfile);
    return $msg;
}
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'save') {
        // Accept id and hash from frontend for better security
        $id = preg_replace('/[^a-f0-9]/', '', $_POST['id'] ?? '');
        if (strlen($id) !== 16) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Invalid id']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9\+\/\=\.]+$/', $_POST['msg'])) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Invalid content']);
            exit;
        }
        // Time-lock
        $timelock_ini = !empty($_POST['timelock_ini']) ? (int)$_POST['timelock_ini'] : 0;
        $timelock_end = !empty($_POST['timelock_end']) ? (int)$_POST['timelock_end'] : 0;
        saveMessage($id, $_POST['msg'], $timelock_ini, $timelock_end);
        // Save hash in extra file
        $hash = preg_replace('/[^a-f0-9]/', '', $_POST['hash'] ?? '');
        file_put_contents("$DATA_DIR/$id.hash", $hash);
        registerCreated();
        $expiration = '';
        if     (!empty($_POST['expiration_custom'])) $expiration = (int)$_POST['expiration_custom'];
        elseif (!empty($_POST['expiration']))        $expiration = $_POST['expiration'];
        addGlobalExpiration($id, $expiration);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
    if ($_POST['action'] === 'read') {
        $id = preg_replace('/[^a-f0-9]/', '', $_POST['id']);
        $clientHash = preg_replace('/[^a-f0-9]/', '', $_POST['hash'] ?? '');
        $res = readAndDeleteMessage($id, $clientHash);
        if (is_array($res) && isset($res['error'])) {
            echo json_encode(['success' => false, 'error' => $res['error']]);
        } elseif ($res !== false) {
            registerDestroyed($id);
            echo json_encode(['success' => true, 'msg' => $res]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Message not found, already read, or expired.']);
        }
        exit;
    }
    exit;
}
if ($_POST['action'] === 'destroy') {
    $id = preg_replace('/[^a-f0-9]/', '', $_POST['id']);
    $file = "$DATA_DIR/$id.txt";
    $hashfile = "$DATA_DIR/$id.hash";
    if (file_exists($file)) {
        unlink($file);
        if (file_exists($hashfile)) unlink($hashfile);
        registerExpired($id);
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false, 'error'=>'Message not found or already destroyed.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PrivateTalk – Secure Message</title>
    <link rel="stylesheet" href="style.css">
    <meta name="description" content="Send secure, self-destructing messages. Encryption is performed in your browser, with no logs.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="Content-Security-Policy" content="default-src 'self';">
</head>
<body>
<div class="pt-main">

    <!-- Header -->
    <header class="pt-header">
        <h1>PrivateTalk – Secure Message</h1>
        <span class="pt-subtitle">Send 100% private, self-destructing messages. No logs, no tracking.</span>
    </header>

    <!-- Main card: message/options + stats/button -->
    <div class="pt-cardwrap">
        <!-- Left: Message + Options -->
        <section class="pt-card pt-msgcol">
            <div class="info pt-info">
                Write your message below. It will be <b>encrypted in your browser</b> and self-destruct after reading or expiration.
            </div>
            <div id="create-sec">
                <textarea id="message" rows="6" placeholder="Write your message..."></textarea>
                <!-- Advanced Options -->
                <div class="accordion-outer">
                    <button type="button" class="accordion-toggle" id="toggle-accordion">⚙️ Advanced Options</button>
                    <div id="accordion-body" class="accordion-body hidden">
                        <!-- Extra Passphrase -->
                        <div class="opt-balao">
                            <label for="extra-key"><b>Extra Passphrase (optional):</b></label>
                            <input type="text" id="extra-key" class="styled-select" autocomplete="off" maxlength="128" placeholder="Leave blank for no extra protection">
                            <div class="expira-nota mb-8">
                                The message can only be read using this passphrase (in addition to the link).
                            </div>
                        </div>
                        <!-- Auto-Expiration -->
                        <div class="opt-balao">
                            <label for="expiration"><b>Auto-Expiration:</b></label>
                            <select id="expiration" class="styled-select w-44p">
                                <?php foreach($expirations as $v=>$txt): ?>
                                    <option value="<?=$v?>"><?=$txt?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="expira-nota mb-8">
                                Choose a value from the menu <b>or</b> manually set expiration time:
                            </div>
                            <div class="flex gap-10 mb-6">
                                <input type="number" id="expiration_value" class="styled-select w-100" min="1" max="9999" placeholder="#">
                                <select id="expiration_unit" class="styled-select w-90">
                                    <option value="">--</option>
                                    <option value="m">min.</option>
                                    <option value="h">hours</option>
                                    <option value="d">days</option>
                                </select>
                            </div>
                        </div>
                        <!-- Scheduled Message (Time-lock) -->
                        <div class="opt-balao">
                            <label><b>Scheduled message:</b></label>
                            <div class="flex gap-16 align-end mb-6">
                                <div class="flex-col">
                                    <label for="timelock_ini" class="fs-097em">Readable <b>from:</b></label>
                                    <input type="datetime-local" id="timelock_ini" class="styled-select w-170">
                                </div>
                                <div class="flex-col">
                                    <label for="timelock_end" class="fs-097em">...until (optional):</label>
                                    <input type="datetime-local" id="timelock_end" class="styled-select w-170">
                                </div>
                            </div>
                            <div class="expira-nota">
                                Set a start (required) and optionally an end time for when the message can be read.<br>
                                Outside this window, no one will be able to access the message.
                            </div>
                        </div>
                    </div>
                </div>
                <button id="btn-create" class="btn-principal">Generate secure link</button>
            </div>
            
            <!-- Block of generated links, shown after message is created -->
            <div id="link-sec" class="hidden">
                <!-- Sharing Accordions -->
                <div class="accordion">
                    <input type="checkbox" id="option1" checked />
                    <label for="option1"><b>Option 1: Simple Sharing</b></label>
                    <div class="accordion-content">
                        <div class="partilha-simples">
                            <div class="msg-link" id="msg-link-full"></div>
                            <button type="button" class="btn-principal" data-copy="msg-link-full">Copy full link</button>
                            <div class="partilha-nota">
                                Use this link for standard sharing through one channel (<span class="verde">normal privacy</span>).
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion">
                    <input type="checkbox" id="option2" />
                    <label for="option2"><b>Option 2: Dual sharing (maximum privacy)</b></label>
                    <div class="accordion-content">
                        <div class="partilha-dividida">
                            <label class="label-parte">1. Link (without key):</label>
                            <div class="msg-link" id="msg-link-nokey"></div>
                            <button type="button" class="btn-principal" data-copy="msg-link-nokey">Copy link</button>
                            <label class="label-parte">2. Secure fragment (#...):</label>
                            <div class="msg-link" id="msg-link-key"></div>
                            <button type="button" class="btn-principal" data-copy="msg-link-key">Copy fragment</button>
                            <div class="partilha-instrucao">
                                Send the link and key (#...) through different channels (e.g. email + Signal).
                                <br>The recipient just needs to append the key to the end of the link.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EXPORT TO PERSONAL LOG (DASHBOARD) -->
                <div class="export-log-box">
                    <b>Export to personal log (Dashboard):</b><br>
                    <input type="text" id="export-log-input" class="export-log-input" readonly>
                    <button class="export-log-btn" type="button" id="copy-export-log">Copy</button>
                    <div class="export-log-nota">
                        Save this line to your .txt file (you can add a tag after it, e.g., <code>link,tag</code>).<br>
                        Then, in the <a href="dashboard.php" class="verde" target="_blank">Dashboard</a>, you can manage and manually destroy messages.
                    </div>
                </div>

                <!-- Extra details Accordions -->
                <div class="accordion">
                    <input type="checkbox" id="details" />
                    <label for="details"><b>Technical Details</b></label>
                    <div class="accordion-content">
                        <div><b>Message ID:</b> <span class="verde" id="det-id"></span></div>
                        <div><b>Destruction hash:</b> <span class="amarelo" id="det-hash"></span></div>
                    </div>
                </div>
                <div class="accordion">
                    <input type="checkbox" id="help" />
                    <label for="help"><b>Help / FAQ</b></label>
                    <div class="accordion-content">
                        <ul>
                            <li>Copy the personal log line above and use the <b>Dashboard</b> to monitor or destroy messages.</li>
                            <li>The code is open-source and fully auditable.</li>
                            <li>Public destruction/expiration logs are available (<a href="destroyed.log" class="verde" target="_blank">destruction</a> | <a href="expired.log" class="amarelo" target="_blank">expiration</a>).</li>
                        </ul>
                    </div>
                </div>
                <div class="hash-bloco">
                    <b>Destruction proof hash:</b><br>
                    <span id="hash-proof"></span>
                    <button type="button" class="btn-principal" data-copy="hash-proof">Copy hash</button>
                    <div class="hash-nota">
                        Save this hash. After reading or expiration, check the <a href="destroyed.log" target="_blank" class="verde">public log</a> to confirm your message was destroyed/expired.
                    </div>
                </div>
                <button class="btn-principal" id="btn-new-msg">New message</button>
            </div>

            <!-- Message reading block -->
            <div id="read-sec" class="hidden">
                <h3>Secret message:</h3>
                <div class="msg-link" id="msg-read"></div>
                <button class="btn-principal" id="btn-back-read">Back</button>
            </div>

            <!-- Error block -->
            <div id="error-sec" class="hidden">
                <div class="info erro" id="error-text">Message not found, already read, expired, or not yet available.</div>
                <button class="btn-principal" id="btn-back-error">Back</button>
            </div>

        </section>
        <!-- Right: Stats + Dashboard Button -->
        <aside class="pt-card pt-statscol">
            <div class="public-stats info">
                <b>Public statistics:</b><br>
                <?php list($created, $destroyed, $expired) = publicStats(); ?>
                Messages created: <b><?= $created ?></b><br>
                Messages destroyed (read): <b><?= $destroyed ?></b><br>
                Messages expired: <b><?= $expired ?></b><br>
                <a href="destroyed.log" class="verde" target="_blank">View destruction log</a><br>
                <a href="expired.log" class="amarelo" target="_blank">View expiration log</a>
            </div>
            <div class="dashboard-btn-wrap">
                <a href="dashboard.php" class="btn-dashboard">Go to Dashboard</a>
            </div>
        </aside>
    </div>

    <!-- Horizontal FAQ -->
    <section class="pt-faq">
        <div class="faq info">
            <b>How does it work?</b><br>
            <ul>
                <li>The message is encrypted in your browser before being sent to the server.</li>
                <li>You can optionally add an extra passphrase.</li>
                <li>The server <b>never has access</b> to the original text or the encryption key.</li>
                <li>The generated link contains a unique key (<code>#fragment</code>).</li>
                <li>The message is destroyed automatically when read, or expires after the defined time.</li>
                <li>You can set the message to only be read after a specific date/time.</li>
                <li>Messages are only deleted when accessed with the correct key — failed attempts do not erase your message.</li>
                <li>No data, cookies, or visitor tracking are stored.</li>
                <li>Mathematical proof of destruction/expiration is available in public logs.</li>
                <li>Open-source, transparent code for auditability.</li>
            </ul>
            <div class="faq-link">
                Technical questions? <a href="https://bitcointalk.org/index.php?topic=5547913.msg65520925#msg65520925" class="verde" target="_blank">See Bitcointalk Topic</a>.
            </div>
        </div>
    </section>

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

<script src="main.js"></script>
</body>
</html>
