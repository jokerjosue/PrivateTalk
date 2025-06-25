# CHANGELOG

## v0.1 – 2025-06-10
**First functional version:**  
- Local encryption, single-use link, message destroyed after reading  
- CSS separated to `style.css`  
- Added input validation and `.htaccess` file for data folder security  
- Cleanup of `index.php`  
- Added “How it works?” section for user transparency

## v0.2 – 2025-06-10
**Usability improvements:**  
- Added innovative dual sharing option: full link or split (ID + key)  
- Clear explanation on how to use each option; more visually separated and educational

## v0.3 – 2025-06-10
**Public logs and statistics:**  
- Automatic logging of created and destroyed messages (SHA-256 hash and timestamp)  
- Global stats visible in the frontend  
- Complete PrivateTalk branding integration  
- Fix: AJAX/POST handling now always before HTML output (solves 'Unexpected token <' error)  
- Full compatibility with public logs and two-column layout

## v0.4 – 2025-06-13
**Expiration and extra protection:**  
- Optional message expiration (1h, 4h, 8h, 24h, 3d, 7d)  
- Auto-cleanup of expired messages on page load  
- Stats and logs for expired messages added  
- Extra keyword option (double protection) and custom expiration (number + unit)  
- Advanced options visually separated as cards; cleaner layout and improved UX

## v0.5 – 2025-06-16
**Time-lock & Dashboard:**  
- New: Scheduled messages (“Time-lock”) — can only be read within a specific time window  
- Private dashboard: lets users monitor the status of their messages using a local file with hashes/tags  
- Improvements: time-lock auto-destroys message after window ends; dashboard shows abbreviated hash (toggle view more), includes “destroy now” button for active messages

## v0.6 – 2025-06-17
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

## v0.7 – 2025-06-19
**Final tweaks before translation:**  
- Ensured correct logging when destroying message via dashboard.php (always logs hash in `expired.log`, no duplicates, stats always accurate)  
- Manual expiration and time-lock fields now displayed side by side for usability  
- Layout adjustments to improve page organization and navigation  
- Fixed minor bugs from previous layout changes

## v1.0.0 – 2025-06-23
**First public release (English):**  
- Script translated to English  
- Prepared for open-source launch  
- Final touches for GitHub release

