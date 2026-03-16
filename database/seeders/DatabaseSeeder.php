<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed sample audit events
        AuditEvent::factory(20)->create();
        AuditEvent::factory(5)->highRisk()->create();
        AuditEvent::factory(3)->largeTransaction()->create();
    }
}
