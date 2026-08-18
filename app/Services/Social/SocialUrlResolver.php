<?php

namespace App\Services\Social;

use App\Models\SocialPlatform;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialUrlResolver
{
    public function resolve(SocialPlatform $platform, string $mode, string $value): string
    {
        $value = trim($value);

        if ($mode === 'full_url') {
            if (! filter_var($value, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages(['input_value' => __('Enter a valid URL.')]);
            }

            $scheme = parse_url($value, PHP_URL_SCHEME);
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages(['input_value' => __('Only http(s) URLs are allowed.')]);
            }

            return $value;
        }

        if (! $platform->supports_username || ! $platform->username_url_template) {
            throw ValidationException::withMessages(['input_mode' => __('This platform requires a full URL.')]);
        }

        $username = ltrim($value, '@');
        if (! preg_match('/^[A-Za-z0-9._-]{2,64}$/', $username)) {
            throw ValidationException::withMessages(['input_value' => __('Enter a valid username.')]);
        }

        return Str::replace('{username}', $username, $platform->username_url_template);
    }
}
