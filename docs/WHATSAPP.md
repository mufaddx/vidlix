# WhatsApp

**Not configured. Not shown.**

There is no WhatsApp provider, no WhatsApp button and no WhatsApp inbox source.
This is deliberate: a fake WhatsApp feature is worse than none, because somebody
will rely on it.

## To add it

WhatsApp requires an approved Business Solution Provider. Until one is
configured, the honest state is absence.

The pattern to follow is the one every other integration uses:

1. `App\Contracts\WhatsAppProviderInterface`
2. An `Unconfigured...` implementation that refuses clearly
3. A live driver registered in `AppServiceProvider::DRIVERS`
4. `WHATSAPP_PROVIDER` in config, defaulting to `unconfigured`
5. Signature verification in `SignatureVerifier`
6. A unique `provider_event_id` for idempotency

Then, and only then, a WhatsApp source in the inbox.

## What it would need to support

Business account connection, approved message templates, explicit opt-in,
incoming and outgoing messages, delivery status, media where permitted,
webhooks, conversation mapping, idempotency, provider health.

## Until then

If the interface ever needs to mention it:

> WhatsApp integration is not configured yet.

Never a button that does nothing.
