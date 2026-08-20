<?php

namespace App\Support\Forms;

use Illuminate\Validation\ValidationException;

/**
 * Validates what a visitor actually submitted against the published schema.
 *
 * The old validator treated every field as a trimmed string and enforced only
 * `required`, which was fine while the builder could not create anything but
 * text. It is not fine now: a dropdown whose options are never checked accepts
 * any value at all, and a conditional field whose condition is never evaluated
 * can be filled in by anyone who skips the step that reveals it.
 *
 * So the rule here is that the published version is the only authority. A key
 * that is not in it is discarded rather than stored, because a form that
 * accepts fields it never offered is a form that can be posted to with anything.
 */
final class FormAnswers
{
    /**
     * @param  array<string, mixed>  $schema  the published version, already normalised
     * @param  array<string, mixed>  $input
     * @return array<string, string> answers, keyed by field, in schema order
     */
    public static function validate(array $schema, array $input): array
    {
        $answers = [];
        $visible = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $type = FieldType::tryFrom($field['type'] ?? 'text') ?? FieldType::Text;
            $label = $field['label'] ?? $key;

            // Was this field even shown? A conditional field whose condition is
            // unmet is not optional — it does not exist for this submission,
            // and an answer to it is discarded rather than stored.
            $shown = self::isVisible($field, $visible);
            $visible[$key] = null;

            if (! $shown) {
                continue;
            }

            $value = self::scalar($input[$key] ?? null, $type);

            if ($value === '') {
                if ($field['required'] ?? false) {
                    throw ValidationException::withMessages([
                        $key => __(':label is required.', ['label' => $label]),
                    ]);
                }

                $answers[$key] = '';
                $visible[$key] = '';

                continue;
            }

            self::assertValid($key, $label, $type, $field, $value);

            $answers[$key] = $value;
            $visible[$key] = $value;
        }

        return $answers;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, string|null>  $answered  values of the fields already processed
     */
    private static function isVisible(array $field, array $answered): bool
    {
        $condition = $field['visible_when'] ?? null;

        if (! is_array($condition)) {
            return true;
        }

        // The schema guarantees the target comes first, so its answer is
        // already known by the time this runs.
        return ($answered[$condition['field']] ?? null) === $condition['equals'];
    }

    /** @param array<string, mixed> $field */
    private static function assertValid(string $key, string $label, FieldType $type, array $field, string $value): void
    {
        if (mb_strlen($value) > $type->maxLength()) {
            throw ValidationException::withMessages([
                $key => __(':label is too long.', ['label' => $label]),
            ]);
        }

        if ($type === FieldType::Email && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                $key => __('Enter a valid email address.'),
            ]);
        }

        if ($type === FieldType::Url && ! filter_var($value, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                $key => __(':label must be a valid web address.', ['label' => $label]),
            ]);
        }

        if ($type === FieldType::Phone && preg_match('/^[0-9+()\s.-]{6,32}$/', $value) !== 1) {
            throw ValidationException::withMessages([
                $key => __(':label must be a valid phone number.', ['label' => $label]),
            ]);
        }

        /*
         | The one that mattered most. Without this a dropdown is decoration:
         | the browser offers three choices and the endpoint accepts anything,
         | including values the owner never wrote.
         */
        if ($type->hasOptions() && ! in_array($value, $field['options'] ?? [], true)) {
            throw ValidationException::withMessages([
                $key => __('Choose one of the offered answers for :label.', ['label' => $label]),
            ]);
        }
    }

    private static function scalar(mixed $value, FieldType $type): string
    {
        if ($type === FieldType::Checkbox) {
            // A checkbox is present or absent; anything else it might carry is
            // not information the owner asked for.
            return filled($value) && $value !== '0' ? '1' : '';
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
