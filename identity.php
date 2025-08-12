<?php
// ---------------- Security headers ----------------
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:;");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: clipboard-write=(self), geolocation=(), camera=(), microphone=(), payment=()");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// ---- Extra isolation headers ----
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");

// ---------------- Basic config ----------------
$DATA_DIR = __DIR__ . '/data';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0700, true); }

// ---- Limits & constants ----
define('MAX_MSG_LEN', 131072);
define('READ_CONFIRM_WINDOW', 600);

// ---------------- Helpers ----------------
function is_hex_id_128($id) { return (bool)preg_match('/^[a-f0-9]{32}$/', $id); }
function is_b64url_token($t) { return (bool)preg_match('/^[A-Za-z0-9_\-]{22}$/', $t); }
function json_response($arr) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode($arr); exit; }
function safe_read($path) { if (!is_file($path)) return false; $c = @file_get_contents($path); return ($c === false) ? false : $c; }
function safe_write($path, $content) { $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp'; if (@file_put_contents($tmp, $content, LOCK_EX) === false) return false; return @rename($tmp, $path); }
function safe_unlink($path) { if (is_file($path)) @unlink($path); }
function timing_safe_equals($a, $b) { return is_string($a) && is_string($b) && hash_equals($a, $b); }
function same_origin_required() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $origin = $_SERVER['HTTP_ORIGIN']  ?? '';
    $refer  = $_SERVER['HTTP_REFERER'] ?? '';
    $toCheck = $origin ?: $refer;
    if (!$toCheck) return true;
    $parts = parse_url($toCheck);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    $base  = $parts['scheme'] . '://' . $parts['host'];
    return stripos($host, $base) === 0;
}

// ---------------- API (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin_required()) { http_response_code(403); json_response(['success' => false, 'error' => 'Forbidden.']); }

    $action = $_POST['action'] ?? '';

    // SAVE (ECC)
    if ($action === 'save_ec') {
        $id = strtolower($_POST['id'] ?? '');
        $rt = $_POST['rt'] ?? '';
        $msg = $_POST['msg'] ?? '';

        if (!is_hex_id_128($id) || !is_b64url_token($rt)) { json_response(['success'=>false,'error'=>'Invalid parameters.']); }
        if (strlen($msg) < 16 || strlen($msg) > MAX_MSG_LEN) { json_response(['success'=>false,'error'=>'Payload size rejected.']); }
        $decoded = base64_decode($msg, true); if ($decoded === false) { json_response(['success'=>false,'error'=>'Bad encoding.']); }
        $payloadObj = json_decode($decoded, true); if (!is_array($payloadObj) || empty($payloadObj['ct'])) { json_response(['success'=>false,'error'=>'Corrupted payload.']); }

        $msgPath = $DATA_DIR . "/$id.txt"; $rtPath = $DATA_DIR . "/$id.rt";
        if (is_file($msgPath) || is_file($rtPath)) { json_response(['success'=>false,'error'=>'ID already exists. Try again.']); }
        if (!safe_write($msgPath, $msg)) { json_response(['success'=>false,'error'=>'Write error (message).']); }

        $meta = [
            'rt'=>$rt,'created'=>time(),
            'status'=>'active','reading_since'=>0
        ];
        if (!safe_write($rtPath, json_encode($meta, JSON_UNESCAPED_SLASHES))) {
            safe_unlink($msgPath); json_response(['success'=>false,'error'=>'Write error (token).']);
        }
        json_response(['success'=>true]);
    }

    // READ (ECC)
    if ($action === 'read_ec') {
        $id = strtolower($_POST['id'] ?? ''); $rt = $_POST['rt'] ?? '';
        if (!is_hex_id_128($id) || !is_b64url_token($rt)) { json_response(['success'=>false,'error'=>'Not available.']); }
        $msgPath = $DATA_DIR . "/$id.txt"; $rtPath = $DATA_DIR . "/$id.rt";
        if (!is_file($msgPath) || !is_file($rtPath)) { json_response(['success'=>false,'error'=>'Not available.']); }

        $metaRaw = safe_read($rtPath); $meta = $metaRaw ? json_decode($metaRaw, true) : null;
        if (!$meta || empty($meta['rt']) || !timing_safe_equals($meta['rt'], $rt) || ($meta['status'] ?? '') !== 'active') {
            json_response(['success'=>false,'error'=>'Not available.']);
        }

        $payload = safe_read($msgPath); if ($payload === false) { json_response(['success'=>false,'error'=>'Not available.']); }

        $meta['status']='reading'; $meta['reading_since']=time(); @safe_write($rtPath, json_encode($meta, JSON_UNESCAPED_SLASHES));
        json_response(['success'=>true,'msg'=>$payload]);
    }

    // CONFIRM READ (ECC)
    if ($action === 'confirm_read') {
        $id = strtolower($_POST['id'] ?? ''); $rt = $_POST['rt'] ?? '';
        if (!is_hex_id_128($id) || !is_b64url_token($rt)) { json_response(['success'=>false,'error'=>'Not available.']); }
        $msgPath = $DATA_DIR . "/$id.txt"; $rtPath = $DATA_DIR . "/$id.rt";
        if (!is_file($rtPath)) { json_response(['success'=>false,'error'=>'Not available.']); }
        $metaRaw = safe_read($rtPath); $meta = $metaRaw ? json_decode($metaRaw, true) : null;
        if (!$meta || empty($meta['rt']) || !timing_safe_equals($meta['rt'], $rt)) { json_response(['success'=>false,'error'=>'Not available.']); }

        safe_unlink($msgPath); safe_unlink($rtPath);
        json_response(['success'=>true,'deleted'=>true]);
    }

    json_response(['success' => false, 'error' => 'Unknown action.']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PrivateTalk – Secure Identity</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="pt-main">
  <div class="pt-header">
    <h1>PrivateTalk – Secure Identity</h1>
    <div class="pt-subtitle">Send and receive fully private messages using your own public and private keys. Your keys never leave your browser.</div>
    <div class="pt-subtitle"><a href="./">Back to PrivateTalk</a></div>
  </div>

  <div class="pt-cardwrap">
    <section class="pt-card pt-msgcol">
      <div class="info pt-info" id="info-block">
        Here you can <b>write and encrypt</b> a message for a public key (PTPUB1). 
        Only someone with the corresponding <b>private key</b> will be able to read it.
      </div>

      <div id="compose-section">
        <label class="label-parte">Recipient public key (PTPUB1):</label>
        <input type="text" id="dest-pub" placeholder="PTPUB1:..." autocomplete="off" spellcheck="false">

        <label class="label-parte">Message:</label>
        <textarea id="msg-plain" rows="6" placeholder="Write your message…"></textarea>

        <button id="btn-enc" class="btn-principal">Generate secure link</button>

        <div id="link-box" class="hash-bloco hidden">
          <label><b>Link Sharing</b></label>
          <div class="msg-link" id="link-out"></div>
          <button data-copy="link-out">Copy link</button>
          <div class="hash-nota">Share only this link.</div>
        </div>
      </div>

      <div id="read-section" class="hidden">
        <div class="info">
          Open the link you received and provide your <b>private key</b> to decrypt locally.
        </div>

        <label class="label-parte">Private key (PTPRIV1) or upload .key:</label>
        <input type="text" id="priv-read" placeholder="PTPRIV1:..." autocomplete="off" spellcheck="false">
        <input type="file" id="priv-read-file" accept=".key,.txt">
        <label for="priv-read-file" class="upload-btn">Upload .key</label>
        <span id="file-name-read"></span>

        <br><button id="btn-read" class="btn-principal">Decrypt</button>

        <div id="read-ok" class="info hidden mt-12 bg-soft">
          <div class="verde"><b>Decrypted message:</b></div>
          <div id="plain-out" class="msg-link prewrap"></div>
        </div>

        <div id="read-err" class="info erro hidden">
          Unable to decrypt. Check your private key.
        </div>
      </div>
    </section>

    <aside class="pt-card pt-statscol">
      <h2 class="verde mt-0">Create Identity</h2>
      <div class="opt-balao">
        <button id="btn-gen-key" class="btn-principal">Generate new key pair</button>
        <div class="info mt-10">The <b>private key</b> is never stored. Save it yourself.</div>

        <label class="label-parte">Public Key:</label>
        <textarea id="pub-out" rows="3" readonly></textarea>
        <div class="flex gap-10">
          <button data-copy="pub-out" class="btn-principal">Copy public</button>
        </div>

        <hr class="hr-dark">
        <label class="label-parte">Private Key:</label>
        <textarea id="priv-out" rows="4" readonly></textarea>
        <div class="flex gap-10">
          <button id="btn-save-priv" class="btn-principal">Download .key</button>
          <button data-copy="priv-out" class="btn-principal">Copy private</button>
        </div>
      </div>
    </aside>
  </div>

	<!-- Horizontal FAQ -->
	<section class="pt-faq">
	  <div class="faq info">
		<b>Quick Instructions</b><br>

		<u>Messaging</u>
		<ul>
		  <li><b>To write:</b> In the left block, paste the recipient’s <em>PTPUB1</em>, write your message, and click “Generate secure link”.</li>
		  <li><b>To read:</b> Open a link containing <code>?id=...&rt=...</code>. The left block will switch to “Read message” and ask for your <em>PTPRIV1</em>.</li>
		  <li><b>Privacy:</b> Private keys and plaintext messages never leave your browser. The server only stores the encrypted <em>blob</em> and minimal metadata.</li>
		  <li><b>Message destruction:</b> After a successful read, the message is permanently deleted from the server.</li>
		</ul>

		<u>Identity creation</u>
		<ul>
		  <li><b>Generate keys:</b> In the right block, click “Generate new key pair” to create your <em>PTPUB1</em> (public) and <em>PTPRIV1</em> (private) keys.</li>
		  <li><b>Public key:</b> Starts with <code>PTPUB1:</code>. Share it with others so they can send you encrypted messages.</li>
		  <li><b>Private key:</b> Starts with <code>PTPRIV1:</code>. Keep it secret — never share it. Use it to decrypt messages sent to your public key.</li>
		  <li><b>Backup:</b> Download your private key as a <code>.key</code> file or copy it manually. Without it, you will not be able to read messages sent to you.</li>
		</ul>

		<div class="faq-link">
		  Technical questions? <a href="https://bitcointalk.org/index.php?topic=5547913.msg65520925#msg65520925" class="verde" target="_blank">See Bitcointalk Topic</a>.
		</div>
	  </div>
	</section>


  <!-- Footer -->
  <footer class="pt-footer">
    <div>
      &copy; <?=date('Y')?> <b>PrivateTalk</b> v2.0.0 — <a href="https://github.com/jokerjosue/PrivateTalk" target="_blank">Open-Source Code</a>
    </div>
    <div>
      Created by <a href="https://bitcointalk.org/index.php?action=profile;u=97582" target="_blank">@joker_josue</a> | <a href="https://bitcointalk.org/index.php?topic=5547913.msg65520925#msg65520925" target="_blank">Bitcointalk</a>
    </div>
  </footer>
</div>

<script src="identity.js"></script>
</body>
</html>
