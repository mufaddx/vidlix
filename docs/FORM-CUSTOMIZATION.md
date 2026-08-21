# Contact form customization

Creators and editors each get one contact form, reachable at
`vidlix.in/{username}/contact`. It is the same builder for both — a contact form
was never creator-shaped, it only lived on the creator page for historical
reasons.

## The pieces

| Thing | Where |
|---|---|
| Schema contract | `App\Support\Forms\FormSchema` |
| Field types | `App\Support\Forms\FieldType` |
| Answer validation | `App\Support\Forms\FormAnswers` |
| Editing | `App\Services\Forms\ContactFormBuilder` |
| Submission | `App\Services\Forms\PublicInquiries` |
| Rendering | `resources/views/partials/inquiry-fields.blade.php` |

## Versioning

Every save publishes a new version. A submission stores
`contact_form_version_id`, so editing the form next month never rewrites what
somebody answered last week.

`contact_form_versions.schema_json` is immutable once written. The fields are
**not** normalised into rows on purpose: a separate table would duplicate the
version history and reintroduce the mutability the design avoids.

## Field types

```
text      textarea   email    phone
url       select     radio    checkbox
```

The list is closed. Every type has a branch in **both** the renderer and the
validator — a type that can be saved but not validated is a field an attacker
fills freely. Adding one means adding both.

## Locked fields

`name`, `email`, `subject`, `message` cannot be removed and are always required.
Replies do not work without them: the reply address comes from `email`, and the
thread subject from `subject`. They can be relabelled freely.

## Conditional fields — "Other → please specify"

A field may carry a condition:

```php
'visible_when' => ['field' => 'topic', 'equals' => 'Other']
```

Rules enforced at publish time:

1. The target field must exist.
2. It must come **before** this one — a field depending on one below it can
   never be shown, because the answer arrives after the decision.
3. If the target has options, the awaited value must be among them.

Publishing is refused otherwise. A field that can never appear is worse than no
field.

At submission the condition is **re-evaluated server-side**. Answers to fields
that were not shown are discarded rather than stored. The hiding in the browser
is a convenience; it is never the check.

## Validation rules

`FormAnswers::validate()` enforces:

- Required fields are present
- Length against the type's maximum
- `email` parses as an email, `url` as a URL, `phone` against a permissive pattern
- **Choice fields accept only their published options** — without this a
  dropdown is decoration: the browser offers three choices and the endpoint
  accepts anything
- Keys not in the published version are **discarded**, not stored
- Checkbox is presence-or-absence, nothing else

## Anti-spam

Three layers, all server-side:

- Honeypot field, named by `config('vidlix.public_form_honeypot')`
- Cloudflare Turnstile via the `turnstile` middleware
- `throttle:public-form`

A honeypot trip fails with the same generic message a real error gives. Telling
a bot which check it tripped is telling it how to pass next time.

## What happens on submission

1. Owner resolved **from the URL**, never from the request body
2. Form enabled and published?
3. Honeypot, Turnstile, rate limit
4. Validate against the published version
5. In one transaction: external contact, conversation, participant, message,
   submission row
6. Acknowledgement email with the Reply-To that routes replies back
7. Notify the owner
8. Audit event

The owner is never taken from a hidden field. A form carrying its own
destination is a form anyone can retarget at somebody else's inbox.

## Disabling

`is_enabled = false` stops new messages and leaves everything already received.
Deleting would orphan conversations people are still replying to.

## Testing

`tests/Feature/ContactFormBuilderTest.php` — including the end-to-end journey:
add a College/Other dropdown, publish, submit as a stranger, and confirm the
answer reaches the inbox bound to the version that asked for it.
