<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->truncate('user_roles', 'role_permissions', 'users', 'permissions', 'roles');
        $this->call(RbacSeeder::class);
    }
}
