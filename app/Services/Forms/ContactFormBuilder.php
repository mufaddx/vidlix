<?php

namespace App\Services\Forms;

use App\Models\ContactForm;
use App\Models\ContactFormVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\DefaultContactFormSchema;
use App\Support\Forms\FormSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editing a public inquiry form.
 *
 * Every change publishes a new version rather than mutating the current one.
 * That is not caution for its own sake: a submission is stored against the
 * version that produced it, so an owner who adds a field next week must not
 * retroactively change what somebody answered last week.
 *
 * One builder serves creators and editors. They had separate paths only because
 * the table hung off the creator page, which was never a real difference
 * between them.
 */
class ContactFormBuilder
{
    public function __construct(private AuditLogger $audit) {}

    /** The form for this person in this role, created on first use. */
    public function formFor(User $user, string $scope): ContactForm
    {
        $form = ContactForm::query()
            ->where('owner_user_id', $user->id)
            ->where('owner_scope', $scope)
            ->first();

        if ($form !== null) {
            return $form;
        }

        return DB::transaction(function () use ($user, $scope) {
            $form = ContactForm::query()->create([
                'owner_user_id' => $user->id,
                'owner_scope' => $scope,
                'current_version' => 1,
                'is_enabled' => true,
            ]);

            ContactFormVersion::query()->create([
                'contact_form_id' => $form->id,
                'version_number' => 1,
                'schema_json' => $scope === 'editor'
                    ? DefaultContactFormSchema::forEditor()
                    : DefaultContactFormSchema::make(),
                'published_at' => now(),
            ]);

            return $form;
        });
    }

    /** What the builder should show: the newest version, published or not. */
    public function workingSchema(ContactForm $form): array
    {
        $version = $form->versions()->orderByDesc('version_number')->first();

        return $version?->schema_json ?? DefaultContactFormSchema::make();
    }

    /**
     * Save a whole schema as a new version.
     *
     * The schema arrives as one document rather than as a stream of add/edit/
     * delete calls, because reordering, renaming and adding a conditional field
     * are usually one edit in the person's head and should be one version in
     * the history too.
     *
     * @param  array<string, mixed>  $schema
     */
    public function publish(ContactForm $form, array $schema, ?User $actor = null): ContactFormVersion
    {
        $normalised = FormSchema::normalise($schema);
        FormSchema::assertConditionsResolve($normalised);

        return DB::transaction(function () use ($form, $normalised, $actor) {
            $next = ((int) $form->versions()->max('version_number')) + 1;

            $version = ContactFormVersion::query()->create([
                'contact_form_id' => $form->id,
                'version_number' => $next,
                'schema_json' => $normalised,
                'published_at' => now(),
            ]);

            $form->update(['current_version' => $next]);

            $this->audit->record('contact_form.published', $form, [
                'version' => $next,
                'fields' => count($normalised['fields']),
            ], $actor?->id);

            return $version;
        });
    }

    /**
     * Turn the form off without deleting it.
     *
     * Disabling keeps every past submission and the schema that produced it;
     * deleting would orphan conversations that people are still replying to.
     */
    public function setEnabled(ContactForm $form, bool $enabled, ?User $actor = null): void
    {
        $form->update(['is_enabled' => $enabled]);

        $this->audit->record(
            $enabled ? 'contact_form.enabled' : 'contact_form.disabled',
            $form,
            [],
            $actor?->id,
        );
    }

    /**
     * Move a field. Returns the reordered schema without publishing it, so a
     * caller can reorder and then save once.
     *
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $order  field keys, in the order wanted
     * @return array<string, mixed>
     */
    public function reorder(array $schema, array $order): array
    {
        $byKey = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $byKey[$field['key']] = $field;
        }

        $reordered = [];

        foreach ($order as $key) {
            if (isset($byKey[$key])) {
                $reordered[] = $byKey[$key];
                unset($byKey[$key]);
            }
        }

        // Anything the order did not mention keeps its place at the end rather
        // than disappearing — a partial order should not delete fields.
        foreach ($byKey as $field) {
            $reordered[] = $field;
        }

        $schema['fields'] = $reordered;

        return $schema;
    }

    /**
     * The version a public submission must be validated against.
     *
     * Null when the form is disabled or was never published, which the caller
     * should treat as "not accepting inquiries" rather than as an error.
     */
    public function publishedVersion(ContactForm $form): ?ContactFormVersion
    {
        if (! $form->is_enabled) {
            return null;
        }

        return $form->publishedVersion();
    }

    /** @param array<string, mixed> $schema */
    public function assertFieldRemovable(array $schema, string $key): void
    {
        if (in_array($key, FormSchema::LOCKED_KEYS, true)) {
            throw ValidationException::withMessages([
                'fields' => __('The :key field cannot be removed.', ['key' => $key]),
            ]);
        }

        $_ = $schema;
    }
}
