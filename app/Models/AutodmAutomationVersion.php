<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The terms of an automation, frozen at the moment it was activated.
 *
 * Never edited. A change is a new version, so a run from months ago can still
 * say exactly which rules produced it — which is the only way to answer "why
 * did it send that?" honestly.
 */
/**
 * @property array<int, mixed>|null $keywords JSON, so it holds whatever was
 *                                            written into it rather than whatever we hoped for
 */
class AutodmAutomationVersion extends Model
{
    public const ANY_COMMENT = 'any_comment';

    public const KEYWORDS = 'keywords';

    protected $fillable = [
        'autodm_automation_id', 'version_number', 'trigger_type', 'keywords',
        'whole_word', 'public_reply_enabled', 'public_reply_text',
        'private_reply_enabled', 'private_reply_text', 'private_reply_url',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'whole_word' => 'boolean',
            'public_reply_enabled' => 'boolean',
            'private_reply_enabled' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(AutodmAutomation::class, 'autodm_automation_id');
    }

    /** @return list<string> */
    public function keywordList(): array
    {
        $words = [];

        foreach ((array) ($this->keywords ?? []) as $word) {
            if (is_scalar($word)) {
                $text = trim((string) $word);

                if ($text !== '') {
                    $words[] = $text;
                }
            }
        }

        return $words;
    }
}
