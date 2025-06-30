# PrivateTalk

PrivateTalk is a lightweight, open-source tool for sending encrypted, self-destructing messages. Focused on maximum privacy, transparency, and auditability, PrivateTalk never stores messages in plain text or requires any registration.


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
- **Prevents "premature" destruction:** Unlike most similar services, messages are NOT deleted if someone tries to access them with an invalid or wrong key.



## Comparison with Similar Services

| Feature                           | **PrivateTalk** | PrivNote | OneTimeSecret | SafeNote | Privmsg |
|------------------------------------|:--------------:|:--------:|:-------------:|:--------:|:-------:|
| **End-to-end encryption in browser** | ✅           | ❌       | ❌            | ✅       | ❌      |
| **Open-source code**               | ✅             | ❌       | ✅            | ❌       | ✅      |
| **No database (flat files only)**  | ✅             | ❌       | ❌            | ❌       | ❌      |
| **Public destruction/expired logs**| ✅             | ❌       | ❌            | ❌       | ❌      |
| **Safe Deletion**                  | ✅             | ❌       | ❌            | ❌       | ❌      |
| **Dashboard for private management** | ✅           | ❌       | ❌            | ❌       | ❌      |
| **No cookies, no tracking**        | ✅             | ❌       | ❌            | ✅       | ❌      |
| **Password/keyword protection**    | ✅             | ❌       | ✅            | ❌       | ✅      |
| **Time-lock (windowed access)**    | ✅             | ❌       | ❌            | ❌       | ❌      |
| **Message expiration (time-based)**| ✅             | ✅       | ✅            | ✅       | ✅      |
| **No ads, no monetization**        | ✅             | ❌       | ❌            | ❌       | ✅      |
| **Minimalist, responsive UI**      | ✅             | ✅       | ❌            | ✅       | ✅      |
| **Account registration required**  | ❌             | ❌       | ❌            | ✅       | ✅      |
| **Email/SMS notifications**        | ❌             | ❌       | ✅            | ✅       | ✅      |

> ✅ — Feature available  
> ❌ — Not available  

**Notes:**
- **PrivateTalk** is radically privacy-focused: there is no account system, and no emails, phone numbers, or notifications are ever collected or sent.
- **No registration** means no user data to leak, hack, or misuse—maximum anonymity for all users.
- **No notifications** (email/SMS) because the platform never stores any contact information; all control remains with the sender and recipient.
- Only PrivateTalk encrypts *everything* on the client side, never storing readable content on the server.
- Public logs allow anyone to independently verify when a message was destroyed or expired.
- **Safe Deletion:** Messages are only deleted when correctly decrypted. Wrong key attempts do not erase your message.

*This table is periodically reviewed; let us know if you spot an inaccuracy or want a service added!*


## How It Works

1. **Write your message.**
2. **Share the generated link** (or split the link and key for maximum privacy).
3. **The message can only be read once.** After it's read or the expiration time passes, the message is destroyed forever.


## Security

- All encryption happens in your browser, using strong algorithms (AES-GCM).
- The server never sees your plaintext message or your encryption keys.
- You may optionally add an extra password for enhanced security.
- No data is tracked or shared; there are no analytics, cookies, or third-party code.
- Self-destruction and expiration hashes are public for maximum auditability.
- Each message uses a unique, random PBKDF2 salt and a random IV (nonce), both generated in the browser. This prevents rainbow table attacks, even if the same passphrase is reused across different messages.
- The default encryption is AES-GCM 256-bit (can be changed to 128-bit if needed).
- All JavaScript and CSS are served from external files only, with strict Content Security Policy (CSP), X-Frame-Options, and X-Content-Type-Options headers enforced, ensuring robust browser-side protection against code injection and clickjacking.

PrivateTalk undergoes periodic security testing and code review. See [SECURITY-TESTS.md](SECURITY-TESTS.md) for a full report and historical results of manual and automated security testing.


## Project Status

Stable, ready for use.  
Ongoing improvements and feature suggestions are welcome.


## Getting Started

1. Clone or download this repository.
2. Place the files on any PHP-enabled server (no database required).
3. Open `index.php` in your browser and start using PrivateTalk.

_For advanced setup, customization, or local hosting, see the comments in each file._


## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a complete version history.


## License

This project is licensed under the MIT License. See [LICENSE.txt](LICENSE.txt) for details.


---


## Credits

Developed and maintained by [joker_josue](https://bitcointalk.org/index.php?action=profile;u=97582).

Some aspects of the codebase benefited from code review and productivity tools, including AI assistants. All key logic and features were designed, implemented, and audited by a human developer.

<sub>_Note: Some productivity and code review tools, including AI assistants, were used to support the development process._</sub>

---

**Questions, suggestions, or feedback?**  
Open an issue or see [Bitcointalk Topic](https://bitcointalk.org/index.php?topic=5547913.msg65520925#msg65520925).

If you liked the project and want to support (or buy me a beer), I accept donations in BTC: bc1q9f2dhfdrzruyecfwea3n6nt2nuaj6htzgke5q2

