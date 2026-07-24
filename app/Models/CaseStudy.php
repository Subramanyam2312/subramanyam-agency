<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CaseStudy extends Model
{
    protected static string $table = 'case_studies';

    protected static bool $softDeletes = true;

    /** metrics: [{label,value}] results tiles. gallery: ordered media ids. */
    protected static array $jsonColumns = ['metrics', 'gallery'];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /**
     * @return array<string,string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_PUBLISHED => 'Published',
        ];
    }
}
