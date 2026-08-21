# Deployment

Host-specific steps for the current shared host are in
`DEPLOYMENT_HOSTINGER.md`. This is the general shape.

## Before the first deploy

1. Four DNS records and certificates — see `docs/DOMAINS.md`
2. Mail domain with SPF, DKIM and DMARC
3. S3-compatible bucket (R2 or S3)
4. Payment provider account and webhook secret
5. Meta app, if Instagram is in scope

## Environment

```
APP_ENV=production
APP_DEBUG=false          # never true in production
APP_KEY=                 # php artisan key:generate

APP_URL=https://vidlix.in
APP_APP_URL=https://app.vidlix.in
AUTODM_APP_URL=https://autodm.vidlix.in
ADMIN_APP_URL=https://admin.vidlix.in

FILESYSTEM_DISK=s3       # the default is local — this MUST be changed
MEDIA_DISK=s3

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

MAIL_FROM_CREATOR=creator@vidlix.in
MAIL_FROM_EDITOR=editor@vidlix.in
# ... see docs/DOMAINS.md for all seven

EMAIL_PROVIDER=
PAYMENT_PROVIDER=
INSTAGRAM_PROVIDER=
CUSTOM_DOMAIN_PROVIDER=
```

Secrets come from environment-specific secret management, never from a committed
file. `.env` is untracked and Gitleaks scans full history in CI.

## Deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Smoke tests

After every deploy:

1. `GET /up` returns 200
2. Landing page renders
3. A public profile resolves at `vidlix.in/{username}`
4. A contact form renders and accepts a submission
5. Sign-in works
6. `/admin/login` is reachable and a member session cannot get past it
7. An unknown hostname returns 404, not the site

## Rollback

1. Put the site in maintenance: `php artisan down`
2. Deploy the previous release
3. **Migrations are not automatically reversed.** Check whether the failed
   release ran any. If it did, run `php artisan migrate:rollback --step=1` only
   after confirming the `down()` is safe for real data
4. `php artisan up`

Migrations that drop or rename data have deliberate `down()` methods — see
`docs/MANAGER-REMOVAL.md` for the pattern. Archive-by-rename means a rollback is
a rename back rather than a restore.

## Backups

Not yet rehearsed. Before launch:

- Automated nightly database dump, offsite
- Object storage versioning or replication
- **A restore actually performed on staging.** A backup nobody has restored is a
  hope, not a backup

## Monitoring

- `/up` health endpoint
- `/admin/health` — provider status, queue depth, failed jobs
- `request_id` on every response and log line
- Webhook rejections in `webhook_logs`
- Failed AutoDM runs in `autodm_runs`

## Queue and scheduler

The queue must be running for outbound email, Instagram sync and media
processing. On hosts without cron, `POST /api/internal/scheduler/run` with
`X-Cron-Token` drives the scheduler; it 404s unless `CRON_TOKEN` is set.
