# SQL injection review

The application's shared database layer uses native PDO prepared statements. The review did not identify a confirmed SQL injection in the inspected runtime request paths. This is a scoped source review and regression check, not a guarantee against every possible vulnerability or future change.

## Applied protections

- `api/connection-pdo.php` disables emulated prepares and multiple SQL statements per call.
- The shared `query()` helper binds values explicitly as integers, booleans, NULL or strings. Collections and objects are rejected as parameter values.
- Admin/customer list pagination and public rating pagination bind LIMIT/OFFSET as integers instead of interpolating them.
- Search text, record IDs, dates and update values use placeholders. Search/status fields reject malformed array input where hardened in this change.
- Sort direction, report type and archive module remain allowlisted. Archive SQL record-column fragments now have an explicit per-module allowlist too.
- Dynamic IN clauses generate placeholder counts and bind each validated ID. SQL export identifiers come from database metadata and are backtick-escaped. Neither uses a raw request value as an SQL fragment.

SQL identifiers and keywords cannot be bound as data parameters. Keep those fragments developer-owned or allowlisted; use placeholders for every request-derived value. Do not build queries by concatenating escaped request text, and do not use keyword blocklists as an injection defense.

## Verification

Run `C:\xampp\php\php.exe tools/check-sql-safety.php` for read-only checks of native prepares, literal injection payloads, typed/named parameters, integer pagination, rejection of stacked statements and archive-column allowlists.

Additional tests used a disposable database: injection search payloads across eight admin/customer list endpoints; tampered sort/module/report/ID/date fields; malformed search arrays; a profile write containing apostrophes and SQL-like text; literal search of that saved value; all five available reports; dashboard and rating pagination; and authentication payload rejection. User, booking and payment counts remained unchanged. The isolated authentication test excluded CAPTCHA verification and exercised the actual password/SQL path. No production test records were created.

All 67 runtime API PHP files passed syntax checks. A generated SQL export was restored into another disposable database and compared across all tables, views and triggers after the shared connection changes.

Use a dedicated database account with the permissions the deployed application needs. Keep administrative database credentials out of public files and version control. Parameter binding addresses SQL injection; authentication, authorization, upload handling and server configuration still need their own protections.
