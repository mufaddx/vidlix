@if(app(\App\Services\Security\Turnstile::class)->isConfigured())
    <div class="cf-turnstile" data-sitekey="{{ app(\App\Services\Security\Turnstile::class)->siteKey() }}"></div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
