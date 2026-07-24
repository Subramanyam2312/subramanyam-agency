<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Testimonial extends Model
{
    protected static string $table = 'testimonials';

    protected static bool $softDeletes = true;
}
