<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use App\Models\Square;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

trait CreatesTestData
{
    protected function createTestUser(string $alias = 'testuser', string $status = 'enabled'): User
    {
        return User::create([
            'alias'   => $alias,
            'email'   => $alias.'@test.local',
            'status'  => $status,
            'pw'      => Hash::make('password123'),
            'created' => now(),
        ]);
    }

    protected function createAdminUser(string $alias = 'testadmin'): User
    {
        return $this->createTestUser($alias, 'admin');
    }

    protected function deleteTestUser(string $alias): void
    {
        User::where('alias', $alias)->delete();
    }

    protected function firstSquare(): ?Square
    {
        return Square::orderBy('priority')->orderBy('sid')->first();
    }
}
