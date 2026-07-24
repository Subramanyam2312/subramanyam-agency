<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Service extends Model
{
    protected static string $table = 'services';

    protected static bool $softDeletes = true;

    /** Repeatable bullet lists, edited as a unit and never queried across rows. */
    protected static array $jsonColumns = ['includes', 'process', 'deliverables'];

    /**
     * @return array<int,string> id => title, for the contact form and select inputs
     */
    public static function options(bool $activeOnly = true): array
    {
        $conditions = $activeOnly ? ['is_active' => 1] : [];
        $options    = [];

        foreach (self::all($conditions, 'sort_order ASC, title ASC') as $row) {
            $options[(int) $row['id']] = (string) $row['title'];
        }

        return $options;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function faqs(int $serviceId): array
    {
        return Database::select(
            'SELECT * FROM `service_faqs` WHERE `service_id` = :id ORDER BY `sort_order`, `id`',
            [':id' => $serviceId]
        );
    }

    /**
     * Replaces a service's FAQ set in one pass.
     *
     * Delete-and-reinsert rather than diffing: the rows carry no external references
     * and the set is always small, so a diff would be more code for no benefit.
     *
     * @param array<int,array{question:string,answer:string}> $faqs
     */
    public static function syncFaqs(int $serviceId, array $faqs): void
    {
        Database::delete('service_faqs', ['service_id' => $serviceId]);

        $order = 0;

        foreach ($faqs as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer   = trim((string) ($faq['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            Database::insert('service_faqs', [
                'service_id' => $serviceId,
                'question'   => $question,
                'answer'     => $answer,
                'sort_order' => $order++,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
