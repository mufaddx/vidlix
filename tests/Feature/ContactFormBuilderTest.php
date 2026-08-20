<?php

namespace Tests\Feature;

use App\Models\ContactFormSubmission;
use App\Models\Conversation;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Forms\ContactFormBuilder;
use App\Support\Forms\FormAnswers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The form builder, and the validator that stands behind it.
 *
 * The validator gets the closer attention. A builder that renders a dropdown is
 * cosmetic; a submission endpoint that accepts any value for that dropdown is a
 * hole, and the two are easy to get out of step.
 */
class ContactFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function creator(string $username = 'mira'): CreatorProfile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = app(CreatorOnboardingService::class)->provision($user->id, 'Mira Rao');

        $profile->update(['username' => $username, 'visibility' => 'public']);
        $profile->publicPage->update([
            'status' => 'published',
            'published_payload' => ['hero_title' => 'Mira Rao', 'cta_text' => 'Work with me'],
        ]);

        return $profile->fresh();
    }

    private function editor(string $username = 'rahul'): EditorProfile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return EditorProfile::query()->create([
            'user_id' => $user->id,
            'username' => $username,
            'display_name' => 'Rahul Sen',
            'application_status' => 'approved',
            'visibility' => 'public',
            'accepts_inquiries' => true,
        ]);
    }

    /* ------------------------------------------------- the flagship journey */

    public function test_a_creator_adds_college_and_other_and_a_visitor_answers_them(): void
    {
        $creator = $this->creator('mira');
        $owner = $creator->user;

        // 1. The creator adds the question, with Other among its answers.
        $this->actingAs($owner)
            ->post(route('app.contact-form.fields.add'), [
                'label' => 'What are you contacting me about?',
                'type' => 'select',
                'required' => '1',
                'options' => "College\nBrand collaboration\nProject\nOther",
            ])->assertRedirect();

        // 2. And a follow-up that only appears when they picked Other.
        $builder = app(ContactFormBuilder::class);
        $form = $builder->formFor($owner, 'creator');
        $schema = $builder->workingSchema($form);

        $schema['fields'][] = [
            'key' => 'please_specify',
            'type' => 'text',
            'label' => 'Please specify',
            'required' => true,
            'visible_when_field' => 'what_are_you_contacting_me_about',
            'visible_when_value' => 'Other',
        ];

        $builder->publish($form, $schema, $owner);

        // 3. The form is live at the address the creator would share.
        $this->get('/mira/contact')
            ->assertOk()
            ->assertSee('What are you contacting me about?', false)
            ->assertSee('College', false)
            ->assertSee('Please specify', false);

        // 4. A stranger, with no account, answers it.
        $this->post('/mira/contact', [
            'name' => 'Asha Menon',
            'email' => 'asha@college.test',
            'subject' => 'Campus fest',
            'message' => 'Would you speak at our fest?',
            'what_are_you_contacting_me_about' => 'College',
        ])->assertRedirect();

        // 5. It became a real conversation in the creator's inbox.
        $conversation = Conversation::query()->where('subject', 'Campus fest')->firstOrFail();
        $this->assertSame($owner->id, $conversation->owner_user_id);
        $this->assertSame('creator', $conversation->owner_scope);
        $this->assertSame('external_email', $conversation->channel);

        // 6. And the custom answer was stored against the version that asked
        //    for it, not against whatever the form says next month.
        $submission = ContactFormSubmission::query()->latest('id')->firstOrFail();
        $this->assertSame('College', $submission->answers['what_are_you_contacting_me_about']);
        $this->assertSame($form->publishedVersion()->id, $submission->contact_form_version_id);

        // 7. The answer reaches the inbox, not only the database row.
        $this->assertStringContainsString('College', $conversation->messages()->first()->body);
    }

    public function test_the_same_journey_works_for_an_editor(): void
    {
        $editor = $this->editor('rahul');
        $owner = $editor->user;

        $this->actingAs($owner)
            ->post(route('app.contact-form.fields.add'), [
                'label' => 'Service required',
                'type' => 'select',
                'options' => "Short form\nLong form\nOther",
            ])->assertRedirect();

        $this->get('/rahul/contact')->assertOk()->assertSee('Service required', false);

        $this->post('/rahul/contact', [
            'name' => 'Devi',
            'email' => 'devi@brand.test',
            'subject' => 'Reel cut',
            'message' => 'Six reels a month.',
            'service_required' => 'Short form',
        ])->assertRedirect();

        $conversation = Conversation::query()->where('subject', 'Reel cut')->firstOrFail();
        $this->assertSame($owner->id, $conversation->owner_user_id);
        $this->assertSame('editor', $conversation->owner_scope);
    }

    /* ------------------------------------------------------ answer validation */

    public function test_a_dropdown_refuses_a_value_it_never_offered(): void
    {
        $schema = [
            'fields' => [
                ['key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => true,
                    'options' => ['College', 'Other']],
            ],
        ];

        // The browser offered two choices. The endpoint must not accept a third.
        $this->expectException(ValidationException::class);

        FormAnswers::validate($schema, ['topic' => 'Anything I like']);
    }

    public function test_an_answer_to_a_hidden_conditional_field_is_discarded(): void
    {
        $schema = [
            'fields' => [
                ['key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => true,
                    'options' => ['College', 'Other']],
                ['key' => 'detail', 'type' => 'text', 'label' => 'Please specify', 'required' => true,
                    'visible_when' => ['field' => 'topic', 'equals' => 'Other']],
            ],
        ];

        $answers = FormAnswers::validate($schema, [
            'topic' => 'College',
            'detail' => 'sneaked in',
        ]);

        // The field was never shown, so its answer is not the visitor's — it is
        // whatever somebody posted directly at the endpoint.
        $this->assertArrayNotHasKey('detail', $answers);
    }

    public function test_a_conditional_field_becomes_required_once_it_is_shown(): void
    {
        $schema = [
            'fields' => [
                ['key' => 'topic', 'type' => 'select', 'label' => 'Topic', 'required' => true,
                    'options' => ['College', 'Other']],
                ['key' => 'detail', 'type' => 'text', 'label' => 'Please specify', 'required' => true,
                    'visible_when' => ['field' => 'topic', 'equals' => 'Other']],
            ],
        ];

        $this->expectException(ValidationException::class);

        FormAnswers::validate($schema, ['topic' => 'Other']);
    }

    public function test_keys_the_form_never_offered_are_not_stored(): void
    {
        $schema = ['fields' => [['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true]]];

        $answers = FormAnswers::validate($schema, [
            'name' => 'Asha',
            'is_admin' => '1',
            'amount_minor' => '999999',
        ]);

        $this->assertSame(['name' => 'Asha'], $answers);
    }

    public function test_field_types_are_actually_checked(): void
    {
        $email = ['fields' => [['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true]]];

        $this->expectException(ValidationException::class);

        FormAnswers::validate($email, ['email' => 'not-an-email']);
    }

    public function test_an_overlong_answer_is_refused(): void
    {
        $schema = ['fields' => [['key' => 'note', 'type' => 'text', 'label' => 'Note', 'required' => false]]];

        $this->expectException(ValidationException::class);

        FormAnswers::validate($schema, ['note' => str_repeat('a', 5000)]);
    }

    /* --------------------------------------------------------- schema rules */

    public function test_the_four_locked_fields_cannot_be_removed(): void
    {
        $creator = $this->creator('locked');

        $this->actingAs($creator->user)
            ->delete(route('app.contact-form.fields.remove', 'email'))
            ->assertSessionHasErrors('fields');
    }

    public function test_a_condition_pointing_at_a_later_field_is_refused(): void
    {
        $creator = $this->creator('ordering');
        $builder = app(ContactFormBuilder::class);
        $form = $builder->formFor($creator->user, 'creator');

        $schema = $builder->workingSchema($form);
        $schema['fields'][] = [
            'key' => 'early', 'type' => 'text', 'label' => 'Early', 'required' => false,
            // Depends on something that comes after it, so it could never show.
            'visible_when_field' => 'later', 'visible_when_value' => 'yes',
        ];
        $schema['fields'][] = ['key' => 'later', 'type' => 'text', 'label' => 'Later', 'required' => false];

        $this->expectException(ValidationException::class);

        $builder->publish($form, $schema, $creator->user);
    }

    public function test_editing_a_form_never_rewrites_an_answer_already_given(): void
    {
        $creator = $this->creator('history');
        $owner = $creator->user;

        $this->actingAs($owner)->post(route('app.contact-form.fields.add'), [
            'label' => 'Budget range',
            'type' => 'select',
            'options' => "Under 1L\nOver 1L",
        ])->assertRedirect();

        $this->post('/history/contact', [
            'name' => 'Asha', 'email' => 'asha@brand.test',
            'subject' => 'Work', 'message' => 'Hello', 'budget_range' => 'Under 1L',
        ])->assertRedirect();

        $submission = ContactFormSubmission::query()->latest('id')->firstOrFail();
        $versionAtSubmission = $submission->contact_form_version_id;

        // The creator changes the question afterwards.
        $this->actingAs($owner)->delete(route('app.contact-form.fields.remove', 'budget_range'))
            ->assertRedirect();

        $submission->refresh();

        $this->assertSame($versionAtSubmission, $submission->contact_form_version_id);
        $this->assertSame('Under 1L', $submission->answers['budget_range']);
    }

    /* ---------------------------------------------------------- enable state */

    public function test_a_disabled_form_stops_accepting_messages(): void
    {
        $creator = $this->creator('closed');

        $this->actingAs($creator->user)
            ->post(route('app.contact-form.toggle'), ['enabled' => '0'])
            ->assertRedirect();

        $this->get('/closed/contact')->assertNotFound();

        $this->post('/closed/contact', [
            'name' => 'Asha', 'email' => 'asha@brand.test',
            'subject' => 'Hi', 'message' => 'Hello',
        ])->assertNotFound();
    }

    public function test_disabling_a_form_keeps_what_it_already_received(): void
    {
        $creator = $this->creator('keeps');

        $this->post('/keeps/contact', [
            'name' => 'Asha', 'email' => 'asha@brand.test',
            'subject' => 'Before', 'message' => 'Hello',
        ])->assertRedirect();

        $this->actingAs($creator->user)
            ->post(route('app.contact-form.toggle'), ['enabled' => '0']);

        $this->assertDatabaseHas('conversations', ['subject' => 'Before']);
        $this->assertSame(1, ContactFormSubmission::query()->count());
    }

    /* ------------------------------------------------------- authorisation */

    public function test_nobody_can_edit_a_form_that_is_not_theirs(): void
    {
        $mine = $this->creator('mine');
        $theirs = $this->creator('theirs');

        $this->actingAs($mine->user)->post(route('app.contact-form.fields.add'), [
            'label' => 'Mine only',
            'type' => 'text',
        ])->assertRedirect();

        // There is no form id in any route, so the only form reachable is the
        // caller's own. The other account must be untouched.
        $builder = app(ContactFormBuilder::class);
        $others = $builder->workingSchema($builder->formFor($theirs->user, 'creator'));
        $keys = array_column($others['fields'], 'key');

        $this->assertNotContains('mine_only', $keys);
    }

    public function test_the_honeypot_still_refuses_a_bot(): void
    {
        $this->creator('bait');

        $this->post('/bait/contact', [
            'name' => 'Bot', 'email' => 'bot@spam.test',
            'subject' => 'Hi', 'message' => 'Hello',
            config('vidlix.public_form_honeypot') => 'filled in',
        ])->assertSessionHasErrors('form');

        $this->assertDatabaseCount('conversations', 0);
    }
}
