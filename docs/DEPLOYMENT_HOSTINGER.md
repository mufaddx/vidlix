# Hostinger deployment

Target: PHP 8.3+, MySQL, SSL, document root `public/`.

## 1. Server and code

1. Create the hosting plan or VPS with PHP 8.3+ and MySQL.
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

## 3. Admin account

The seeder reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_MOBILE`. Set a real
password in `.env` **before** seeding, and seed roles and CMS content only:

```
php artisan db:seed --force
```

Then log in and change the password again from the UI. `ChangeMe_Admin1` must
never exist on a production host.

## 4. Queue and scheduler

Outbound email, Instagram syncs and push all run on the queue, so a worker is
required — without one, replies sit in `queued` forever.

```
# Supervisor (or the Hostinger equivalent)
php /home/USER/vidlix/artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Cron
* * * * * cd /home/USER/vidlix && php artisan schedule:run >> /dev/null 2>&1
```

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
