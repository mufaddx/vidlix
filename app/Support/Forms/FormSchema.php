<?php

namespace App\Support\Forms;

use Illuminate\Validation\ValidationException;

/**
 * The shape of a published inquiry form, and the rules for changing it.
 *
 * A version's schema_json is immutable once published and is bound to every
 * submission made against it, so a form that changes later never rewrites what
 * somebody actually answered. That was already true; what this adds is a
 * contract, so "a field" is a defined thing rather than whatever the last
 * writer happened to put in the array.
 *
 * Everything here is about the schema as data. Validating a visitor's answers
 * against it is FormAnswers' job, and the two are deliberately separate: one
 * runs when an owner edits their form, the other on every public submission.
 */
final class FormSchema
{
    /** Fields every inquiry needs. They can be relabelled but never removed. */
    public const LOCKED_KEYS = ['name', 'email', 'subject', 'message'];

    public const MAX_FIELDS = 25;

    public const MAX_OPTIONS = 30;

    /**
     * Normalise a schema into the canonical shape, rejecting anything that
     * would not survive being rendered or validated.
     *
     * @param  array<string, mixed>  $schema
     * @return array{title: string, description: string, success_message: string, submit_text: string, fields: list<array<string, mixed>>}
     */
    public static function normalise(array $schema): array
    {
        $fields = [];
        $seen = [];

        foreach ($schema['fields'] ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $field = self::normaliseField($raw);

            // A duplicate key would silently shadow the earlier field: both
            // render, one answer arrives, and the other is quietly dropped.
            if (isset($seen[$field['key']])) {
                throw ValidationException::withMessages([
                    'fields' => __('Two fields cannot share the name ":key".', ['key' => $field['key']]),
                ]);
            }

            $seen[$field['key']] = true;
            $fields[] = $field;
        }

        foreach (self::LOCKED_KEYS as $key) {
            if (! isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'fields' => __('The :key field cannot be removed.', ['key' => $key]),
                ]);
            }
        }

        if (count($fields) > self::MAX_FIELDS) {
            throw ValidationException::withMessages([
                'fields' => __('A form can have at most :count fields.', ['count' => self::MAX_FIELDS]),
            ]);
        }

        return [
            'title' => self::text($schema['title'] ?? '', 120) ?: __('Get in touch'),
            'description' => self::text($schema['description'] ?? '', 500),
            'success_message' => self::text($schema['success_message'] ?? '', 500)
                ?: __('Thanks — your message was sent.'),
            'submit_text' => self::text($schema['submit_text'] ?? '', 40) ?: __('Send message'),
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function normaliseField(array $raw): array
    {
        $key = self::key((string) ($raw['key'] ?? ''));

        if ($key === '') {
            throw ValidationException::withMessages([
                'fields' => __('Every field needs a name.'),
            ]);
        }

        $locked = in_array($key, self::LOCKED_KEYS, true);

        $type = FieldType::tryFrom((string) ($raw['type'] ?? 'text'));

        if ($type === null) {
            throw ValidationException::withMessages([
                'fields' => __('":type" is not a field type.', ['type' => (string) ($raw['type'] ?? '')]),
            ]);
        }

        // The four locked fields keep the type the rest of the system expects.
        // An email field turned into a dropdown would break threading, because
        // the reply address comes from that answer.
        if ($locked) {
            $type = match ($key) {
                'email' => FieldType::Email,
                'message' => FieldType::Textarea,
                default => FieldType::Text,
            };
        }

        $field = [
            'key' => $key,
            'type' => $type->value,
            'label' => self::text($raw['label'] ?? '', 120) ?: ucfirst(str_replace('_', ' ', $key)),
            'placeholder' => self::text($raw['placeholder'] ?? '', 120),
            // Locked fields are always required; the form does not work without
            // them, so offering the choice would be offering a broken form.
            'required' => $locked ? true : (bool) ($raw['required'] ?? false),
            'locked' => $locked,
        ];

        if ($type->hasOptions()) {
            $field['options'] = self::options($raw['options'] ?? []);
        }

        /*
         | Conditional visibility, which is what makes "Other → please specify"
         | work: this field appears only when another field holds a given value.
         | The dependency is validated here rather than trusted, because a rule
         | pointing at a field that does not exist would render a field nobody
         | can ever satisfy.
         */
        if (filled($raw['visible_when_field'] ?? null)) {
            $field['visible_when'] = [
                'field' => self::key((string) $raw['visible_when_field']),
                'equals' => self::text($raw['visible_when_value'] ?? '', 120),
            ];
        }

        return $field;
    }

    /**
     * Check that every conditional rule points at a field that exists, comes
     * before it, and offers the value being waited for.
     *
     * Separate from normaliseField() because a field cannot know about its
     * siblings until the whole list is assembled.
     *
     * @param  array{fields: list<array<string, mixed>>}  $schema
     */
    public static function assertConditionsResolve(array $schema): void
    {
        $byKey = [];

        foreach ($schema['fields'] as $field) {
            $condition = $field['visible_when'] ?? null;

            if ($condition !== null) {
                $target = $byKey[$condition['field']] ?? null;

                // Must come earlier: a field that depends on one below it can
                // never be shown, because the answer arrives after the decision.
                if ($target === null) {
                    throw ValidationException::withMessages([
                        'fields' => __('":label" depends on a field that does not come before it.', [
                            'label' => $field['label'],
                        ]),
                    ]);
                }

                $options = $target['options'] ?? null;

                if ($options !== null && ! in_array($condition['equals'], $options, true)) {
                    throw ValidationException::withMessages([
                        'fields' => __('":label" waits for an answer that ":target" does not offer.', [
                            'label' => $field['label'],
                            'target' => $target['label'],
                        ]),
                    ]);
                }
            }

            $byKey[$field['key']] = $field;
        }
    }

    /** Snake-cased, ASCII, and safe to use as a form input name. */
    public static function key(string $value): string
    {
        $key = mb_strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';

        return trim(mb_substr($key, 0, 40), '_');
    }

    /**
     * @param  mixed  $options
     * @return list<string>
     */
    private static function options($options): array
    {
        if (is_string($options)) {
            // The editor sends one option per line, which is how people
            // actually type a list.
            $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
        }

        if (! is_array($options)) {
            $options = [];
        }

        $clean = [];

        foreach ($options as $option) {
            $text = self::text($option, 120);

            if ($text !== '' && ! in_array($text, $clean, true)) {
                $clean[] = $text;
            }
        }

        if ($clean === []) {
            throw ValidationException::withMessages([
                'fields' => __('A dropdown or multiple choice field needs at least one option.'),
            ]);
        }

        return array_slice($clean, 0, self::MAX_OPTIONS);
    }

    private static function text(mixed $value, int $max): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim(strip_tags($value)), 0, $max);
    }
}
