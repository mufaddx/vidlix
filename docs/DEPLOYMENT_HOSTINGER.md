# Hostinger deployment

Target: PHP 8.4+, MySQL, SSL, document root `public/`.

## 1. Server and code

1. Create the hosting plan or VPS with PHP 8.4+ and MySQL. The lock file
   resolves Symfony 8.1, which requires PHP >= 8.4.1 — 8.3 will fail at
   `composer install`. On Hostinger this is hPanel -> Advanced -> PHP
   Configuration, and it must be changed for the web side too, not just CLI.
2. Point the domain and set the document root to **`public/`**. Nothing above
   `public/` may be web-reachable.
3. Upload the app, then `composer install --no-dev --optimize-autoloader`.
4. `chmod`/`chown` so `storage/` and `bootstrap/cache/` are writable by the web
   user.

## 2. Environment

Copy `.env.example` to `.env` and fill it in. Never commit `.env`.

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
APP_KEY=            # php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Provider credentials are documented in `docs/INTEGRATIONS.md` and listed with
comments in `.env.example`. Every provider stays off until its keys are present,
so the site is safe to launch with some of them still blank — the UI will say
what is unavailable rather than pretending.

Then:

```
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

On Hostinger, `symlink` is in `disable_functions`, so `storage:link` fails.
Create the link from the shell instead — the shell is not subject to PHP's
`disable_functions`:

```
ln -s ~/vidlix/storage/app/public ~/vidlix/public/storage
```

## 3. Admin account

The seeder reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_MOBILE`. Set a real
password in `.env` **before** seeding, and seed roles and CMS content only:

```
php artisan db:seed --force
```

Then log in and change the password again from the UI. `ChangeMe_Admin1` must
never exist on a production host.

## 4. Queue and scheduler

Outbound email, Instagram syncs and push all run on the queue, so something must
drain it. Without a worker, replies sit in `queued` forever — the message is
stored and the UI says so honestly, but it never actually leaves.

### VPS (preferred)

A long-running worker under Supervisor, plus one cron line for the scheduler:

```
# /etc/supervisor/conf.d/vidlix-worker.conf
[program:vidlix-worker]
command=php /home/USER/vidlix/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=USER
numprocs=1
redirect_stderr=true
stdout_logfile=/home/USER/vidlix/storage/logs/worker.log
stopwaitsecs=3600

# Cron
* * * * * cd /home/USER/vidlix && php artisan schedule:run >> /dev/null 2>&1
```

### Shared hosting (no Supervisor)

Hostinger shared plans have no process manager, so run a short-lived worker from
cron instead. It drains whatever is waiting and exits before the next minute:

```
* * * * * cd /home/USER/vidlix && php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
* * * * * cd /home/USER/vidlix && php artisan schedule:run >> /dev/null 2>&1
```

`--max-time=55` keeps a slow job from overlapping the next tick, and
`--stop-when-empty` means an idle site costs nothing. The tradeoff is latency: a
reply can wait up to a minute before it is handed to the provider. That is
acceptable for email and Instagram syncs; it is not acceptable for anything
interactive, so do not move settlement or chat onto the queue.

Do **not** set `QUEUE_CONNECTION=sync` to avoid the problem. That runs the
provider call inside the web request, so a slow or failing provider becomes a
slow or failing page for the user.

### PHP version on shared hosting

The vendor tree needs PHP 8.4.1+. Hostinger's panel does not always expose a PHP
version selector per site, and `crontab`/CloudLinux selector CLIs are not
available to the account either. When the selector is missing, `public/.htaccess`
already pins the interpreter:

```
<IfModule mime_module>
    AddHandler application/x-httpd-alt-php84___lsphp .php
</IfModule>
```

Symptom when this is wrong: a bare 500 with **nothing in `storage/logs`**. That
is the giveaway — the failure is Composer's platform check firing before Laravel
boots, so Laravel never gets far enough to log anything. Confirm the version the
web actually runs before hunting anywhere else:

```
php -r 'echo PHP_VERSION;'          # CLI, may differ from web
curl -s https://your-domain/ -o /dev/null -w '%{http_code}
'
```

CLI and web can run different versions. Artisan working while the site 500s is
consistent with exactly this.

### Document root on shared hosting

Laravel must be served from `public/`, but Hostinger shared plans serve
`public_html`. In order of preference:

1. **Change the document root in hPanel** to `.../vidlix/public`. Available on
   Business and higher — use this if you have it.
2. **Put the app beside `public_html`** (e.g. `/home/USER/vidlix`) and replace
   `public_html` with a symlink to `/home/USER/vidlix/public`.
3. **Last resort:** copy the contents of `public/` into `public_html` and edit
   its `index.php` paths to point one level up. This works, but it splits the
   app across two directories and every deploy has to remember it — avoid unless
   the first two are impossible.

Never solve this by moving the app itself into `public_html`. That puts `.env`,
`storage/` and `vendor/` on the public web.

## 5. Webhook URLs

Register these with each provider once SSL is live. Each must be reachable over
HTTPS and must not be behind basic auth (except the Postmark `basic` scheme,
which uses it deliberately).

```
https://your-domain/webhooks/payment
https://your-domain/webhooks/payout
https://your-domain/webhooks/email/inbound
https://your-domain/webhooks/email/events
https://your-domain/webhooks/meta        (GET verify + POST events)
```

Meta's redirect URI must match `META_REDIRECT_URI` exactly:

```
https://your-domain/integrations/instagram/callback
```

After registering each one, send a test event and confirm a row appears in
`webhook_logs` with `signature_status = valid`. A `rejected` row means the
secret does not match; fix that before going live, because an unverified webhook
is processed as nothing at all.

## 6. Email domain

Before sending anything real, publish SPF, DKIM and DMARC for the sending
domain, and set `EMAIL_INBOUND_DOMAIN` with an MX record pointing at the inbound
parse service. Reply routing depends on `reply+<token>@` addresses surviving the
round trip; if the provider strips plus-addressing, inbound mail will correctly
land as `unmatched` rather than in the wrong inbox.

## 7. Object storage

Set `FILESYSTEM_DISK=s3` (or `MEDIA_DISK=s3`) plus the `AWS_*` values for S3,
Cloudflare R2 or DigitalOcean Spaces. Do not serve project media from the app
server, and do not put video in MySQL — the database keeps keys and metadata
only.

## 8. Legal content

`/p/terms`, `/p/privacy`, `/p/cookie`, `/p/refund` and the other legal pages are
CMS rows edited from the admin area. They ship with placeholder bodies. Replace
them with text your lawyer approved before taking real money; the placeholders
are not a policy.

## 9. Before go-live

- [ ] `APP_DEBUG=false` and the site returns no stack traces
- [ ] Admin password changed from the seeded value
- [ ] SSL enforced, HTTP redirects to HTTPS
- [ ] Queue worker running and restarting on boot
- [ ] Cron running `schedule:run`
- [ ] Every registered webhook produced a `valid` row in `webhook_logs`
- [ ] SPF, DKIM, DMARC published
- [ ] Object storage bucket private, signed URLs working
- [ ] Legal pages replaced with approved text
- [ ] Database backups scheduled **and a restore tested**
