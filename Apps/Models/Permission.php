<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

final class Permission extends Model
{
    protected static string $table = 'permissions';

    protected static array $fillable = [
        'name',
        'display_name',
    ];
}
