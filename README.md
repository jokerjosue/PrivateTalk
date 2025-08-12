# PrivateTalk

PrivateTalk is a lightweight, open-source tool for sending encrypted, self-destructing messages.  
Focused on maximum privacy, transparency, and auditability, PrivateTalk never stores messages in plain text or requires any registration.



## 🚀 New in v2.0.0 — Identity (ECC)

PrivateTalk now includes the **Identity (ECC)** module, allowing secure messaging using asymmetric encryption (P-256).  
Users can generate a public key (**PTPUB1**) to share and a private key (**PTPRIV1**) to keep secret.  
Messages sent to your public key can only be read by you — and are destroyed after being decrypted.



## Official Site

Access the live public instance here:

🔗 [https://talk2tag.com/privatetalk/](https://talk2tag.com/privatetalk/)

This is the main, always-on, production version.

> *Note: Free to use. As always, do not trust sensitive data to any service unless you have personally reviewed and audited the code!*



## Features

- **Client-side encryption:** Messages are encrypted in your browser (AES-GCM), never on the server.
- **One-time access:** Messages can only be read once; they're destroyed immediately after being viewed.
- **Optional extra password:** Add a second password for double protection (optional).
- **Custom expiration:** Define automatic expiration (in hours or days) or a specific time window ("time-lock").
- **No tracking:** No cookies, logs, or tracking of any kind. No analytics scripts.
- **No database:** All data is stored in simple flat files for maximum transparency and easy self-hosting.
- **Public logs:** Self-destruction and expiration logs are available for independent verification (SHA-256 hashes).
- **Minimalist UI:** Responsive design for desktop and mobile, with a focus on usability.
- **Local dashboard:** Easily track and manually destroy your own messages by uploading your log file (no server-side tracking).
- **True One-Time Read:** Messages are only destroyed after a successful decryption with the correct key. Invalid attempts do NOT erase or reveal the message.
- **Prevents "premature" destruction:** Messages are NOT deleted if someone tries to access them with an invalid or wrong key.
- **NEW – Identity (ECC) mode:** Send encrypted messages to a **PTPUB1** public key; only the matching **PTPRIV1** private key can decrypt them.
- **NEW – Built-in key management:** Generate, copy, or download public/private keys directly in the browser — private keys never leave the client.



## Comparison with Similar Services

| Feature                           | **PrivateTalk** | PrivNote | OneTimeSecret | SafeNote | Privmsg |
|------------------------------------|:--------------:|:--------:|:-------------:|:--------:|:-------:|
| **End-to-end encryption in browser** | ✅           | ❌       | ❌            | ✅       | ❌      |
| **Open-source code**               | ✅             | ❌       | ✅            | ❌       | ✅      |
| **No database (flat files only)**  | ✅             | ❌       | ❌            | ❌       | ❌      |
| **Public destruction/expired logs**| ✅             | ❌       | ❌            | ❌       | ❌      |
| **Safe Deletion**                  | ✅             | ❌       | ✅            | ✅       | ❌      |
| **Dashboard for private management** | ✅           | ❌       | ❌            | ❌       | ❌      |
| **No cookies, no tracking**        | ✅             | ❌       | ❌            | ✅       | ❌      |
| **Password/keyword protection**    | ✅             | ❌       | ✅            | ❌       | ✅      |
| **Time-lock (windowed access)**    | ✅             | ❌       | ❌            | ❌       | ❌      |
| **Message expiration (time-based)**| ✅             | ✅       | ✅            | ✅       | ✅      |
| **Identity mode (ECC)**            | ✅             | ❌       | ❌            | ❌       | ❌      |
| **No ads, no monetization**        | ✅             | ❌       | ❌            | ❌       | ✅      |
| **Minimalist, responsive UI**      | ✅             | ✅       | ❌            | ✅       | ✅      |
| **Account registration required**  | ❌             | ❌       | ❌            | ❌       | ✅      |
| **Email/SMS notifications**        | ❌             | ❌       | ✅            | ✅       | ✅      |

> ✅ — Feature available  
> ❌ — Not available  



## How It Works

PrivateTalk now has **two modes**:

1. **Classic mode:** One-time read messages with optional extra password and expiration.
2. **Identity (ECC) mode:** Public/private key messaging (PTPUB1/PTPRIV1) — recipient can read only with their private key.

**Basic workflow:**
1. **Write your message** (or paste recipient’s public key for Identity mode).
2. **Share the generated link** (or split the link and key for maximum privacy).
3. **The message can only be read once.** After it’s read or expired, it’s gone forever.



## Security

- All encryption happens in your browser, using strong algorithms (AES-GCM for classic mode, P-256 ECIES-like for Identity mode).
- The server never sees your plaintext message or your encryption keys.
- No cookies, no logs, no analytics — nothing to track you.
- Public destruction and expiration logs for independent verification.
- Strong HTTP security headers (CSP, Referrer-Policy, Permissions-Policy, etc.) for protection against XSS, clickjacking, and data leaks.



## Project Status

Stable, ready for use.  
Ongoing improvements and feature suggestions are welcome.



## Getting Started

1. Clone or download this repository.
2. Place the files on any PHP-enabled server (no database required).
3. Open `index.php` (classic mode) or `identity.php` (Identity mode) in your browser.



## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a complete version history.



## License

MIT License — See [LICENSE.txt](LICENSE.txt)



## Credits

Developed and maintained by [joker_josue](https://bitcointalk.org/index.php?action=profile;u=97582).  

Some productivity and code review tools, including AI assistants, were used to support the development process — all key logic was designed and audited by a human developer.

---

**Questions, suggestions, or feedback?**  
Open an issue or see [Bitcointalk Topic](https://bitcointalk.org/index.php?topic=5547913.msg65520925#msg65520925).

If you liked the project and want to support (or buy me a beer), I accept donations in BTC: bc1q9f2dhfdrzruyecfwea3n6nt2nuaj6htzgke5q2

