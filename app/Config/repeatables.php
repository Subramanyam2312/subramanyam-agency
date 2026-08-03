<?php

declare(strict_types=1);

/**
 * Repeatable card groups on the block-driven pages.
 *
 * Each entry describes a numbered set of page blocks — approach_step_1_title,
 * approach_step_2_title and so on — so the CMS can add another card by inserting
 * the next-numbered set, and the template renders however many exist.
 *
 * `fields` is the block suffix => [label, type]. The label is templated with :n
 * so the admin form reads "Step 3 title" rather than a raw key.
 */
return [
    'about' => [
        'approach_step' => [
            'label'  => 'Approach steps',
            'group'  => 'Approach',
            'noun'   => 'step',
            'base'   => 40,
            'fields' => [
                'title' => ['Step :n title', 'text'],
                'body'  => ['Step :n body', 'textarea'],
            ],
        ],
        'client' => [
            'label'  => 'Client cards',
            'group'  => 'Track record',
            'noun'   => 'client',
            'base'   => 80,
            'fields' => [
                'name' => ['Client :n name', 'text'],
                'meta' => ['Client :n sector', 'text'],
                'href' => ['Client :n link (optional)', 'url'],
                'body' => ['Client :n description', 'textarea'],
            ],
        ],
        'cred' => [
            'label'  => 'Credentials',
            'group'  => 'Credentials',
            'noun'   => 'credential',
            'base'   => 100,
            'fields' => [
                'title' => ['Credential :n title', 'text'],
                'body'  => ['Credential :n body', 'textarea'],
            ],
        ],
        'faq' => [
            'label'  => 'FAQ entries',
            'group'  => 'FAQ',
            'noun'   => 'question',
            'base'   => 130,
            'fields' => [
                'q' => ['Question :n', 'text'],
                'a' => ['Answer :n', 'textarea'],
            ],
        ],
    ],

    'contact' => [
        'step' => [
            'label'  => 'What happens next',
            'group'  => 'Next',
            'noun'   => 'step',
            'base'   => 40,
            'fields' => [
                'title' => ['Step :n title', 'text'],
                'body'  => ['Step :n body', 'textarea'],
            ],
        ],
        'faq' => [
            'label'  => 'FAQ entries',
            'group'  => 'FAQ',
            'noun'   => 'question',
            'base'   => 60,
            'fields' => [
                'q' => ['Question :n', 'text'],
                'a' => ['Answer :n', 'textarea'],
            ],
        ],
    ],

    /** Hard ceiling, so a stuck click cannot generate blocks without end. */
    'max' => 12,
];
