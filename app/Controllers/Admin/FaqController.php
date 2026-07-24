<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\Faq;

final class FaqController extends ResourceController
{
    protected string $model = Faq::class;

    protected string $route = '/admin/faqs';

    protected string $views = 'admin/faqs';

    protected string $singular = 'FAQ';

    protected string $plural = 'FAQs';

    protected string $order = 'group_name ASC, sort_order ASC';

    protected bool $sortable = true;

    protected array $searchable = ['question', 'answer', 'group_name'];

    protected function columns(): array
    {
        return [
            ['key' => 'question', 'label' => 'Question', 'type' => 'primary'],
            ['key' => 'group_name', 'label' => 'Group'],
            ['key' => 'sort_order', 'label' => 'Order'],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'bool'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'question'   => 'required|max:300',
            'answer'     => 'required',
            'group_name' => 'required|max:80',
            'sort_order' => 'nullable|integer',
        ];
    }

    protected function fields(?array $record): array
    {
        return [
            ['name' => 'question', 'label' => 'Question', 'value' => $record['question'] ?? '', 'required' => true],
            ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rows' => 5,
             'value' => $record['answer'] ?? '', 'required' => true,
             'hint' => 'Plain text. This is emitted as FAQPage structured data, which does not permit markup.'],
            ['name' => 'group_name', 'label' => 'Group', 'value' => $record['group_name'] ?? 'General',
             'required' => true,
             'hint' => 'Existing groups: ' . (implode(', ', Faq::groupNames()) ?: 'none yet')],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $record['sort_order'] ?? 0],
            ['name' => 'is_active', 'label' => 'Show on the site', 'type' => 'checkbox',
             'value' => (string) ($record['is_active'] ?? 1)],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        return [
            // Deliberately not rich text: Google rejects FAQPage entries containing
            // markup, so allowing it here would silently break the structured data.
            'answer'     => trim((string) $request->input('answer')),
            'question'   => trim((string) $request->input('question')),
            'group_name' => trim((string) $request->input('group_name', 'General')),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active'  => (int) $request->input('is_active', 0),
        ];
    }
}
