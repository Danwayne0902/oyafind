<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        // Ensure a system administrator exists so the LocationSeeder can
        // attribute its review records.  The account is created only when no
        // administrator is already present; production credentials must be
        // rotated immediately after the first deployment.
        $adminExists = User::query()->where('role', 'admin')->exists();

        if (! $adminExists) {
            User::query()->create([
                'name' => 'OyaFind Admin',
                'email' => 'admin@oyafind.ng',
                'password' => Hash::make(env('OYAFIND_SEED_ADMIN_PASSWORD', 'change-me-immediately')),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        $this->call(LocationSeeder::class);
        $this->call(SourceSeeder::class);
    }
}
