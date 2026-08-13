<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin account - the only role allowed to upload maestro footage.
        User::updateOrCreate(
            ['email' => 'admin@citra.test'],
            [
                'name'     => 'Admin CITRA',
                'password' => 'admin12345',   // hashed by the model cast
                'is_admin' => true,
                'avatar'   => 'default-avatar.png',
                'settings' => config('citra.default_settings'),
            ]
        );

        $this->call([
            AchievementSeeder::class,
            MaestroSeeder::class,
            SampleDataSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Akun demo:');
        $this->command?->line('  admin@citra.test / admin12345   (admin)');
        $this->command?->line('  siti@citra.test  / password123  (Master)');
        $this->command?->line('  ahmad@citra.test / password123  (Mahir)');
        $this->command?->line('  eko@citra.test   / password123  (Pemula)');
    }
}
