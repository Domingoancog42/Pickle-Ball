# Pikol Castle

## Guest booking

The Time column groups slots into Late night (before 6 AM), Morning (6 AM–noon), Afternoon (noon–6 PM), and Evening (6 PM onward). Below Booking information, **Add-ons** and **Food & drinks** show selectable products and quantities (0–10). Food & drinks has **Drinks** and **Junk Foods** buttons; only the chosen category is shown in a compact scrollable list, and quantities stay selected when switching.

**Admin → Add-ons & Food** manages names, categories, unit prices, replacement photos and availability, and can add further products. Default example prices are grip tape PHP 75, paddle PHP 150, clear edge tape PHP 100, water PHP 25, C2/Coca-Cola/Sprite/Royal/Pepsi PHP 40, Gatorade/Pocari Sweat PHP 60, and each snack PHP 25. The generic Soft drinks item is retired without deleting past orders. Saving an item clears its example-price marker. Product photo sources and the original illustration prompts are documented in `assets/images/addons/PROVENANCE.md`.

Selected products are priced again on the server under the booking transaction. Price changes require another review; negative/duplicate quantities and disabled items are rejected. Product names, quantities and unit prices are saved for each order and charged once per checkout, on its first court slot. The existing `court_fee` field remains the locked **reservation total including add-ons**, so payment caps, confirmations, refunds and revenue remain consistent; `addons_total` separates the product amount, and the court-only portion is `court_fee - addons_total`. Existing bookings default to zero add-ons. Both customer and staff reservation details show the item breakdown.

For an existing local database run `C:\xampp\php\php.exe tools/setup-booking-addons.php` then `C:\xampp\php\php.exe tools/setup-live-updates.php` (both applied here). Fresh setup includes the migration. For an existing hosted installation import `database/booking-addons-migration.sql`, `database/booking-addons-column-migration.sql`, and `database/booking-refreshments-migration.sql` before deploying the updated files (installations that already have add-ons only need the refreshments migration); a full newly built bundle already includes this schema. Image uploads are saved in `uploads/addons` and need filesystem backups.

**Book a slots** opens the dedicated `booking.html` page. It shows venue photos and current court rates beside live date/court availability. Slot selection, review and payment stay in the normal page flow. The payment step has provider buttons that switch the QR and account details. A payment hold can be resumed by refreshing the booking page in the same browser session.

Visitors can select court slots and continue directly to checkout without logging in. The review form asks for full name, email address and a Philippine mobile number (`09…` or `+639…`). Guest checkout uses the same server prices, atomic slot reservation, ten-minute payment deadline, private receipt uploads and staff verification as customer checkout. Staff confirmation emails go to the contact email entered for that checkout.

Guest access is restricted to checkouts created in the same PHP browser session. Entering an existing customer's email never signs the guest into that account or grants access to its bookings, payments or profile; the account details stay intact. Contact details are saved on the checkout so administrators see the guest's submitted name and phone. New email addresses receive a customer contact record with a random, undisclosed password, consistent with the existing walk-in records. Successful guest holds are limited to ten per IP address per fifteen minutes.

Existing local installations must run `C:\xampp\php\php.exe tools/setup-guest-checkout.php` before deploying these files. This has been applied to this workspace's database. It adds three nullable contact columns to `booking_checkout_holds`; existing records are preserved. Fresh setup includes this step. For an existing hosted database, import `database/guest-checkout-migration.sql` once through phpMyAdmin before uploading the updated files. A newly built InfinityFree bundle includes the updated schema in its SQL export. Refresh the website after updating to load the new scripts.

## Player inactivity warning

At the configured player inactivity limit, SweetAlert2 shows **Stay signed in** and **Log out**, with a 30-second countdown. Staying explicitly renews the session; cancelling or leaving the warning unanswered signs the player out. The server also expires the session after this 30-second grace period, even if JavaScript is unavailable. Background status/live requests do not renew sessions, and ordinary activity cannot silently dismiss an active warning. Admin accounts are exempt. The warning runs on the homepage and player workspace; embedded player panels forward activity to their parent to avoid duplicate prompts. Same-origin tabs coordinate Stay/Logout actions.

## Deploy to InfinityFree

See [the step-by-step InfinityFree guide](docs/INFINITYFREE.md). Run `powershell -ExecutionPolicy Bypass -File tools/build-infinityfree.ps1` locally to create a dated deployment folder and ZIP in Downloads. Upload the prepared htdocs contents, import the separate SQL file in phpMyAdmin, and fill in the three deployment configuration files. The hosting profile uses application change counters and ten-second polling instead of unsupported triggers, and direct database downloads instead of scheduled server backups. The local XAMPP configuration is preserved.

## SQL query safety

Runtime queries use native PDO prepared statements with typed parameter binding. Pagination values are bound, dynamic SQL structure is allowlisted, and multiple SQL statements are disabled. Run `C:\xampp\php\php.exe tools/check-sql-safety.php` for read-only regression checks. See [SQL safety review and verification](docs/SQL-SAFETY.md) for the scope and limitations.

## Direct database downloads

Settings → Backup Data → **Download database backup** immediately prepares and downloads an SQL file to the browser. No backup-path field, persistent server backup folder, shell functions or `mysqldump` is required. PHP/PDO exports table schemas/data, views and accessible triggers using an InnoDB snapshot, and records the export in Audit Logs. Temporary PHP storage may be used while generating the file. Uploaded images and stored routines/events require separate backups. Hosting execution-time, temporary-storage and database-permission limits still apply.

Existing server backups remain in Backup History. Scheduled backups require server support and do not download to a closed browser. See [backup and restore instructions](docs/BACKUP-RESTORE.md).

## Individual account lockouts

Admin Settings → Security ends with **Locked Accounts**, showing account name/email, role, failed logins, lock expiry in Asia/Manila, and an Unlock action. The list includes only current lockouts, refreshes automatically, and supports manual Refresh without replacing the security policy form. Administrators can unlock one account without changing any other account's lockout or the saved policy.

**Lockout After Failed Attempts** and **Lockout Duration (minutes)** apply separately to each account across browsers and IP addresses. Other accounts can still sign in from the same IP. A correct password does not bypass an active lock; expiry allows another attempt, and successful sign-in clears that account's failure counter. Existing active lock records remain effective. Signup retains its separate abuse limit.

Fresh setup includes the account lookup index and live tracking. Existing installations run `C:\xampp\php\php.exe tools/setup-account-lockout.php` and `C:\xampp\php\php.exe tools/setup-live-updates.php`; both have already been applied here.

## Admin reservation customer picker

New reservation uses a searchable active-customer dropdown with email/phone details, keyboard selection and a Load more control. Selecting a player fills their contact details and submits their existing user ID. **Custom / Walk-in** enables manual name, email and phone entry; switching modes preserves the walk-in draft. Email remains required for creating a customer record, and an existing customer email links to that account. In Custom / Walk-in mode, selecting Cash records the full court fee as Paid and sets the booking to Confirmed when created. The payment records the administrator and verification time, with an audit trail. Bank/wallet payments remain Unpaid until separately verified.

## Reports & Analytics

The **Select Report Type** dropdown switches between Booking Reservation, Revenue Sales, Customer/Player, Payment Transactions, and Cancellation & No-show reports. Each selection has its own summary metrics, chart, requested detail columns, and matching CSV export. **Export PDF** downloads a formal A4 landscape report with the venue logo, report title, date/court filters, preparation time in Asia/Manila, record count, colored repeating table headers and page numbers. PDF columns, row order and displayed values match the report table; table searches apply across all matching rows, including later table pages. Empty reports still export their column headings. PDF generation runs in the browser using locally bundled jsPDF, AutoTable and Noto Sans fonts, loaded only when exporting; no additional PHP extension or runtime CDN is needed. Date and court filters apply to the selected report; the table supports searching and pagination. Live updates include player and operating-schedule changes.

Run `node tools/check-report-pdf.cjs` for PDF generation, matching columns and values, multipage/long-row handling, repeated header colors, table search, empty reports, font/asset loading failures and retry checks. This check does not connect to the database or export customer data.

Reservations and cancellations use scheduled booking dates. Revenue includes Paid and Refunded transactions by original payment date, with full recorded refunds deducted from gross; it is not a refund-date cash-flow report. Payment Transactions includes all payment statuses by recorded payment/submission date. Player reports include accounts registered on or before the end date, with booking totals within the selected period. Utilization uses Confirmed/Completed bookings and current schedules, subtracts overlapping maintenance once, and identifies tied peak clock-hour windows by booked minutes. Cancellation dates come from recorded activity; missing dates are shown as not recorded, and No-show entries are identified in Reason.

## Player ratings

Players can choose **Rate Pikol Castle** in their profile dropdown on the homepage or player workspace. Each customer can save one 1–5 star rating, add an optional comment, and update it later. The homepage shows the actual average, total ratings and four submissions at a time, with circular previous/next buttons for additional reviews and swipe navigation on touchscreens; names are limited to first name and last initial. Ratings update through long polling. No sample reviews are seeded.

Fresh setup includes the ratings table. For an existing installation run `C:\xampp\php\php.exe tools/setup-ratings.php` followed by `C:\xampp\php\php.exe tools/setup-live-updates.php`. These migrations have already been run on this installation.

## Payment account settings

Admin Settings → Payments manages banks and wallets in `payment_methods`. Choose Add payment method, enter a provider name (such as MariBank), account holder and account number, upload its QR image, and save. JPEG, PNG and WebP images up to 3 MB are accepted only when a readable QR code is detected. The selected image is checked immediately, and the server checks it again on save; ordinary photos, corrupt images and unreadable QR codes are rejected. Each method can be edited, have its QR replaced, or be disabled to hide it from new checkouts without deleting existing payment records. Disabled drafts can be saved before their account details and QR are complete. Remove beside Save payment method removes a provider from Settings and new reservations/checkouts while preserving historical payments and its QR asset. Adding the same provider name again restores its existing method ID.

QR validation uses the PHP GD extension and `khanamiryan/qrcode-detector-decoder` from Composer. Enable GD in PHP and restart Apache after changing `php.ini`; run `composer install` when installing dependencies. Run `C:\xampp\php\php.exe tools/check-payment-qr.php` for QR format, rotation, transparency, corrupted-image and non-QR regression checks. Validation only reads the QR data to confirm it is decodable; it does not open encoded links or verify the payment recipient. Existing saved QR images are preserved when no replacement is uploaded or when a replacement fails validation.

Customer checkout, payment cards, Copy buttons and receipt submissions use the enabled methods from the database. Account numbers retain leading zeroes. Uploaded QR images display in full; the original bundled GCash/BPI artwork keeps its existing crop. Receipt uploads always require staff verification. On an existing installation run `C:\xampp\php\php.exe tools/setup-payment-methods.php` once; fresh setup includes it. This additive migration preserves existing method IDs, saved recipients, enabled state and payment history. Uploaded QR images are filesystem assets in `uploads/payment-qr` and must be included in filesystem backups.

## Live updates

Public court availability, admin records, customer reservations/payments, calendars, notifications, audit logs and backup history update through `api/live.php` without a browser refresh. Existing installations must run `C:\xampp\php\php.exe tools/setup-live-updates.php` once; fresh database setup includes it. The additive migration creates an InnoDB change-counter table and triggers, so the installation account needs CREATE TRIGGER permission. Changes become visible only after their transaction commits.

Each visible page keeps one bounded long-poll request open, releases the PHP session lock while waiting, and reconnects with backoff after network failures. Hidden/offline pages pause polling. The inactivity timeout applies only to customer/player accounts; administrators are exempt. Background reads do not extend a player's inactivity timeout. Keep Apache configured for concurrent requests; a single-worker PHP development server cannot serve a waiting poll and another request simultaneously.

Updates preserve server filters and scroll position, refresh calendars without changing their date/view, and defer page updates while a dialog, menu or field is active. Profile, security and checkout forms are not automatically replaced. Backup history refreshes separately from its schedule form. Slot-picker availability is updated in place; selected times that become unavailable are removed with a message. Refresh an already-open browser page once after installing this code to load the new scripts.

HTML, CSS, and vanilla JavaScript recreation of https://pikolcastle.space-z.ai.
The original images, fonts, responsive layouts, court details, and page content are stored locally. The frontend requires no framework or build step.

Start Apache in XAMPP and open `http://localhost/pikolcastle/`. The page loads its HTML sections from the local server.

The workspace's `.vscode/settings.json` also configures VS Code Live Server to proxy requests to `http://127.0.0.1/pikolcastle/`. Keep **Apache** and **MySQL** running in XAMPP, then stop and restart Live Server after changing its settings. This lets PHP execute through Apache while using the Live Server address. Without this proxy, Live Server serves PHP source as text and API calls fail with `Unexpected token '<' ... is not valid JSON`.

## Editing the page

Each section now has its own HTML file. Edit that file, save it, and refresh the main website. You do not need to copy the changes into `index.html`.

| File | What to edit |
| --- | --- |
| `html/header.html` | Logo, navigation links, and header buttons |
| `html/home.html` | Hero heading, image, buttons, and statistics |
| `html/courts.html` | Court card layout and default catalog used only to initialize an empty database |
| `html/booking.html` | Availability section and booking dialog fields/buttons |
| `html/rates.html` | Hourly rate card layout and booking buttons |
| `html/facilities.html` | Lounge and amenity cards |
| `html/testimonials.html` | Player reviews and ratings |
| `html/faq.html` | FAQ question buttons and answers |
| `html/find-booking.html` | Reservation lookup form |
| `html/footer.html` | Footer links, contact details, and opening hours |
| `html/login.html` | Login dialog |
| `html/signup.html` | Sign-up dialog |
| `html/forgot-password.html` | Password reset dialog |

`index.html` contains the shared head, stylesheet/script links, and section order. `js/includes.js` loads each section document's body, then `js/app.js` starts the controls. The sections are displayed together on the main page.

Keep the `<html>`, `<head>`, and `<body>` wrappers in the files under `html/`. VS Code Live Server needs the closing `</body>` to inject its reload script safely; otherwise it injects into inline SVG icons and truncates the section response. The loader imports only body content and removes injected scripts, while the main page retains normal live reload.

Court names, hourly rates, images, and surface types on the homepage come from MySQL through the public read-only `api/courts.php` endpoint. Edit these fields in Admin Courts. `js/court-catalog.js` fills court cards and the optional Rates section (when included) from the same records, refreshes every 30 seconds while the page is visible, and refreshes when returning to the homepage tab. Requests bypass the browser cache. If the catalog cannot load, those sections show a retry message instead of static prices. The markup in `html/courts.html` supplies card layouts and initial records for an empty database; its default prices are not used as runtime fallbacks. FAQ answers remain in `html/faq.html` inside their matching `faq-answer-*` panels.

The current catalog contains two courts, with their names and prices managed in Admin Courts. Retired courts are excluded from the public listings, booking options, admin catalog, court counts, and utilization table. Their linked reservations and payments remain available as historical records. `tools/remove-retired-courts.php` applies the requested catalog removal without deleting those records; `tools/setup-database.php` includes the supporting `courts.deleted_at` column for other installations.

Keep element IDs and `data-action`, `data-court`, `data-court-id`, `data-court-name`, `data-court-type`, and `data-court-rate` attributes when editing so the controls stay connected. The public availability grid reads MySQL and opens customer or guest checkout.

The screenshot-matched login and sign-up forms are in `html/login.html` and `html/signup.html`, with their shared design in `css/auth.css`. Open them using the website's **Log in** and **Sign Up** buttons. Login includes password visibility and a working **Remember me for 30 days** option; unchecking it keeps the session only in the current browser session.

Both forms keep the reference's full-size banner, logo, 44px controls, and single-column fields at every window height. If a window is shorter than the card, the backdrop scrolls so the complete form remains accessible; the card itself has no internal scroll area.

### Cloudflare Turnstile login protection

Customer and administrator login require a Cloudflare Turnstile security check. The dialog loads the official widget, enables Log In after verification, and refreshes the challenge after a failed login. Expired tokens and connection failures disable login and offer a retry. The widget uses a compact layout when the form is narrower than 300px.

`api/auth.php` validates `cf-turnstile-response` against Cloudflare Siteverify before checking credentials or creating a session, including through the compatibility URL `api/admin/auth.php`. Verification requires a successful result with the `login` action. Missing, invalid, expired, or reused tokens are rejected; configuration errors and Cloudflare outages also block login. Signup, password recovery, and logout keep their existing flows.

The supplied keys are configured in the private, Git-ignored `config/turnstile.local.php`. Only the public site key is returned to the browser. On another machine, copy `config/turnstile.example.php` to `config/turnstile.local.php` and supply the widget keys, or set `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` in the server environment. Environment values override the local file. Apache already denies direct access to `config/`. PHP cURL with a trusted CA bundle and outbound HTTPS to `challenges.cloudflare.com` are required.

In the Cloudflare Turnstile dashboard, allow the hostnames that serve the form, including `localhost` for XAMPP development and your production domain. Use a Managed widget for the visible verification shown in the reference. Open the site through Apache at `http://localhost/pikolcastle/`; Live Server cannot execute the PHP verification.

## Features

- Responsive navigation, court cards, rates, facilities, reviews, and FAQ accordion.
- Manila-time booking calendar, with a 60-day reservation window.
- Shared PHP signup/login with role-based dashboards, real availability, server-priced bookings, and reference codes.
- Own reservation history, policy-controlled cancellation, and admin-reviewed reschedule requests.
- Accessible modal dialogs, keyboard focus handling, and reduced-motion support.
- Anime.js hero entrances, staggered scroll reveals, card and button hover effects, smooth FAQ/mobile panels, and animated booking dialogs, calendar changes, and notifications.

## Player dashboard and shared records

Customer signup and login use MySQL and PHP sessions through the existing public login form. Signup asks only for name and email. PHPMailer sends a random temporary password, stored only as a hash, with a one-hour expiry. The first login creates a restricted 15-minute password-setup session, with no dashboard/API access. After choosing a different password of 12-72 bytes, the customer is signed out and must log in again with the new password. Resubmitting signup for a pending account replaces the temporary password and sends a new email, subject to rate limiting. Existing customer and admin passwords are preserved. Forgot-password recovery uses a three-step email/code/new-password dialog. Public booking buttons now open the player booking workflow; browser-local demo bookings are no longer created or read.

The public login is shared by customers and administrators. Database roles redirect admins to `admin/` and customers to `customer/`. Every protected endpoint checks role and record ownership. Both dashboards use the same `bookings`, courts, users, and payments tables.

The player workspace includes actual booking counts, upcoming matches, enabled courts, an availability-aware date/time picker, expiring server price quotes, own reservation history and calendar, payments, private proof uploads, notifications, and profile/avatar/password editing. Empty accounts show empty states. No sample bookings, revenue, wins, or rankings are generated.

New reservations start **Pending / Unpaid**. Staff confirm reservations and verify payments separately. Cancellation uses the configured notice cutoff and releases the slot; refunds require separate staff review. Reschedule requests retain the original court, duration, price, and held time until approved in Admin Reservations. Approval rechecks conflicts under the shared court lock. A requested target time is not held before approval.

Enabled banks and wallets are configured in Admin Settings ? Payments, including custom providers and uploaded QR images. Existing Cash records remain supported for staff handling. Customers can open **Pay / upload proof** directly from My Reservations, choose their payment channel (including switching from Cash before verification), scan or save the QR, and upload the successful transaction screenshot. Administrators see a private proof thumbnail and a Review payment button inside the reservation details. Uploaded proofs stay **Pending Verification** until staff verify funds. Private proofs are accessible only to the booking owner and authorized administrators. Player notifications contain only reminders for their own confirmed bookings, generated once when a booking enters the next 24 hours. Operational reservation, payment, and reschedule alerts remain in the admin feed. Player lists, pagination, unread counts, and mark-as-read actions all use the same reminder filter; previously copied activity alerts are excluded. Reminders are delivered in the app.

Player page wrappers are in `customer/`, endpoints in `api/customer/`, vanilla JavaScript in `assets/js/customer/`, and the responsive theme in `assets/css/customer.css`. Local GSAP and ScrollTrigger provide motion with navigation cleanup and reduced-motion support. The public site retains Anime.js.

Admin and customer record actions use horizontal three-dot menus, including table rows, court cards, and reservation cards. `assets/js/action-menus.js` handles shared menus, keyboard navigation, outside-click dismissal, and positioning outside scrolling tables. `assets/js/modals.js` uses the locally installed SweetAlert2 library for confirmations, required cancellation reasons, logout, and form submission confirmation. Cancelling a confirmation preserves the form; changes are sent only after confirmation. SweetAlert2's pinned build and license are in `assets/vendor/`.

`assets/js/table-tools.js` provides rows-per-page selectors, numbered pages, First/Previous/Next/Last navigation, and record ranges. Main reservation, customer, and payment lists use database pagination with bounded page sizes and filtered totals; changing filters resets the page and preserves the selected size. Player reservation filters support reference/court search and booking date; player payments support reference/court search, method, and payment status. Supplementary tables (maintenance, reschedule requests, report utilization, and loaded detail/history tables) filter and paginate their loaded rows independently. The court catalog has search, status filtering, and card pagination. Pagination does not change report totals or CSV exports.

Player verification used an isolated MySQL database: role/CSRF restrictions, cross-account access denial, server quote pricing and stale-price rejection, overlapping bookings including simultaneous PHP processes, shared admin confirmation, cancellation cutoff/rebooking, approval-only rescheduling and target conflicts, private payment proofs, manual payment verification, notification ownership/reminder deduplication, and password changes. Browser checks covered all player pages, a complete booking submission, 320/390/768/1440px layouts, reduced motion, and the Apache homepage's live availability/login flow. The isolated database and its proof files were removed afterward.

## Admin dashboard — XAMPP setup

The admin dashboard is implemented in `admin/`, with authenticated PDO endpoints in `api/admin/`. It uses your existing normalized `users`, `courts`, `bookings`, and `payments` schema. The dashboard includes court management, weekly schedules and maintenance, manual reservations, a booking calendar, customers, verified manual payments, reports/CSV exports, settings, and per-admin notification read states.

1. Start **Apache** and **MySQL** in the XAMPP Control Panel. PHP pages must run through Apache; VS Code Live Server cannot execute PHP.
2. Open PowerShell in `C:\xampp\htdocs\pikolcastle` and run:

   ```powershell
   composer install
   & C:\xampp\php\php.exe tools/setup-database.php
   & C:\xampp\php\php.exe tools/create-admin.php
   & C:\xampp\php\php.exe tools/check-install.php
   ```

   The database setup has already been run in this workspace. It is safe to rerun: it preserves records, adds missing admin and customer fields/tables, and initializes only an empty court catalog from `html/courts.html`. No sample customers, bookings, or revenue are inserted. The requested admin account has been added to this workspace. To create additional admins, use the CLI tool; choose your own credentials when prompted. Public signup always creates customers, and role checks run on every protected endpoint.
3. Sign in through **http://localhost/pikolcastle/index.html#login**. Administrators are redirected to **http://localhost/pikolcastle/admin/**. The public website remains at **http://localhost/pikolcastle/**.

Database configuration and the shared PDO connection are combined in `api/connection-pdo.php`. APIs use `db()` through the shared bootstrap; CLI tools use the same connection helpers. The defaults target XAMPP's local `pickleball_booking` database with the local root account. For another database or hosting environment, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the server environment. Use a dedicated database account and HTTPS on hosting. Database credentials are never included in browser JavaScript. Upload directories `uploads/` and `storage/proofs/` need write permission; proof images are served only through an authenticated PHP endpoint.

The setup extends `database/pickleball_booking.sql` with `database/admin-migration.sql` and `database/customer-migration.sql` and adds missing columns through the setup script. It removes the legacy unique `(court_id, booking_date, start_time)` index so a cancelled reservation can be rebooked. Overlap protection instead uses InnoDB transactions and a shared court-row lock for booking, confirmation, maintenance, and schedule changes. The core booking table is named **`bookings`**, preserving your schema rather than introducing a duplicate `reservations` table.

All PHP, MySQL sessions, reporting dates, and calendar events use **Asia/Manila**. FullCalendar includes Luxon timezone support, so the calendar remains in Manila time even on devices in another timezone. Booking and payment states are separate. Completing a game does not mark it paid; manual verification does. A refund action records an externally completed refund and does not transfer funds.

`assets/css/admin.css` contains the admin theme; the shared compiled Tailwind stylesheet is reused. `assets/js/admin.js` handles navigation and API requests, `management.js` contains management forms/pages, and the other JavaScript files handle modals, charts, the overview, and calendar. Libraries, Inter font files, licenses, and pinned versions are stored under `assets/vendor/`; no runtime CDN or JavaScript backend is needed.

### Admin verification

Run `tools/check-install.php` for a read-only installation check. To check PHP syntax:

```powershell
Get-ChildItem admin,customer,api,config,tools -Filter *.php -Recurse | ForEach-Object {
    & C:\xampp\php\php.exe -l $_.FullName
}
```

Functional checks performed against XAMPP Apache, PHP, and real MySQL included unauthorized/CSRF rejection, operating hours and weekly closures, maintenance conflicts, overlapping reservations, two simultaneous booking processes contending for the same court, cancellation and rebooking, customer disabling safeguards, payment proof access, manual verification, refunds, revenue totals, filtered CSV exports, notifications, login throttling, and logout. Apache checks verified that database configuration, SQL files, private storage, Composer metadata, and CLI setup tools reject direct HTTP access. Browser checks exercised actual reservation and court forms, separate start/end time columns, the pending KPI badge, all management pages, mobile navigation, calendar, contained table scrolling, and dialogs. Temporary QA accounts and records were removed afterwards.

For your own walkthrough, create a reservation for a future slot, try booking an overlapping slot, confirm the first reservation, and verify that its payment remains unpaid. Review the payment in Payments, record verified funds, and check the dashboard/report totals. Cancel the reservation with a reason and confirm that the time becomes available again. Use your selected report date range and court when checking CSV exports. Enable reduced motion in your operating system to verify the motion preference.

### Integrations still requiring configuration

- **Online payments:** no live gateway is configured. Bank and wallet QR payments use manual staff verification. Providers, account details, QR images and enabled state are managed in Admin Settings ? Payments. Existing Cash payments also require staff verification.
- **Email:** PHPMailer signup email is configured through the private SMTP settings. Gmail SMTP connection/authentication was verified. Forgot-password recovery also sends verification codes through PHPMailer.

## PHP mail library

PHPMailer 7.2.0 is installed with Composer in `vendor/phpmailer/phpmailer/`. The root `composer.json` declares the dependency and `composer.lock` records the installed version. This PHP vendor directory is separate from the animation library in `js/vendor/`.

Load it from a PHP file inside `api/` with:

```php
require dirname(__DIR__) . '/vendor/autoload.php';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
$mail->isSMTP();
```

Signup email uses `api/mail-service.php` with SMTP settings from environment variables or the ignored `config/mail.local.php`. The supplied Gmail account is configured locally; credentials are never sent to browser JavaScript. On another machine, copy `config/mail.example.php` to `config/mail.local.php` and supply your own credentials, or set `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM`, `SMTP_FROM_NAME`, `SMTP_ENCRYPTION` and `SMTP_AUTH`. Run `php tools/check-mail.php` to verify SMTP connection/authentication without sending email. Run `php tools/setup-database.php` to apply the additive signup fields. `change-password.php` is reachable only after a valid temporary-password login. Mail failures roll back account creation or temporary-password replacement; the API never returns the temporary password. To restore PHP dependencies on another machine, install Composer and run `composer install` from the project root. PHP's ZIP extension must be enabled for Composer to extract the package, or an `unzip`/7-Zip executable must be available.

Reservation confirmation emails use a branded HTML layout with an embedded Pikol Castle logo, booking details, verified payment total and an optional itemized add-ons table. The logo travels with the email rather than loading from a website URL. Plain-text emails retain the same details. Run `php tools/check-confirmation-email.php` to verify the message and embedded-image MIME without connecting to SMTP or sending a message.

`css/styles.css` contains the reference site's precompiled styles; `css/app.css` adds the vanilla interaction styles. Section HTML files hold the page content, and `js/app.js` implements interactions.

`css/motion.css` contains the transition utilities, and `js/motion.js` coordinates animation with Anime.js 4.5.0. The MIT-licensed library and its license are included in `js/vendor/`, so animations also work offline. Operating-system reduced-motion preferences disable movement, including when changed while the page is open. Without the animation library, the same controls and content remain usable.

## Password reset

The shared login dialog offers Forgot password. `html/forgot-password.html` and `js/password-reset.js` provide the Email / Verify code / New password stepper, resend countdown, live password checklist, and visibility toggles. `api/password-reset.php` handles request, verify, and reset using the same private SMTP configuration as signup. Codes are random six-digit values stored only as hashes, expire in 10 minutes, and allow at most five incorrect verification attempts. Resending has a 60-second cooldown and invalidates the previous request in that browser. Unknown email addresses receive the same generic response. The verified reset ticket is bound to the PHP session and cannot be used after completion. A successful reset invalidates all outstanding codes for the user and all prior authenticated sessions; the user must log in with the new password. The additive `tools/setup-password-reset.php` migration runs from the main setup script and preserves existing accounts.

Verification used an isolated database and a local SMTP capture server: complete mobile browser reset, wrong/expired code handling, resend throttling, verification before password change, code replay denial, old session invalidation, and login with the new password. Gmail SMTP authentication was checked separately without sending a test message.

## Account security OTP

The customer sidebar no longer includes Notifications; updates remain accessible through the header bell. Profile Account security requires the current password and a six-digit email OTP before changing a password. `api/customer/security-code.php` derives the recipient from the authenticated account and sends through the private PHPMailer SMTP configuration. Codes expire in 10 minutes, allow five incorrect attempts, have a 60-second resend cooldown, and are limited to five requests per 15 minutes. Codes are stored as hashes and tied to both the requesting session and current password version. A successful change consumes outstanding codes, invalidates other sessions, and rotates the current session/CSRF token. Ordinary profile/contact updates do not require an OTP.

## Security, audit and backup settings

Audit Logs keeps the connection IP recorded with each event and shows its approximate city, region and country using `https://ipapi.co/{IP_ADDRESS}/json/`. The Location column and audit detail dialog fill in the background; authentication never waits for geolocation. Successful lookups are cached for seven days, failed or rate-limited lookups for 15 minutes. Local/private/reserved IPs are labeled without sending a lookup. Provider errors leave audit records readable. Only administrators can resolve an IP, and only for an existing audit event. The Result table column is removed; result values remain in the stored audit data, filter and detail dialog. Run `php tools/setup-audit-locations.php` on an existing installation; the main setup includes it. On localhost, `127.0.0.1` / `::1` identify the local connection and have no public location. The app records `REMOTE_ADDR`; a reverse proxy must be configured on the server to supply a trusted visitor address, rather than accepting arbitrary forwarded headers.

Admin Settings contains General, Security, Audit Logs, and Backup Data. Security saves password length, expiry (0 disables), inactivity timeout and login-lockout policies in `system_settings`; server authentication enforces these values on following requests. Existing password hashes remain supported. Backup Data saves automatic daily/weekly/monthly schedules with a Manila date/time anchor and shows automation health, last automatic run, manual/automatic history, details and checksum-verified downloads. Run `php tools/setup-backups.php` on an existing installation; fresh setup includes it. Backups stay outside the web root in private database-scoped storage. The path is displayed read-only. Uploaded images and payment screenshots need separate filesystem backups. Browser-based restoration is not enabled. Existing private backups and retirement archives remain preserved.

`config/security.php` supplies defaults for unsaved security settings. Notification read states remain per account in `notifications.read_by`. On Windows, `tools/install-backup-task.ps1` installs a five-minute task using `php-win.exe`; by default it runs while the Windows user is signed in. An administrator can install with `-Unattended` for S4U signed-out execution. The server and MySQL must remain running. Schedule `php tools/backup-runner.php` every five minutes on other servers. A recent runner heartbeat confirms execution; saving a schedule alone does not confirm a working scheduler. Missed occurrences produce one catch-up backup, then advance the next due time; monthly schedules preserve their original day, clamped to shorter months. Historical recovery instructions remain in `docs/BACKUP-RESTORE.md`.

## Homepage customer account menu

Public desktop/mobile navigation and the footer now link to **Locate Us** (`#locate-us`). `html/locate-us.html` replaces the former booking lookup section with the supplied Kibaghot address, a Google area map, the existing Bing Maps destination, telephone and email links. `css/location.css` contains its responsive layout. Reservation lookup remains available to signed-in customers through My reservations in their account menu.

Customer sign-in stays on the public homepage. The named profile dropdown opens My profile, My schedule, My reservations, Book a court and Billing in an inline account panel. A separate header notification bell shows the unread count and opens a five-item notification preview. View all notifications opens a floating full-list card with server-paginated Load more; both views support marking notifications as read and Mark all as read, without navigating away from the current page. Existing customer views are reused in authenticated embedded mode without their sidebar or topbar; the public header and homepage URL remain in place. While an account page is open, public homepage sections and the footer are hidden. Header navigation links and Back to homepage restore the public sections. The Back to homepage control closes the panel. Profile changes refresh the header name, and logout removes the panel without opening the login dialog. Direct customer workspace URLs and admin navigation remain available.

## Archived records

Customer account Actions use **Make inactive** / **Make active**, with matching Active / Inactive badges and filters. Inactive accounts cannot sign in or use existing authenticated sessions; making the account active allows a fresh login, while old sessions stay invalid. Sign-in failures display **Login Failed** and **Invalid email or password.** Existing reservations remain recorded for staff to manage. Customer archiving also makes the account inactive and Restore preserves its previous Active / Inactive state. Run `php tools/setup-customer-status.php` to migrate legacy disabled customer accounts; the main setup includes this migration.

Administrators can use **Archive** in the Actions menus for Courts, Reservations, Customers and Payments. Each module has its own **Archived** page with search, pagination and **Restore**. There is no Archived entry in the sidebar. The module’s Archived button opens only its records at `admin/archived.php?module=reservations` (or courts/customers/payments); refresh and browser history retain that module, and archive API reads require a module. Archiving keeps linked bookings, receipts, account history and financial totals. Courts disappear from booking choices; archived customers are signed out and cannot sign in. Restore returns the previous court/account status. Previously retired courts are included without making them available again.

Resolve upcoming reservations before archiving a court or customer. Reservations and payments can be archived after the reservation is Completed, Cancelled or No-show, with no pending payment verification or reschedule review. Restore a record before editing it. Archiving a reservation and its payment are separate actions. Reports retain archived transactions for accurate totals. Run `php tools/setup-archives.php` for an existing installation; the main database setup includes this additive migration.

## Customer booking length

The public Book a Court section shows a **Book a slots** button. It opens a floating date strip and time-by-court availability matrix (`js/slot-picker.js`, `css/slot-picker.css`) with whole-hour starts only. Customers can select up to ten non-overlapping one-hour slots on one court/date. Continue preserves the selection through login and opens Review with a payment-method selector. **Confirm selected times** atomically creates Pending / Unpaid reservations through `api/customer/checkout-hold.php` and starts a shared, server-stored 10-minute payment deadline. Those slots immediately become Reserved for other players. Payment shows the remaining time, QR/account details, booking summary, receipt upload, and venue reminder. Back, reload, retries, and resuming from My reservations preserve the original deadline and do not create duplicate reservations. Submitting a valid receipt through `api/customer/checkout-proof.php` before the deadline changes payment to Pending Verification and preserves the slots while staff review it; receipt submission does not create new bookings. Unpaid held slots are automatically cancelled and become available after the deadline, including when the browser is closed. Server requests expire due holds under the court lock, and availability/conflict queries also exclude expired unpaid holds. Late receipt submissions and staff attempts to revive expired unpaid slots are rejected. A receipt or verified payment received on time remains reserved; old/manual reservations without a checkout hold are not timed out. `api/customer/submit-checkout.php` is a compatibility URL for the existing-reservation receipt endpoint. Run `php tools/setup-payment-holds.php` on an existing installation; the main database setup includes this additive migration. Profile-menu Book a court opens this same floating picker.

Each new customer time-slot reservation is exactly 60 minutes, with starts on the hour or half-hour. Book a Court shows a fixed 1-hour duration instead of a duration picker. Multiple non-overlapping 1-hour selections remain supported, each with a separate reference. Customer quote and checkout endpoints reject shorter/longer bookings, including old non-hour quote tickets. Existing reservation durations are preserved when rescheduling. Keep the business booking interval at 30 minutes and its maximum duration at least 60 minutes.
