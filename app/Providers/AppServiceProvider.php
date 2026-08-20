<?php

namespace App\Providers;

use App\Contracts\CustomHostnameProviderInterface;
use App\Contracts\EmailProviderInterface;
use App\Contracts\InstagramProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Contracts\PayoutProviderInterface;
use App\Contracts\PushProviderInterface;
use App\Models\User;
use App\Services\Integrations\Domains\UnconfiguredHostnameProvider;
use App\Services\Integrations\Email\ResendEmailProvider;
use App\Services\Integrations\Email\SendGridEmailProvider;
use App\Services\Integrations\Email\SmtpEmailProvider;
use App\Services\Integrations\Instagram\MetaInstagramProvider;
use App\Services\Integrations\Payments\RazorpayPaymentProvider;
use App\Services\Integrations\Payments\RazorpayXPayoutProvider;
use App\Services\Integrations\Push\FcmPushProvider;
use App\Services\Integrations\UnconfiguredEmailProvider;
use App\Services\Integrations\UnconfiguredInstagramProvider;
use App\Services\Integrations\UnconfiguredPaymentProvider;
use App\Services\Integrations\UnconfiguredPayoutProvider;
use App\Services\Integrations\UnconfiguredPushProvider;
use App\Support\Ability;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Which concrete adapter each provider slot resolves to.
     *
     * An unknown or absent driver always falls back to the Unconfigured
     * adapter, so a typo in .env degrades to PROVIDER_NOT_CONFIGURED rather
     * than to a half-working integration.
     */
    private const DRIVERS = [
        PaymentProviderInterface::class => [
            'razorpay' => RazorpayPaymentProvider::class,
        ],
        PayoutProviderInterface::class => [
            'razorpay' => RazorpayXPayoutProvider::class,
            'razorpayx' => RazorpayXPayoutProvider::class,
        ],
        EmailProviderInterface::class => [
            'resend' => ResendEmailProvider::class,
            'sendgrid' => SendGridEmailProvider::class,
            'smtp' => SmtpEmailProvider::class,
            'ses' => SmtpEmailProvider::class,
            'postmark' => SmtpEmailProvider::class,
        ],
        InstagramProviderInterface::class => [
            'meta' => MetaInstagramProvider::class,
            'instagram_graph' => MetaInstagramProvider::class,
        ],
        PushProviderInterface::class => [
            'fcm' => FcmPushProvider::class,
            'firebase' => FcmPushProvider::class,
        ],
        // No live driver yet. The contract and the fallback exist so the
        // feature can be built and tested against a provider that honestly
        // refuses, rather than against a stub that pretends to succeed.
        CustomHostnameProviderInterface::class => [],
    ];

    private const FALLBACKS = [
        PaymentProviderInterface::class => UnconfiguredPaymentProvider::class,
        PayoutProviderInterface::class => UnconfiguredPayoutProvider::class,
        EmailProviderInterface::class => UnconfiguredEmailProvider::class,
        InstagramProviderInterface::class => UnconfiguredInstagramProvider::class,
        PushProviderInterface::class => UnconfiguredPushProvider::class,
        CustomHostnameProviderInterface::class => UnconfiguredHostnameProvider::class,
    ];

    private const CONFIG_KEYS = [
        PaymentProviderInterface::class => 'vidlix.providers.payment',
        PayoutProviderInterface::class => 'vidlix.providers.payout',
        EmailProviderInterface::class => 'vidlix.providers.email',
        InstagramProviderInterface::class => 'vidlix.providers.instagram',
        PushProviderInterface::class => 'vidlix.providers.push',
        CustomHostnameProviderInterface::class => 'vidlix.providers.custom_domains',
    ];

    public function register(): void
    {
        foreach (self::CONFIG_KEYS as $contract => $configKey) {
            $this->app->bind($contract, function () use ($contract, $configKey) {
                $driver = strtolower((string) config($configKey, 'unconfigured'));
                $concrete = self::DRIVERS[$contract][$driver] ?? self::FALLBACKS[$contract];
                $instance = $this->app->make($concrete);

                // Credentials can be missing even when a driver is named. In
                // that case fall back so callers see PROVIDER_NOT_CONFIGURED
                // instead of a live-looking adapter that cannot do anything.
                if (! $instance->isConfigured() && $concrete !== self::FALLBACKS[$contract]) {
                    return $this->app->make(self::FALLBACKS[$contract]);
                }

                return $instance;
            });
        }
    }

    public function boot(): void
    {
        /*
         | One gate per ability, so every admin route names exactly what it
         | needs. Previously a single "admin" middleware accepted six role slugs
         | and opened everything behind it, which meant whoever could edit CMS
         | copy could also approve a real bank transfer.
         */
        foreach (Ability::all() as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasAbility($ability));
        }

        Event::listen(Registered::class, SendEmailVerificationNotification::class);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.$request->input('login')));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        // OtpService applies its own per-destination limits; this is the outer
        // guard so one IP cannot hammer the endpoints at all.
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(12)->by($request->ip()));
        RateLimiter::for('public-form', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // A once-a-minute trigger needs very little headroom.
        RateLimiter::for('scheduler', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
