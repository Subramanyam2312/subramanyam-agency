<?php

/**
 * One form field, rendered from a spec. Every admin form is built from this, so
 * labels, error display, ARIA wiring and old-input repopulation behave identically
 * everywhere and are fixed in one place.
 *
 * Expected keys:
 *   name      string   input name (required)
 *   label     string   visible label (required)
 *   type      string   text|textarea|number|url|email|date|datetime|password|
 *                      select|checkbox|richtext|media|repeater|tags
 *   value     mixed    current value
 *   options   array    for select: value => label
 *   hint      string   help text under the field
 *   required  bool
 *   rows      int      textarea rows
 *   fields    array    for repeater: [key => label]
 *   media     array    for media: the currently attached media row
 *   attrs     string   extra raw attributes
 */

$name     = $name     ?? '';
$label    = $label    ?? '';
$type     = $type     ?? 'text';
$options  = $options  ?? [];
$hint     = $hint     ?? '';
$required = $required ?? false;
$rows     = $rows     ?? 4;
$fields   = $fields   ?? [];
$media    = $media    ?? null;
$attrs    = $attrs    ?? '';

// Old input wins after a failed validation round-trip so nothing typed is lost.
$value = old($name, $value ?? '');

$error       = error_for($name);
$id          = 'field-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
$describedBy = $error ? $id . '-error' : ($hint !== '' ? $id . '-hint' : '');
$aria        = ($error ? ' aria-invalid="true"' : '')
    . ($describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '');
?>
<div class="mb-5">
    <?php if ($type !== 'checkbox'): ?>
        <label for="<?= e($id) ?>" class="field-label">
            <?= e($label) ?><?= $required ? '<span class="text-danger" aria-hidden="true"> *</span>' : '' ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'textarea'): ?>
        <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) $rows ?>"
                  class="field-input"<?= $required ? ' required' : '' ?><?= $aria ?><?= $attrs ?>><?= e($value) ?></textarea>

    <?php elseif ($type === 'select'): ?>
        <select id="<?= e($id) ?>" name="<?= e($name) ?>" class="field-input"<?= $required ? ' required' : '' ?><?= $aria ?><?= $attrs ?>>
            <?php if (!$required): ?>
                <option value="">— none —</option>
            <?php endif; ?>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= e($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>>
                    <?= e($optionLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($type === 'checkbox'): ?>
        <label for="<?= e($id) ?>" class="flex items-start gap-3 cursor-pointer">
            <input type="hidden" name="<?= e($name) ?>" value="0">
            <input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"
                   class="mt-0.5 rounded border-field bg-raised text-accent focus:ring-accent"
                   <?= (string) $value === '1' ? 'checked' : '' ?><?= $attrs ?>>
            <span>
                <span class="text-sm font-medium text-body"><?= e($label) ?></span>
                <?php if ($hint !== ''): ?>
                    <span class="block text-sm text-muted"><?= e($hint) ?></span>
                <?php endif; ?>
            </span>
        </label>

    <?php elseif ($type === 'richtext'): ?>
        <!-- Quill writes into the hidden input on submit; see admin-editor.js -->
        <div class="rounded-lg border border-line bg-raised overflow-hidden">
            <div id="<?= e($id) ?>-editor" class="quill-editor" data-input="<?= e($id) ?>"><?= $value ?></div>
        </div>
        <input type="hidden" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>">

    <?php elseif ($type === 'media'): ?>
        <div class="flex items-start gap-4" data-media-field>
            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-lg border border-line bg-raised"
                 data-media-preview>
                <?php if ($media !== null): ?>
                    <img src="/<?= e(ltrim((string) $media['path'], '/')) ?>"
                         alt="<?= e($media['alt_text'] ?? '') ?>" class="h-full w-full object-cover">
                <?php endif; ?>
            </div>
            <div class="flex-1">
                <input type="hidden" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" data-media-input>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-ghost" data-media-choose>Choose image</button>
                    <button type="button" class="btn-ghost" data-media-clear
                            <?= $value === '' ? 'hidden' : '' ?>>Remove</button>
                </div>
                <?php if ($hint !== ''): ?>
                    <p class="field-hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($type === 'repeater'): ?>
        <?php
        $items = is_array($value) ? $value : json_column($value);
        ?>
        <div data-repeater data-name="<?= e($name) ?>">
            <div data-repeater-items class="space-y-3">
                <?php foreach (array_values($items) as $index => $item): ?>
                    <div class="rounded-lg border border-line bg-raised p-3" data-repeater-item>
                        <div class="grid gap-3 <?= count($fields) > 1 ? 'sm:grid-cols-2' : '' ?>">
                            <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
                                <label class="block">
                                    <span class="mb-1 block text-xs text-muted"><?= e($fieldLabel) ?></span>
                                    <input type="text"
                                           name="<?= e($name) ?>[<?= (int) $index ?>][<?= e($fieldKey) ?>]"
                                           value="<?= e(is_array($item) ? ($item[$fieldKey] ?? '') : $item) ?>"
                                           class="field-input">
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="mt-2 text-sm text-danger hover:underline" data-repeater-remove>
                            Remove
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <template data-repeater-template>
                <div class="rounded-lg border border-line bg-raised p-3" data-repeater-item>
                    <div class="grid gap-3 <?= count($fields) > 1 ? 'sm:grid-cols-2' : '' ?>">
                        <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
                            <label class="block">
                                <span class="mb-1 block text-xs text-muted"><?= e($fieldLabel) ?></span>
                                <input type="text" name="<?= e($name) ?>[__INDEX__][<?= e($fieldKey) ?>]"
                                       value="" class="field-input">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="mt-2 text-sm text-danger hover:underline" data-repeater-remove>
                        Remove
                    </button>
                </div>
            </template>

            <button type="button" class="btn-ghost mt-3" data-repeater-add>Add row</button>
        </div>

    <?php elseif ($type === 'tags'): ?>
        <input type="text" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
               class="field-input" placeholder="Separate with commas"<?= $aria ?><?= $attrs ?>>

    <?php else: ?>
        <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
               class="field-input"<?= $required ? ' required' : '' ?><?= $aria ?><?= $attrs ?>>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="field-error" id="<?= e($id) ?>-error"><?= e($error) ?></p>
    <?php elseif ($hint !== '' && !in_array($type, ['checkbox', 'media'], true)): ?>
        <p class="field-hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
    <?php endif; ?>
</div>
