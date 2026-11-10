# Security and production deployment

The application uses session authentication, CSRF protection, server-side permissions, private media storage, and invitation-only registration. Posts and messages are not end-to-end encrypted. Restrict access to databases, storage credentials, backups, logs, and the application encryption key.

## Deploying the security update

1. Install locked PHP dependencies with `composer install --no-dev --optimize-autoloader` and Node dependencies with `npm ci`.
2. Run `php artisan migrate --force`. Existing unused invitations receive a seven-day grace period; new and refreshed invitations expire after seven days.
3. Run `npm run build`, refresh the application's cached configuration, and restart queue workers / PHP processes through your normal deployment procedure.
4. Use `APP_ENV=production`, `APP_DEBUG=false`, the correct HTTPS `APP_URL`, and `SESSION_SECURE_COOKIE=true`. Session encryption now defaults to on; set `SESSION_ENCRYPT=true` explicitly if an existing environment overrides it. Changing session encryption can require users to log in again.
5. Verify the mail transport. Changing an account email requires the current password, followed by a signed confirmation link sent to the new address. The original email continues working until confirmation; links expire after 30 minutes and are invalidated by a password or email change.
6. Visit Profile → Set up two-factor authentication. Enrol an authenticator app, confirm a six-digit code, and save the recovery codes privately. Strongly recommend this for every administrator. Enrollment is available to all accounts; it is not forced on existing users. Each recovery code works once. Managing MFA requires recent password confirmation (15 minutes by default).

Password changes revoke other database sessions, rotate remember tokens, and regenerate the current session. Session authentication also checks stored password hashes on each request, covering existing non-database sessions after they have established a password-hash baseline. Use a shared session/cache backend across application instances.

## Optional Cloudflare Turnstile

Turnstile is configured in `config/security.php` and is **off by default**. With it off, the login, registration, and setup pages do not load Cloudflare's widget script or call its verification API. No Cloudflare account or keys are needed to deploy with it off.

To enable it after creating a widget for your production hostname:

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=your-public-site-key
TURNSTILE_SECRET_KEY=your-secret-key
TURNSTILE_HOSTNAME=your-production-hostname
```

The hostname must match exactly, without a scheme, path, or port. It otherwise defaults to the hostname in `APP_URL`. Set an explicit hostname if your widget hostname differs from `APP_URL`. Refresh cached configuration after changes. The secret key is never sent to the browser.

The server verifies tokens with Cloudflare and checks the response hostname and action. Missing, invalid, expired, reused, or unavailable challenges fail closed while enabled. Tokens are claimed once in the shared cache to prevent concurrent reuse. The widget refreshes after form errors. Use a working shared cache and ensure the server can reach `https://challenges.cloudflare.com` before enabling. Set `TURNSTILE_ENABLED=false` and refresh configuration to disable it.

## Invitations and first setup

Invitations expire after seven days. Administrators can supply a recipient email when creating an invitation; that email is then required during registration. Emailing an unbound invitation binds it to the address it is sent to. Copy-only invitations without an email remain bearer links: give them only to the intended recipient. The CLI supports `php artisan users:create family-member --email=member@example.com`. Refreshing an invitation invalidates the previous token and renews its lifetime.

Initial production setup requires `SETUP_TOKEN`. Before exposing a fresh installation, generate a random value with `openssl rand -hex 32` and set it in the environment. Open `/setup?setup_token=YOUR_TOKEN` once; the session retains the setup authorization for form submission. Treat this URL as a secret and remove it from any retained logs. Setup becomes unavailable once users or a timeline exist; remove the token after setup. Existing installed servers do not need a setup token.

## Browser and upload protections

Production responses include a nonce-based script Content Security Policy compatible with Turnstile and secure WebSocket messaging. Framing and MIME sniffing are disabled. HSTS is sent only for HTTPS requests in production; `SECURITY_HSTS=false` disables it if your reverse proxy manages that policy. Configure only your known trusted proxy addresses in the hosting stack so HTTPS detection and rate-limit IPs are correct. Do not trust arbitrary forwarded headers.

Timeline photos are checked before decoding: maximum 20 megapixels, 8192 pixels per edge, and a memory-budget check. Photos are resized to at most 4096 pixels per edge, metadata is stripped, and animated images use their first frame to bound decoding work. Post creation is rate-limited. Existing file-count, file-type, file-size, video-processing timeouts, and private storage protections remain in place. Set reverse-proxy upload/body limits and isolate media workers with CPU, memory, and disk limits. Run supported, patched FFmpeg/GD/Imagick versions. File-type validation is not an antivirus scanner; downloaded documents may still contain malicious content.

Verify that cloud buckets and backups are private, HTTPS is enforced, the server document root is `public/`, and `.env`, `.git`, storage logs, and configuration files cannot be downloaded. Keep the application key and service-account credentials outside the public directory. These settings must be checked on the actual server.

## Checks

Run `composer ci:check`, `npm run build`, `composer audit --locked`, and `npm audit` before pushing. GitHub Actions runs PHP tests with coverage on PHP 8.4 and 8.5, frontend behaviour tests, formatting, linting, type checks, and dependency audits.

The npm override for `@typescript-eslint/typescript-estree` selects the patched minimatch 11 release while its upstream dependency still selects vulnerable minimatch 10. Remove the override once upstream includes a patched compatible dependency. Node 22 is used in CI.
