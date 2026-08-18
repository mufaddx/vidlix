<?php

namespace App\Support;

final class DefaultContactFormSchema
{
    /**
     * @return array{title: string, description: string, success_message: string, fields: list<array<string, mixed>>}
     */
    public static function make(): array
    {
        return [
            'title' => "Let's work together",
            'description' => 'Tell me about your campaign. Required fields cannot be removed.',
            'success_message' => 'Inquiry received. The creator will reply by email.',
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'locked' => true],
                ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'locked' => true],
                ['key' => 'subject', 'type' => 'text', 'label' => 'Subject', 'required' => true, 'locked' => true],
                ['key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true, 'locked' => true],
                ['key' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => false, 'locked' => false],
                ['key' => 'budget', 'type' => 'text', 'label' => 'Budget', 'required' => false, 'locked' => false],
            ],
        ];
    }
}
