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

Questions, suggestions, or feedback?  
Open an issue or see [Bitcointalk Topic].

