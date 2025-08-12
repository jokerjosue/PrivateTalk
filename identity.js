// ===================== Identity (ECC) — WebCrypto (P-256, ECIES-like) =====================
// All private keys remain client-side. No secrets are sent to the server.

// ---------- Small helpers ----------
const $ = (id) => document.getElementById(id);
const enc = new TextEncoder();
const dec = new TextDecoder();

function toBase64(arr) { return btoa(String.fromCharCode(...new Uint8Array(arr))); }
function fromBase64(str) { return Uint8Array.from(atob(str), c => c.charCodeAt(0)); }
// Base64url (no padding)
function b64url(bytes) {
  return toBase64(bytes).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');
}
function fromB64url(str) {
  const pad = str.length % 4 === 2 ? "==" : str.length % 4 === 3 ? "=" : str.length % 4 === 1 ? "===" : "";
  const s = str.replace(/-/g, '+').replace(/_/g, '/') + pad;
  return fromBase64(s);
}
// Random hex for ID (16 bytes = 32 hex chars)
function randIdHex128() {
  const a = new Uint8Array(16); crypto.getRandomValues(a);
  return Array.from(a).map(b=>b.toString(16).padStart(2,'0')).join('');
}
// Random b64url token (16 bytes -> 22 chars)
function randTokenB64url() {
  const a = new Uint8Array(16); crypto.getRandomValues(a);
  return b64url(a);
}
function show(el) { el.classList.remove('hidden'); }
function hide(el) { el.classList.add('hidden'); }

// ---------- Key export/import (PTPUB1/PTPRIV1) ----------
async function genKeyPair() {
  return await crypto.subtle.generateKey(
    { name: "ECDH", namedCurve: "P-256" },
    true,
    ["deriveBits"]
  );
}
async function exportPublicRaw(kp) {
  const raw = await crypto.subtle.exportKey("raw", kp.publicKey);
  return "PTPUB1:" + b64url(new Uint8Array(raw));
}
async function exportPrivatePKCS8(kp) {
  const pkcs8 = await crypto.subtle.exportKey("pkcs8", kp.privateKey);
  return "PTPRIV1:" + b64url(new Uint8Array(pkcs8));
}
async function importPublicFromPT(pubStr) {
  if (!pubStr.startsWith("PTPUB1:")) throw new Error("Invalid public key format");
  const raw = fromB64url(pubStr.slice(7));
  return crypto.subtle.importKey("raw", raw, { name: "ECDH", namedCurve: "P-256" }, true, []);
}
async function importPrivateFromPT(privStr) {
  if (!privStr.startsWith("PTPRIV1:")) throw new Error("Invalid private key format");
  const pkcs8 = fromB64url(privStr.slice(8));
  return crypto.subtle.importKey("pkcs8", pkcs8, { name: "ECDH", namedCurve: "P-256" }, false, ["deriveBits"]);
}

// ---------- ECIES-like ----------
async function hkdfAesKey(sharedSecretBytes, saltBytes, infoStr) {
  const secret = await crypto.subtle.importKey("raw", sharedSecretBytes, "HKDF", false, ["deriveKey"]);
  return crypto.subtle.deriveKey(
    { name: "HKDF", salt: saltBytes, info: enc.encode(infoStr), hash: "SHA-256" },
    secret,
    { name: "AES-GCM", length: 256 },
    false,
    ["encrypt","decrypt"]
  );
}
async function eciesEncryptForPublic(recipientPubKey, plaintext, aadId) {
  const eph = await genKeyPair();
  const ephRaw = new Uint8Array(await crypto.subtle.exportKey("raw", eph.publicKey));
  const secret = await crypto.subtle.deriveBits({ name: "ECDH", public: recipientPubKey }, eph.privateKey, 256);
  const shared = new Uint8Array(secret);
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const aesKey = await hkdfAesKey(shared, salt, "PTv2-ECIES");
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const cipher = await crypto.subtle.encrypt(
    { name: "AES-GCM", iv, additionalData: enc.encode(aadId) },
    aesKey,
    enc.encode(plaintext)
  );
  return btoa(JSON.stringify({
    v:2, scheme:"ECIES-P256-HKDF-SHA256-AESGCM",
    epk_raw: b64url(ephRaw), salt: b64url(salt), iv: b64url(iv),
    ct: b64url(new Uint8Array(cipher)), aad: "id"
  }));
}
async function eciesDecryptWithPrivate(privateKey, payloadB64, aadId) {
  const obj = JSON.parse(atob(payloadB64));
  const epk = await crypto.subtle.importKey("raw", fromB64url(obj.epk_raw), { name:"ECDH", namedCurve:"P-256" }, true, []);
  const secret = await crypto.subtle.deriveBits({ name:"ECDH", public: epk }, privateKey, 256);
  const shared = new Uint8Array(secret);
  const aesKey = await hkdfAesKey(shared, fromB64url(obj.salt), "PTv2-ECIES");
  const plainBuf = await crypto.subtle.decrypt(
    { name:"AES-GCM", iv: fromB64url(obj.iv), additionalData: enc.encode(aadId) },
    aesKey,
    fromB64url(obj.ct)
  );
  return dec.decode(plainBuf);
}

// ---------- Save file ----------
function downloadText(filename, text) {
  const blob = new Blob([text], {type: 'text/plain'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href = url; a.download = filename; a.click();
  setTimeout(()=>URL.revokeObjectURL(url), 500);
}

// ---------- Encrypt & Save ----------
async function handleEncryptAndSave() {
  const destPub = $('dest-pub').value.trim();
  const message = $('msg-plain').value;
  if (message.length < 2) { alert('Message too short.'); return; }

  let pubKey;
  try { pubKey = await importPublicFromPT(destPub); }
  catch(e) { alert('Invalid recipient public key.'); return; }

  $('btn-enc').disabled = true; $('btn-enc').textContent = 'Encrypting...';
  try {
    const id = randIdHex128();
    const rt = randTokenB64url();
    const payloadB64 = await eciesEncryptForPublic(pubKey, message, id);

    const body = new URLSearchParams({ action:'save_ec', id, rt, msg: payloadB64 });
    const res = await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
    const data = await res.json();
    if (!data.success) { alert(data.error || 'Error saving message.'); return; }

    const link = location.origin + location.pathname + '?id=' + id + '&rt=' + rt;
    $('link-out').textContent = link;
    show($('link-box'));
  } catch(e) {
    alert('Error: ' + e.message);
  } finally {
    $('btn-enc').disabled = false; $('btn-enc').textContent = 'Generate secure link';
  }
}

// ---------- Read ----------
async function handleRead() {
  const url = new URL(location.href);
  const id = url.searchParams.get('id') || '';
  const rt = url.searchParams.get('rt') || '';
  const privStr = $('priv-read').value.trim();
  if (!privStr) { alert('Paste your private key.'); return; }

  let privKey;
  try { privKey = await importPrivateFromPT(privStr); }
  catch(e) { alert('Invalid private key.'); return; }

  try {
    const body = new URLSearchParams({ action:'read_ec', id, rt });
    const res = await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Not available');

    const plain = await eciesDecryptWithPrivate(privKey, data.msg, id);
    $('plain-out').textContent = plain;
    hide($('read-err')); show($('read-ok'));

    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'confirm_read', id, rt }) }).catch(()=>{});
  } catch(e) {
    hide($('read-ok'));
    $('read-err').textContent = e.message || 'Error reading message.';
    show($('read-err'));
  }
}

// ---------- Key management ----------
let currentKP = null;
async function handleGenKey() {
  currentKP = await genKeyPair();
  $('pub-out').value  = await exportPublicRaw(currentKP);
  $('priv-out').value = await exportPrivatePKCS8(currentKP);
}
function handleSavePriv() {
  const t = $('priv-out').value.trim();
  if (t) downloadText('privkey-' + Date.now() + '.key', t);
}
async function handlePrivFileLoad(inputEl, nameSpan, targetInput) {
  const f = inputEl.files && inputEl.files[0];
  if (!f) return;
  nameSpan.textContent = f.name;
  const txt = await f.text();
  targetInput.value = txt.trim();
}

// ---------- DOM Ready ----------
document.addEventListener('DOMContentLoaded', () => {
  // Copy buttons
  document.querySelectorAll('button[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-copy');
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        const text = targetEl.value !== undefined ? targetEl.value : targetEl.textContent;
        navigator.clipboard.writeText(text.trim())
          .then(() => {
            const old = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = old, 1500);
          });
      }
    });
  });

  // Main event listeners
  $('btn-gen-key')?.addEventListener('click', handleGenKey);
  $('btn-save-priv')?.addEventListener('click', handleSavePriv);
  $('btn-enc')?.addEventListener('click', handleEncryptAndSave);
  $('btn-read')?.addEventListener('click', handleRead);
  $('priv-file')?.addEventListener('change', ()=>handlePrivFileLoad($('priv-file'), $('file-name'), $('priv-in')));
  $('priv-read-file')?.addEventListener('change', ()=>handlePrivFileLoad($('priv-read-file'), $('file-name-read'), $('priv-read')));

  // Toggle compose/read
  const url = new URL(location.href);
  const hasReadParams = !!(url.searchParams.get('id') && url.searchParams.get('rt'));
  const compose = $('compose-section');
  const read    = $('read-section');
  if (compose && read) {
    if (hasReadParams) { compose.classList.add('hidden'); read.classList.remove('hidden'); }
    else { compose.classList.remove('hidden'); read.classList.add('hidden'); }
  }
});
