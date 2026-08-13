<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * Materialise the achievements declared in config/citra.php.
 *
 * Idempotent: safe to re-run after adding a new achievement to the config.
 */
class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('citra.achievements', []) as $index => $item) {
            Achievement::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name'        => $item['name'],
                    'icon'        => $item['icon'],
                    'description' => $item['desc'],
                    'rule'        => $item['rule'],
                    'threshold'   => $item['value'],
                    'karakter'    => $item['karakter'] ?? null,
                    'order_index' => $index,
                ]
            );
        }

        $this->command?->info('Achievements seeded: '.Achievement::count());
    }
}
