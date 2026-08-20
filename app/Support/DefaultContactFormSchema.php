<?php

namespace App\Support;

use App\Support\Forms\FieldType;

/**
 * The form somebody starts with.
 *
 * Four locked fields plus a couple of useful optional ones, so a new profile
 * has a working contact form on day one and the builder has something to edit
 * rather than an empty canvas.
 */
final class DefaultContactFormSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        return [
            'title' => "Let's work together",
            'description' => 'Tell me about your campaign.',
            'success_message' => 'Thanks — your message was sent. I will reply by email.',
            'submit_text' => 'Send inquiry',
            'fields' => [
                self::locked('name', 'Your name', FieldType::Text),
                self::locked('email', 'Your email', FieldType::Email),
                self::locked('subject', 'Subject', FieldType::Text),
                self::locked('message', 'What do you have in mind?', FieldType::Textarea),
                self::optional('company', 'Company', FieldType::Text),
                self::optional('budget', 'Budget', FieldType::Text),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forEditor(): array
    {
        return [
            'title' => 'Get in touch',
            'description' => 'Tell me what you need edited.',
            'success_message' => 'Thanks — your message was sent. I will reply by email.',
            'submit_text' => 'Send enquiry',
            'fields' => [
                self::locked('name', 'Your name', FieldType::Text),
                self::locked('email', 'Your email', FieldType::Email),
                self::locked('subject', 'Subject', FieldType::Text),
                self::locked('message', 'What do you need edited?', FieldType::Textarea),
                self::optional('company', 'Company', FieldType::Text),
                self::optional('deadline', 'Preferred deadline', FieldType::Text),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function locked(string $key, string $label, FieldType $type): array
    {
        return [
            'key' => $key,
            'type' => $type->value,
            'label' => $label,
            'placeholder' => '',
            'required' => true,
            'locked' => true,
        ];
    }

    /** @return array<string, mixed> */
    private static function optional(string $key, string $label, FieldType $type): array
    {
        return [
            'key' => $key,
            'type' => $type->value,
            'label' => $label,
            'placeholder' => '',
            'required' => false,
            'locked' => false,
        ];
    }
}
