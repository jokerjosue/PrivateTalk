<?php
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
            if (file_exists($file)) {
                unlink($file);
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
function readAndDeleteMessage($id) {
    global $DATA_DIR;
    $file = "$DATA_DIR/$id.txt";
    if (!file_exists($file)) return false;
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
        registerExpired($id);
        return ['error' => 'Message has expired. The reading window is over!'];
    }
    // Normal read
    unlink($file);
    return $msg;
}
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'save') {
        $id = bin2hex(random_bytes(8));
        if (!preg_match('/^[a-zA-Z0-9\+\/\=\.]+$/', $_POST['msg'])) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Invalid content']);
            exit;
        }
        // Time-lock
        $timelock_ini = !empty($_POST['timelock_ini']) ? (int)$_POST['timelock_ini'] : 0;
        $timelock_end = !empty($_POST['timelock_end']) ? (int)$_POST['timelock_end'] : 0;
        saveMessage($id, $_POST['msg'], $timelock_ini, $timelock_end);
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
        $res = readAndDeleteMessage($id);
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
    if (file_exists($file)) {
        unlink($file);
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
                    <button type="button" class="accordion-toggle" onclick="toggleAccordion()">⚙️ Advanced Options</button>
                    <div id="accordion-body" class="accordion-body hidden">
                        <!-- Extra Passphrase -->
                        <div class="opt-balao">
                            <label for="extra-key"><b>Extra Passphrase (optional):</b></label>
                            <input type="text" id="extra-key" class="styled-select" autocomplete="off" maxlength="128" placeholder="Leave blank for no extra protection">
                            <div class="expira-nota" style="margin-bottom:8px;">
                                The message can only be read using this passphrase (in addition to the link).
                            </div>
                        </div>
                        <!-- Auto-Expiration -->
                        <div class="opt-balao">
                            <label for="expiration"><b>Auto-Expiration:</b></label>
                            <select id="expiration" class="styled-select" style="width:44%;" onchange="clearCustomExpiration()">
                                <?php foreach($expirations as $v=>$txt): ?>
                                    <option value="<?=$v?>"><?=$txt?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="expira-nota" style="margin-bottom: 8px;">
                                Choose a value from the menu <b>or</b> manually set expiration time:
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 6px;">
                                <input type="number" id="expiration_value" class="styled-select" style="width: 100px;" min="1" max="9999" placeholder="#">
                                <select id="expiration_unit" class="styled-select" style="width: 90px;" onchange="clearPresetExpiration()">
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
                            <div style="display: flex; gap: 16px; align-items: flex-end; margin-bottom: 6px;">
                                <div style="display: flex; flex-direction: column;">
                                    <label for="timelock_ini" style="font-size:0.97em;">Readable <b>from:</b></label>
                                    <input type="datetime-local" id="timelock_ini" class="styled-select" style="width:170px;">
                                </div>
                                <div style="display: flex; flex-direction: column;">
                                    <label for="timelock_end" style="font-size:0.97em;">...until (optional):</label>
                                    <input type="datetime-local" id="timelock_end" class="styled-select" style="width:170px;">
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
                            <button type="button" class="btn-principal" onclick="copyToClipboard('msg-link-full')">Copy full link</button>
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
                            <button type="button" class="btn-principal" onclick="copyToClipboard('msg-link-nokey')">Copy link</button>
                            <label class="label-parte">2. Secure fragment (#...):</label>
                            <div class="msg-link" id="msg-link-key"></div>
                            <button type="button" class="btn-principal" onclick="copyToClipboard('msg-link-key')">Copy fragment</button>
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
                    <button class="export-log-btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('export-log-input').value)">Copy</button>
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
                    <button type="button" class="btn-principal" onclick="copyToClipboard('hash-proof')">Copy hash</button>
                    <div class="hash-nota">
                        Save this hash. After reading or expiration, check the <a href="destroyed.log" target="_blank" class="verde">public log</a> to confirm your message was destroyed/expired.
                    </div>
                </div>
                <button class="btn-principal" onclick="goToStart()">New message</button>
            </div>

            <!-- Message reading block -->
            <div id="read-sec" class="hidden">
                <h3>Secret message:</h3>
                <div class="msg-link" id="msg-read"></div>
                <button class="btn-principal" onclick="goToStart()">Back</button>
            </div>

            <!-- Error block -->
            <div id="error-sec" class="hidden">
                <div class="info erro" id="error-text">Message not found, already read, expired, or not yet available.</div>
                <button class="btn-principal" onclick="goToStart()">Back</button>
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
                <li>No data, cookies, or visitor tracking are stored.</li>
                <li>Mathematical proof of destruction/expiration is available in public logs.</li>
                <li>Open-source, transparent code for auditability.</li>
            </ul>
            <div class="faq-link">
                Technical questions? <a href="https://github.com/yourusername/PrivateTalk" class="verde" target="_blank">Check the code.</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-footer">
        <div>
            &copy; <?=date('Y')?> <b>PrivateTalk</b> — <a href="https://github.com/jokerjosue/PrivateTalk" target="_blank">Open-Source Code</a>
        </div>
        <div>
            Created by <a href="https://bitcointalk.org/index.php?action=profile;u=97582" target="_blank">@joker_josue</a> | Minimalist design
        </div>
    </footer>
</div>

<script>
// JS functions - adapted to new IDs/names
function toggleAccordion() {
    const body = document.getElementById('accordion-body');
    body.classList.toggle('hidden');
}
function clearCustomExpiration() {
    document.getElementById('expiration_value').value = '';
    document.getElementById('expiration_unit').selectedIndex = 0;
}
function clearPresetExpiration() {
    document.getElementById('expiration').selectedIndex = 0;
}
function toBase64(arr) { return btoa(String.fromCharCode(...new Uint8Array(arr))); }
function fromBase64(str) { return Uint8Array.from(atob(str), c => c.charCodeAt(0)); }
async function deriveKeyFromPassword(password) {
    const enc = new TextEncoder();
    const salt = new Uint8Array([18,42,54,85,100,222,203,7]);
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw", enc.encode(password), {name: "PBKDF2"}, false, ["deriveKey"]);
    return window.crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: salt,
            iterations: 100000,
            hash: "SHA-256"
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        true,
        ["encrypt", "decrypt"]
    );
}
async function generateKey() {
    return window.crypto.subtle.generateKey({name: "AES-GCM", length: 256}, true, ["encrypt", "decrypt"]);
}
async function encryptMsg(message, key) {
    const enc = new TextEncoder();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await window.crypto.subtle.encrypt(
        { name: "AES-GCM", iv: iv },
        key,
        enc.encode(message)
    );
    const keyJwk = await window.crypto.subtle.exportKey("jwk", key);
    return {
        ciphertext: toBase64(new Uint8Array(ciphertext)),
        iv: toBase64(iv),
        key: btoa(JSON.stringify(keyJwk))
    };
}
async function decryptMsg(ciphertextB64, ivB64, keyB64) {
    const keyJwk = JSON.parse(atob(keyB64));
    const key = await window.crypto.subtle.importKey("jwk", keyJwk, {name: "AES-GCM"}, true, ["decrypt"]);
    const dec = new TextDecoder();
    try {
        const msg = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: fromBase64(ivB64) },
            key,
            fromBase64(ciphertextB64)
        );
        return dec.decode(msg);
    } catch (e) {
        return false;
    }
}
async function encryptWithExtraKey(msg, extraKey) {
    const baseKey = await generateKey();
    const enc = await encryptMsg(msg, baseKey);
    if (!extraKey) return enc;
    const passKey = await deriveKeyFromPassword(extraKey);
    const inner = enc.ciphertext + "|" + enc.iv + "|" + enc.key;
    const enc2 = await encryptMsg(inner, passKey);
    return {
        ciphertext: enc2.ciphertext,
        iv: enc2.iv,
        key: enc2.key,
        password: true
    };
}
async function decryptWithExtraKey(ciphertextB64, ivB64, keyB64, extraKey) {
    if (!extraKey) return decryptMsg(ciphertextB64, ivB64, keyB64);
    const passKey = await deriveKeyFromPassword(extraKey);
    const dec = new TextDecoder();
    try {
        const inner = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: fromBase64(ivB64) },
            passKey,
            fromBase64(ciphertextB64)
        );
        const [ciphertext, iv, key] = dec.decode(inner).split('|');
        return decryptMsg(ciphertext, iv, key);
    } catch(e) {
        return false;
    }
}
document.getElementById('btn-create').onclick = async function() {
    const message = document.getElementById('message').value.trim();
    const extraKey = document.getElementById('extra-key').value.trim();
    let expiration     = document.getElementById('expiration').value;
    let value      = document.getElementById('expiration_value').value.trim();
    let unit    = document.getElementById('expiration_unit').value;
    let expiration_custom = '';
    // Time-lock (scheduled message)
    const timelock_ini_raw = document.getElementById('timelock_ini').value;
    const timelock_end_raw = document.getElementById('timelock_end').value;
    let timelock_ini = 0, timelock_end = 0;
    if (timelock_ini_raw) timelock_ini = Math.floor(new Date(timelock_ini_raw).getTime() / 1000);
    if (timelock_end_raw) timelock_end = Math.floor(new Date(timelock_end_raw).getTime() / 1000);

    if (message.length < 2) { alert('Message too short.'); return; }
    if (timelock_end && timelock_ini && timelock_end <= timelock_ini) {
        alert('End date/time must be after the start!');
        return;
    }
    if (value && unit) {
        let seconds = parseInt(value, 10);
        if      (unit === 'm') seconds *= 60;
        else if (unit === 'h') seconds *= 3600;
        else if (unit === 'd') seconds *= 86400;
        else seconds = 0;
        if (seconds > 0) expiration_custom = (Date.now()/1000 | 0) + seconds;
        expiration = '';
    }
    this.disabled = true;
    this.textContent = "Encrypting...";
    const enc = await encryptWithExtraKey(message, extraKey);
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=save&msg=' + encodeURIComponent(enc.ciphertext + '.' + enc.iv) +
            (expiration ? '&expiration=' + encodeURIComponent(expiration) : '') +
            (expiration_custom ? '&expiration_custom=' + encodeURIComponent(expiration_custom) : '') +
            (timelock_ini ? '&timelock_ini=' + encodeURIComponent(timelock_ini) : '') +
            (timelock_end ? '&timelock_end=' + encodeURIComponent(timelock_end) : '')
    })
    .then(response => response.json())
    .then(data => {
        this.disabled = false;
        this.textContent = "Generate secure link";
        if (data.success) {
            let url = location.origin + location.pathname + '?id=' + data.id + '#' + enc.key;
            if (enc.password) url += '-pw';
            const urlNoKey = url.split('#')[0];
            const keyOnly = '#' + url.split('#')[1];
            document.getElementById('msg-link-nokey').textContent = urlNoKey;
            document.getElementById('msg-link-key').textContent = keyOnly;
            const encoder = new TextEncoder();
            crypto.subtle.digest("SHA-256", encoder.encode(data.id)).then(buf => {
                const hashArray = Array.from(new Uint8Array(buf));
                const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                document.getElementById('hash-proof').textContent = hashHex;
                document.getElementById('det-id').textContent = data.id;
                document.getElementById('det-hash').textContent = hashHex;
                document.getElementById('export-log-input').value = url;
            });
            document.getElementById('msg-link-full').textContent = url;
            document.getElementById('create-sec').classList.add('hidden');
            document.getElementById('link-sec').classList.remove('hidden');
        }
    });
};
async function readMessage() {
    const url = new URL(location.href);
    const id = url.searchParams.get('id');
    let key = location.hash.replace('#','');
    let extraKey = '';
    if (key.endsWith('-pw')) {
        key = key.slice(0,-3);
        extraKey = prompt('This message is protected by an extra passphrase. Enter to read:');
        if (extraKey === null) return;
    }
    if (id && key) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=read&id=' + encodeURIComponent(id)
        })
        .then(response => response.json())
        .then(async data => {
            if (data.success) {
                const [ciphertext, iv] = data.msg.split('.');
                const msg = await decryptWithExtraKey(ciphertext, iv, key, extraKey);
                if (msg !== false) {
                    document.getElementById('msg-read').textContent = msg;
                    document.getElementById('read-sec').classList.remove('hidden');
                } else {
                    document.getElementById('error-text').textContent = 'Error decrypting message (wrong passphrase?).';
                    document.getElementById('error-sec').classList.remove('hidden');
                }
            } else {
                document.getElementById('error-text').textContent = data.error || 'Message not found, already read, expired, or not yet available.';
                document.getElementById('error-sec').classList.remove('hidden');
            }
        });
    }
}
function goToStart() {
    document.getElementById('create-sec').classList.remove('hidden');
    document.getElementById('link-sec').classList.add('hidden');
    document.getElementById('read-sec').classList.add('hidden');
    document.getElementById('error-sec').classList.add('hidden');
    document.getElementById('message').value = '';
    if (history.pushState) history.pushState({}, '', location.pathname);
    else location.hash = '';
}
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text)
      .then(() => {
        const button = event.target;
        const original = button.textContent;
        button.textContent = "Copied!";
        setTimeout(()=>{ button.textContent = original; }, 1200);
      });
}
window.onload = function() {
    const url = new URL(location.href);
    const id = url.searchParams.get('id');
    let key = location.hash.replace('#','');
    let extraKey = '';
    if (id && key) {
        document.getElementById('create-sec').classList.add('hidden');
        readMessage();
    }
}
</script>
</body>
</html>
