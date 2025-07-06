# CHANGELOG

## v0.1.0 – 10-06-2025
**First functional version:**  
- Local encryption, single-use link, message destroyed after reading  
- CSS separated to `style.css`  
- Added input validation and `.htaccess` file for data folder security  
- Cleanup of `index.php`  
- Added “How it works?” section for user transparency

## v0.2.0 – 10-06-2025
**Usability improvements:**  
- Added innovative dual sharing option: full link or split (ID + key)  
- Clear explanation on how to use each option; more visually separated and educational

## v0.3.0 – 10-06-2025
**Public logs and statistics:**  
- Automatic logging of created and destroyed messages (SHA-256 hash and timestamp)  
- Global stats visible in the frontend  
- Complete PrivateTalk branding integration  
- Fix: AJAX/POST handling now always before HTML output (solves 'Unexpected token <' error)  
- Full compatibility with public logs and two-column layout

## v0.4.0 – 13-06-2025
**Expiration and extra protection:**  
- Optional message expiration (1h, 4h, 8h, 24h, 3d, 7d)  
- Auto-cleanup of expired messages on page load  
- Stats and logs for expired messages added  
- Extra keyword option (double protection) and custom expiration (number + unit)  
- Advanced options visually separated as cards; cleaner layout and improved UX

## v0.5.0 – 16-06-2025
**Time-lock & Dashboard:**  
- New: Scheduled messages (“Time-lock”) — can only be read within a specific time window  
- Private dashboard: lets users monitor the status of their messages using a local file with hashes/tags  
- Improvements: time-lock auto-destroys message after window ends; dashboard shows abbreviated hash (toggle view more), includes “destroy now” button for active messages

## v0.6.0 – 17-06-2025
**Dashboard improvements:**  
- Shows expiration date if applicable  
- Link field with copy button  
- User file can contain just the ID or full link (optional tag); dashboard extracts ID automatically  
- “Destroy now” updates the row immediately to “Expired” state in the dashboard, no refresh needed  
- Hash column shows SHA256 of ID (abbreviated, with button to copy full value)  
- Fix: Immediate destruction via dashboard deletes file and logs as expired  
- Stats updated when destroying via dashboard  
- Dashboard only shows stats for loaded file; F5/refresh forces new upload  
- Visual tweaks: export to personal log, dashboard access button, accordions for technical details/FAQ, other UI improvements

## v0.7.0 – 19-06-2025
**Final tweaks before translation:**  
- Ensured correct logging when destroying message via dashboard.php (always logs hash in `expired.log`, no duplicates, stats always accurate)  
- Manual expiration and time-lock fields now displayed side by side for usability  
- Layout adjustments to improve page organization and navigation  
- Fixed minor bugs from previous layout changes

## v1.0.0 – 23-06-2025
**First public release (English):**  
- Script translated to English  
- Prepared for open-source launch  
- Final touches for GitHub release

## v1.0.1 - 24-06-2025
- Added comparison table to README, highlighting main privacy differences vs. similar services.
- Menor README improvements.
- Bitcointalk topic created as a user support page.

## v1.1.0 - 01-07-2025
- Added homepage link to the dashboard title.
- **Fixed critical bug:** Message is now destroyed **only after a valid read** (correct key), preventing deletion when accessed with an invalid or wrong key.
- Improved server-side verification: now uses a hash of (ID + key), ensuring message destruction only if decryption is possible.
- Minor security and file-handling improvements for message storage and expiration.
- README.md improvements.

**Security:**  
- Replaced the fixed salt in passphrase-based encryption with a unique random salt per message. This significantly increases protection against rainbow table and brute-force attacks. Old messages remain compatible.
- Added extra security headers (CSP, X-Frame-Options, X-Content-Type-Options) to strengthen browser-side protection against clickjacking and code injection.
- Moved all inline JavaScript and CSS to external files (`main.js`, `style.css`) for full CSP compliance and stronger XSS mitigation.
- Added `SECURITY-TESTS.md` with reports of all security testing performed.

## v1.2.0 – 06-07-2025
**Major improvements:**
- Improved extra passphrase UX and security: users now have up to 5 attempts to enter the correct passphrase without needing to refresh or reopen the link. The encrypted message is kept in memory until a valid decryption or max attempts.
- The passphrase input now disables browser autocomplete and saving suggestions, further enhancing privacy and security.
- The main informational text at the top of the page is now dynamic, giving users clear guidance through every step (writing, sharing, reading, error).

**Fixes & usability:**
- "New message" button now fully resets all form fields and advanced options, preventing residual data from carrying over to new messages.

**Security & privacy:**
- Added strict HTTP security headers (Content-Security-Policy, Referrer-Policy, Permissions-Policy, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection) to `index.php` and `dashboard.php`, mitigating XSS, clickjacking, referer leakage, and restricting unnecessary browser features.
- Referrer information to external domains is now disabled by default, preventing metadata leaks.
- Clipboard access is now restricted to where strictly needed, further tightening browser permissions.
- New security audit and result in `SECURITY-TESTS.md`.
