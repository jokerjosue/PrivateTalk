// ========== Core JS functions ==========

// Dynamic info texts for different page states
const infoTexts = {
    create: "Write your message below. It will be <b>encrypted in your browser</b> and self-destruct after reading or expiration.",
    link:   "Your secure message link has been generated! Share it safely. You can destroy it from the dashboard at any time.",
    read:   "Below is your decrypted message. Remember: it has now been destroyed and cannot be read again.",
    error:  "Could not find or decrypt the message. Please check your link and password, or contact the sender."
};
function setInfoText(state) {
    const block = document.getElementById('info-block');
    if (!block) return;
    block.innerHTML = infoTexts[state] || infoTexts.create;
}

// --- Variables to support password retry for encrypted messages ---
let encryptedData = null;
let decryptAttempts = 0;
const MAX_ATTEMPTS = 5;

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
async function deriveKeyFromPassword(password, salt) {
    const enc = new TextEncoder();
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
    // Generate random salt (16 bytes)
    const salt = window.crypto.getRandomValues(new Uint8Array(16));
    const passKey = await deriveKeyFromPassword(extraKey, salt);
    // Encrypt the inner message with passphrase key
    const inner = enc.ciphertext + "|" + enc.iv + "|" + enc.key;
    const enc2 = await encryptMsg(inner, passKey);
    // Save salt with the key, as a JSON (for base64 transmission)
    const keyWithSalt = btoa(JSON.stringify({
        key: enc2.key,
        salt: toBase64(salt)
    }));
    return {
        ciphertext: enc2.ciphertext,
        iv: enc2.iv,
        key: keyWithSalt,
        password: true
    };
}
async function decryptWithExtraKey(ciphertextB64, ivB64, keyB64, extraKey) {
    if (!extraKey) return decryptMsg(ciphertextB64, ivB64, keyB64);
    // Try to parse JSON {key, salt}; fallback to previous (just key string)
    let parsed, passKey, salt;
    try {
        parsed = JSON.parse(atob(keyB64));
        salt = fromBase64(parsed.salt);
        passKey = await deriveKeyFromPassword(extraKey, salt);
        keyB64 = parsed.key; // update with real encryption key
    } catch(e) {
        // Compatibility: fallback to fixed salt
        const fallbackSalt = new Uint8Array([18,42,54,85,100,222,203,7]);
        passKey = await deriveKeyFromPassword(extraKey, fallbackSalt);
    }
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
// Utility: generate hash from id+key, returns hex
async function calcMessageHash(id, key) {
    const encoder = new TextEncoder();
    const data = id + key;
    const buf = await crypto.subtle.digest("SHA-256", encoder.encode(data));
    return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
}

async function readMessage() {
    const url = new URL(location.href);
    const id = url.searchParams.get('id');
    let key = location.hash.replace('#','');
    let extraKey = '';
    if (key.endsWith('-pw')) {
        key = key.slice(0,-3);
        // Do not prompt here; wait until we have the encrypted message!
    }
    if (id && key) {
        // Calculate hash from id+key and send to server
        const hash = await calcMessageHash(id, key);
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=read&id=' + encodeURIComponent(id) + '&hash=' + encodeURIComponent(hash)
        })
        .then(response => response.json())
        .then(async data => {
            if (data.success) {
                // Store the encrypted message and iv globally
                const [ciphertext, iv] = data.msg.split('.');
                encryptedData = {ciphertext, iv, key};
                decryptAttempts = 0;
                // If message is NOT password protected, decrypt and show directly
                if (!location.hash.endsWith('-pw')) {
                    const msg = await decryptWithExtraKey(ciphertext, iv, key, '');
                    if (msg !== false) {
                        document.getElementById('msg-read').textContent = msg;
                        document.getElementById('read-sec').classList.remove('hidden');
                        setInfoText('read');
                    } else {
                        showDecryptError();
                    }
                } else {
                    showPasswordRetry();
                }
            } else {
                document.getElementById('error-text').textContent = data.error || 'Message not found, already read, expired, or not yet available.';
                document.getElementById('error-sec').classList.remove('hidden');
                setInfoText('error');
            }
        });
    }
}

function showPasswordRetry() {
    document.getElementById('error-sec').classList.add('hidden');
    document.getElementById('read-sec').classList.add('hidden');
    document.getElementById('pw-retry-block').classList.remove('hidden');
    document.getElementById('pw-try-count').textContent = (decryptAttempts + 1);
    document.getElementById('pw-retry-input').value = '';
    document.getElementById('pw-retry-input').focus();
}
async function handlePasswordRetry() {
    const password = document.getElementById('pw-retry-input').value;
    if (!password) {
        document.getElementById('pw-retry-input').focus();
        return;
    }
    decryptAttempts++;
    document.getElementById('pw-try-count').textContent = decryptAttempts;
    const {ciphertext, iv, key} = encryptedData;
    const msg = await decryptWithExtraKey(ciphertext, iv, key, password);
    if (msg !== false) {
        document.getElementById('msg-read').textContent = msg;
        document.getElementById('read-sec').classList.remove('hidden');
        document.getElementById('pw-retry-block').classList.add('hidden');
        encryptedData = null;
        setInfoText('read');
    } else {
        if (decryptAttempts < MAX_ATTEMPTS) {
            document.getElementById('pw-retry-msg').innerHTML =
                `Wrong passphrase. Please try again (attempt <span id="pw-try-count">${decryptAttempts + 1}</span>/5).<br><small>Do not refresh or close the page, or you will lose the message!</small>`;
            document.getElementById('pw-retry-input').value = '';
            document.getElementById('pw-retry-input').focus();
        } else {
            document.getElementById('pw-retry-msg').innerHTML =
                `<b>Maximum attempts reached.</b> The message cannot be decrypted. Please make sure you have the correct passphrase before opening the link.`;
            document.getElementById('pw-retry-input').disabled = true;
            document.getElementById('pw-retry-btn').disabled = true;
            encryptedData = null;
        }
    }
}
function showDecryptError() {
    document.getElementById('error-text').textContent = 'Decryption error. Message may be corrupted or the link is invalid.';
    document.getElementById('error-sec').classList.remove('hidden');
    setInfoText('error');
}
async function createMessage(event) {
    const btn = event.target;
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
    btn.disabled = true;
    btn.textContent = "Encrypting...";
    // Generate random id (8 bytes = 16 hex chars)
    const arr = new Uint8Array(8);
    window.crypto.getRandomValues(arr);
    const id = Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
    const enc = await encryptWithExtraKey(message, extraKey);
    // Calculate hash from id+key
    const keyForHash = enc.key;
    const hash = await calcMessageHash(id, keyForHash);
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body:
            'action=save' +
            '&id=' + encodeURIComponent(id) +
            '&msg=' + encodeURIComponent(enc.ciphertext + '.' + enc.iv) +
            '&hash=' + encodeURIComponent(hash) +
            (expiration ? '&expiration=' + encodeURIComponent(expiration) : '') +
            (expiration_custom ? '&expiration_custom=' + encodeURIComponent(expiration_custom) : '') +
            (timelock_ini ? '&timelock_ini=' + encodeURIComponent(timelock_ini) : '') +
            (timelock_end ? '&timelock_end=' + encodeURIComponent(timelock_end) : '')
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = "Generate secure link";
        if (data.success) {
            let url = location.origin + location.pathname + '?id=' + id + '#' + enc.key;
            if (enc.password) url += '-pw';
            const urlNoKey = url.split('#')[0];
            const keyOnly = '#' + url.split('#')[1];
            document.getElementById('msg-link-nokey').textContent = urlNoKey;
            document.getElementById('msg-link-key').textContent = keyOnly;
            const encoder = new TextEncoder();
            crypto.subtle.digest("SHA-256", encoder.encode(id)).then(buf => {
                const hashArray = Array.from(new Uint8Array(buf));
                const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                document.getElementById('hash-proof').textContent = hashHex;
                document.getElementById('det-id').textContent = id;
                document.getElementById('det-hash').textContent = hashHex;
                document.getElementById('export-log-input').value = url;
            });
            document.getElementById('msg-link-full').textContent = url;
            document.getElementById('create-sec').classList.add('hidden');
            document.getElementById('link-sec').classList.remove('hidden');
            setInfoText('link');
        }
    });
}

// Resets UI to start state (full form reset)
function goToStart() {
    encryptedData = null;
    decryptAttempts = 0;
    // Hide read/erro/password retry blocks
    document.getElementById('pw-retry-block').classList.add('hidden');
    document.getElementById('pw-retry-input').value = '';
    document.getElementById('pw-retry-input').disabled = false;
    document.getElementById('pw-retry-btn').disabled = false;
    document.getElementById('create-sec').classList.remove('hidden');
    document.getElementById('link-sec').classList.add('hidden');
    document.getElementById('read-sec').classList.add('hidden');
    document.getElementById('error-sec').classList.add('hidden');
    // Clear all form fields
    document.getElementById('message').value = '';
    document.getElementById('extra-key').value = '';
    document.getElementById('expiration').selectedIndex = 0;
    document.getElementById('expiration_value').value = '';
    document.getElementById('expiration_unit').selectedIndex = 0;
    document.getElementById('timelock_ini').value = '';
    document.getElementById('timelock_end').value = '';
    // Hide advanced options
    document.getElementById('accordion-body').classList.add('hidden');
    if (history.pushState) history.pushState({}, '', location.pathname);
    else location.hash = '';
    setInfoText('create');
}

// Copies the textContent or value of the target element to clipboard and gives feedback on button
function copyToClipboard(elementId, button) {
    const el = document.getElementById(elementId);
    if (!el) return;
    // Prefer textContent (for divs), fallback to value (for inputs)
    const text = (typeof el.textContent === "string" ? el.textContent : (el.value || ""));
    navigator.clipboard.writeText(text)
      .then(() => {
        if (button && typeof button.textContent !== "undefined") {
            const original = button.textContent;
            button.textContent = "Copied!";
            setTimeout(() => { button.textContent = original; }, 1200);
        }
      });
}

// ========== DOMContentLoaded: all event listeners ==========
document.addEventListener('DOMContentLoaded', function () {
    // Accordion toggle
    const accordionBtn = document.getElementById('toggle-accordion');
    if (accordionBtn) {
        accordionBtn.addEventListener('click', toggleAccordion);
    }
    // Password retry button listener
    const pwRetryBtn = document.getElementById('pw-retry-btn');
    if (pwRetryBtn) {
        pwRetryBtn.addEventListener('click', handlePasswordRetry);
    }
    // Expiration select listeners
    const expiration = document.getElementById('expiration');
    if (expiration) {
        expiration.addEventListener('change', clearCustomExpiration);
    }
    const expirationUnit = document.getElementById('expiration_unit');
    if (expirationUnit) {
        expirationUnit.addEventListener('change', clearPresetExpiration);
    }
    // Create message button
    const btnCreate = document.getElementById('btn-create');
    if (btnCreate) {
        btnCreate.addEventListener('click', createMessage);
    }
    // Export log copy
    const exportLogBtn = document.getElementById('copy-export-log');
    if (exportLogBtn) {
        exportLogBtn.addEventListener('click', function () {
            const input = document.getElementById('export-log-input');
            if (input) navigator.clipboard.writeText(input.value);
        });
    }
    // Navigation buttons
    const btnNewMsg = document.getElementById('btn-new-msg');
    if (btnNewMsg) btnNewMsg.addEventListener('click', goToStart);
    const btnBackRead = document.getElementById('btn-back-read');
    if (btnBackRead) btnBackRead.addEventListener('click', goToStart);
    const btnBackError = document.getElementById('btn-back-error');
    if (btnBackError) btnBackError.addEventListener('click', goToStart);
    // Robust event delegation for all [data-copy] buttons (works for dynamic and static buttons)
    document.addEventListener('click', function(event) {
        // Search up to find the button or element with data-copy attribute
        let target = event.target;
        while (target && target !== document && !target.hasAttribute('data-copy')) {
            target = target.parentElement;
        }
        if (target && target.hasAttribute('data-copy')) {
            // Pass the button and the target id
            copyToClipboard(target.getAttribute('data-copy'), target);
        }
    });
    // Message reading logic (when loading with ?id=...#key)
    const url = new URL(location.href);
    const id = url.searchParams.get('id');
    let key = location.hash.replace('#','');
    if (id && key) {
        document.getElementById('create-sec').classList.add('hidden');
        readMessage();
    } else {
        setInfoText('create');
    }
});
