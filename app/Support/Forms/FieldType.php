<?php

namespace App\Support\Forms;

/**
 * The kinds of field a public inquiry form may contain.
 *
 * This list is closed on purpose. Every type here has a matching branch in the
 * renderer and, more importantly, in the server-side validator — a type that
 * can be saved but not validated is a field an attacker can put anything into.
 * Adding one means adding both.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Phone = 'phone';
    case Url = 'url';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';

    /** Does this type carry a fixed list of permitted answers? */
    public function hasOptions(): bool
    {
        return match ($this) {
            self::Select, self::Radio => true,
            default => false,
        };
    }

    /** The longest answer this type should ever accept. */
    public function maxLength(): int
    {
        return match ($this) {
            self::Textarea => 5000,
            self::Email, self::Url => 255,
            self::Phone => 32,
            default => 500,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Short text'),
            self::Textarea => __('Long text'),
            self::Email => __('Email address'),
            self::Phone => __('Phone number'),
            self::Url => __('Website'),
            self::Select => __('Dropdown'),
            self::Radio => __('Multiple choice'),
            self::Checkbox => __('Checkbox'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
