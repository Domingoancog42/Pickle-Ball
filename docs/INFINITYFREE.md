# Deploy Pikol Castle to InfinityFree

Uploading `index.html` alone will not run this application. Upload the complete contents of the prepared `htdocs` folder and import its separate database SQL file. The bundle contains your existing database and uploaded files; keep it private.

## 1. Create your website and database

In InfinityFree, create a hosting account/domain and enable HTTPS. In its control panel open **MySQL Databases** and create a database. Copy the exact MySQL hostname, database name, username and database password from that page. The database hostname is not `localhost`.

## 2. Edit three files on your computer

Inside the prepared `htdocs/config` folder:

1. Edit `database.local.php`: replace host, name, user and password with InfinityFree's values. Keep port 3306 unless the panel says otherwise.
2. Edit `mail.local.php`: set the SMTP username, app password and sender. With Gmail, use smtp.gmail.com, port 587 and TLS. This is needed for signup, password recovery and verification emails. Do not enter your regular Gmail password.
3. Edit `turnstile.local.php`: enter the real Cloudflare Turnstile site key and secret. In Cloudflare, add your new website hostname to the widget's allowed hostnames. Sign-in requires this security check.

Keep PHP quotes intact. If a password contains an apostrophe or backslash, escape it in the PHP string. Never share these credentials in chat or screenshots. Existing local secrets are deliberately not copied into the bundle; configure the deployment copies only.

`hosting.local.php` is already configured for InfinityFree; leave portable set to true. It enables PHP-based change tracking and ten-second background polling without database triggers. Changes made directly through phpMyAdmin do not update these application counters; reload the page after such changes.

## 3. Import your database

Open **phpMyAdmin** from the hosting control panel. Select the new database in the left sidebar, choose **Import**, select `IMPORT-DATABASE.sql` from the bundle and click **Go**. This file already includes the current schema and data. It excludes unsupported triggers/views and does not create or select a database. Import only into your intended new database; it replaces tables with the same names.

The SQL file stays on your computer. Do not upload it into htdocs.

## 4. Upload all website files

Use File Manager or FTP to open the domain's **htdocs** folder. Remove only the hosting provider's default placeholder index page if present. Upload the **contents** of the bundle's `htdocs` folder into that folder: `index.html`, `change-password.php`, `.htaccess`, and the admin, api, assets, config, css, customer, html, js, storage, uploads and vendor folders. Keep the same structure and include hidden `.htaccess` files. FTP is preferable for many files.

Do not upload the outer bundle folder, SQL file, START-HERE.md, source tools or ZIP itself as your website. If you edit configuration after creating the ZIP, upload from the edited folder; the original ZIP still contains placeholder settings.

## 5. Open and check the website

Open `https://YOUR-DOMAIN/`. Your existing admin email and password remain the same as the local database. Check sign-in, a customer profile photo, court availability, payment QR images and the admin Settings pages. Test a signup/recovery email to an address you control, then download a database backup. The browser should load API calls under your own domain without localhost URLs.

Free hosting uses direct SQL downloads rather than scheduled server backups. Keep separate copies of uploaded photos and receipts. PHP limits still apply to large exports/uploads. Existing server-backup history is retained in the database but not shown in portable mode because those server files were not transferred.

If the site cannot connect to MySQL, recheck all four database values and the imported tables. If sign-in's security check fails, verify both Turnstile keys and the allowed hostname. If signup/recovery email fails, verify SMTP credentials and port 587/TLS. Avoid repeatedly requesting signup emails during troubleshooting.

## Rebuild after code or database changes

From the project directory in PowerShell run:

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-infinityfree.ps1
```

The builder writes a dated folder and ZIP to Downloads, preserves the existing local application configuration, includes current uploaded files and exports the current database without changing its records. Re-enter deployment credentials in the new bundle. Do not reimport a local database over a live site with newer bookings; use a deliberate migration instead.

Hosting references: [uploading PHP/MySQL websites](https://forum.infinityfree.com/t/how-to-add-mysql-to-website-and-where-to-put-php-file/70677/2), [trigger restriction](https://forum.infinityfree.com/t/creating-triggers/91719/3), [Gmail SMTP](https://forum.infinityfree.com/t/how-to-send-email-with-gmail-smtp/49239/).
