<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private function withAdminEnv(): void
    {
        config([
            'admin.email' => 'seed-admin@test.local',
            'admin.name' => 'Seed Admin',
            'admin.password' => 'seed-secret-123',
        ]);
    }

    public function test_admin_seeder_creates_admin_user_from_env(): void
    {
        $this->withAdminEnv();
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'seed-admin@test.local')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('Seed Admin', $admin->full_name);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('seed-secret-123', $admin->password));
    }

    public function test_admin_seeder_is_idempotent(): void
    {
        $this->withAdminEnv();

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'seed-admin@test.local')->count());
    }
}
