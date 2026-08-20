<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContactForm;
use App\Services\Forms\ContactFormBuilder;
use App\Support\Forms\FieldType;
use App\Support\Forms\FormSchema;
use App\Support\PublicUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The form builder, for creators and editors alike.
 *
 * Which form somebody is editing comes from their session role and their own
 * account — never from a form id in the request. That is the whole of the
 * authorisation story here, and it is deliberately not expressible any other
 * way: there is no route that takes a form id, so there is nothing to tamper
 * with.
 */
class ContactFormController extends Controller
{
    public function __construct(private ContactFormBuilder $builder) {}

    public function edit(Request $request): View
    {
        $scope = $this->scope($request);
        $form = $this->builder->formFor($request->user(), $scope);
        $schema = $this->builder->workingSchema($form);

        return view('app.contact-form', [
            'form' => $form,
            'schema' => $schema,
            'scope' => $scope,
            'types' => FieldType::cases(),
            'lockedKeys' => FormSchema::LOCKED_KEYS,
            'username' => $this->username($request, $scope),
            'publicUrl' => $this->publicUrl($request, $scope),
        ]);
    }

    /** Save the whole form: settings, fields, order, all as one version. */
    public function save(Request $request): RedirectResponse
    {
        $scope = $this->scope($request);
        $form = $this->builder->formFor($request->user(), $scope);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'submit_text' => ['nullable', 'string', 'max:40'],
            'fields' => ['required', 'array', 'min:1', 'max:'.FormSchema::MAX_FIELDS],
            'fields.*.key' => ['required', 'string', 'max:40'],
            'fields.*.type' => ['required', 'string'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:120'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.visible_when_field' => ['nullable', 'string', 'max:40'],
            'fields.*.visible_when_value' => ['nullable', 'string', 'max:120'],
        ]);

        // Checkboxes are absent rather than false when unticked, so required is
        // read as presence rather than trusted to arrive.
        $fields = [];

        foreach ($data['fields'] as $index => $field) {
            $field['required'] = $request->boolean("fields.{$index}.required");
            $fields[] = $field;
        }

        $this->builder->publish($form, [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'success_message' => $data['success_message'] ?? '',
            'submit_text' => $data['submit_text'] ?? '',
            'fields' => $fields,
        ], $request->user());

        return back()->with('status', __('Form published. Earlier submissions keep the form they were sent with.'));
    }

    public function addField(Request $request): RedirectResponse
    {
        $scope = $this->scope($request);
        $form = $this->builder->formFor($request->user(), $scope);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:'.implode(',', FieldType::values())],
            'required' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'string', 'max:2000'],
        ]);

        $schema = $this->builder->workingSchema($form);
        $key = FormSchema::key($data['label']);

        if ($key === '') {
            throw ValidationException::withMessages(['label' => __('Give the field a name.')]);
        }

        foreach ($schema['fields'] as $existing) {
            if ($existing['key'] === $key) {
                throw ValidationException::withMessages([
                    'label' => __('You already have a field called that.'),
                ]);
            }
        }

        $schema['fields'][] = [
            'key' => $key,
            'type' => $data['type'],
            'label' => $data['label'],
            'placeholder' => '',
            'required' => $request->boolean('required'),
            'locked' => false,
            'options' => $data['options'] ?? '',
        ];

        $this->builder->publish($form, $schema, $request->user());

        return back()->with('status', __('Field added.'));
    }

    public function removeField(Request $request, string $key): RedirectResponse
    {
        $scope = $this->scope($request);
        $form = $this->builder->formFor($request->user(), $scope);

        $schema = $this->builder->workingSchema($form);
        $this->builder->assertFieldRemovable($schema, $key);

        $before = count($schema['fields']);
        $schema['fields'] = array_values(array_filter(
            $schema['fields'],
            fn (array $field) => $field['key'] !== $key,
        ));

        abort_if(count($schema['fields']) === $before, 404);

        // A field that others were waiting on takes their condition with it,
        // rather than leaving them permanently invisible.
        foreach ($schema['fields'] as $index => $field) {
            if (($field['visible_when']['field'] ?? null) === $key) {
                unset($schema['fields'][$index]['visible_when']);
            }
        }

        $this->builder->publish($form, $schema, $request->user());

        return back()->with('status', __('Field removed.'));
    }

    public function reorder(Request $request): RedirectResponse
    {
        $scope = $this->scope($request);
        $form = $this->builder->formFor($request->user(), $scope);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'max:40'],
        ]);

        $schema = $this->builder->reorder($this->builder->workingSchema($form), $data['order']);

        $this->builder->publish($form, $schema, $request->user());

        return back()->with('status', __('Order saved.'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $scope = $this->scope($request);
        $form = $this->builder->formFor($request->user(), $scope);

        $this->builder->setEnabled($form, $request->boolean('enabled'), $request->user());

        return back()->with('status', $request->boolean('enabled')
            ? __('Your form is live.')
            : __('Your form is off. Nobody can send you a message through it.'));
    }

    /**
     * Which form this person is editing.
     *
     * Taken from the profiles they actually hold, and refused outright if they
     * hold neither — so somebody without a creator or editor profile cannot
     * conjure a form by asking for one.
     */
    private function scope(Request $request): string
    {
        $user = $request->user();
        $active = session('active_role');

        if ($active === 'editor' && $user->editorProfile !== null) {
            return 'editor';
        }

        if ($user->creatorProfile !== null) {
            return 'creator';
        }

        if ($user->editorProfile !== null) {
            return 'editor';
        }

        abort(403, __('Add a creator or editor profile before setting up a contact form.'));
    }

    private function username(Request $request, string $scope): ?string
    {
        $profile = $scope === 'editor'
            ? $request->user()->editorProfile
            : $request->user()->creatorProfile;

        return $profile?->username;
    }

    private function publicUrl(Request $request, string $scope): ?string
    {
        $username = $this->username($request, $scope);

        return $username === null ? null : PublicUrl::contact($username);
    }

    private function formOf(Request $request): ContactForm
    {
        return $this->builder->formFor($request->user(), $this->scope($request));
    }
}
