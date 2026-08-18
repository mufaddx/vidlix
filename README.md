# Vidlix

One Laravel backend. Three clients: **website**, **iOS**, **Android** (Flutter). Phones never talk to MySQL — only `HTTPS /api/v1`.

## Website

```bash
cd C:\Vidlix
php artisan migrate:fresh --seed
php artisan serve
```

Open http://127.0.0.1:8000

## iOS / Android / Flutter web

Keep `php artisan serve` running, then:

```bash
cd C:\Vidlix\mobile
flutter pub get
flutter run                 # pick iPhone simulator, Android emulator, or Chrome
```

Android emulator uses `http://10.0.2.2:8000`. iOS simulator and Flutter web use `http://127.0.0.1:8000`. Physical phone:  
`flutter run --dart-define=API_BASE=http://YOUR_LAN_IP:8000`

| Account | Email | Password |
| --- | --- | --- |
| Admin | admin@vidlix.test | ChangeMe_Admin1 |
| Creator | creator@vidlix.test | Creator_Pass1 |
| Editor | editor@vidlix.test | Editor_Pass1 |
| Brand | brand@vidlix.test | Brand_Pass1 |

Public web: `/u/mursalim`

## Providers

Payments (Razorpay), payouts (RazorpayX), email (SendGrid/SMTP/SES/Postmark),
Instagram (official Meta Graph) and S3-compatible object storage are all wired
behind interfaces and stay off until their credentials exist. A missing
credential reports `PROVIDER_NOT_CONFIGURED`; nothing is ever faked into looking
paid, delivered, or synced. See `docs/INTEGRATIONS.md`.

Docs: `docs/ARCHITECTURE.md` · `docs/INTEGRATIONS.md` · `docs/RUN.md` · `docs/DEPLOYMENT_HOSTINGER.md`

Hostinger: `docs/DEPLOYMENT_HOSTINGER.md`  
App Store / Play Store binaries still need your Apple/Google developer accounts and store listing — the **source** for both stores is `mobile/`.
