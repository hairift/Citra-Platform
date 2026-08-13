<?php

namespace Database\Seeders;

use App\Models\MaestroReference;
use Illuminate\Database\Seeder;

/**
 * Create a reference row for every gerakan defined in config/citra.php.
 *
 * These start unpublished-without-video; `php artisan citra:sync-dataset`
 * later attaches the real footage and extracted keyframes to the ones that
 * have them. Keeping the curriculum here means the tutorial page always has a
 * complete syllabus even before any video has been processed.
 */
class MaestroSeeder extends Seeder
{
    public function run(): void
    {
        $instructions = $this->instructions();
        $tips = $this->tips();

        foreach (config('citra.karakters', []) as $karakterSlug => $karakter) {
            foreach ($karakter['gerakan'] as $index => $gerakan) {
                MaestroReference::updateOrCreate(
                    [
                        'karakter'     => $karakterSlug,
                        'gerakan_slug' => $gerakan['slug'],
                    ],
                    [
                        'slug'         => $karakterSlug.'-'.$gerakan['slug'],
                        'gerakan_name' => $gerakan['name'],
                        'role'         => 'maestro',
                        'description'  => $gerakan['desc'].' pada karakter '.$karakter['name'].'.',
                        'difficulty'   => $gerakan['difficulty'],
                        'hitungan'     => $gerakan['hitungan'],
                        'order_index'  => $index,
                        'instructions' => $instructions[$gerakan['slug']] ?? $this->genericInstructions($karakter, $gerakan),
                        'tips'         => $tips[$gerakan['slug']] ?? $this->genericTips($karakter, $gerakan),
                        'is_published' => true,
                    ]
                );
            }
        }

        $this->command?->info('Maestro references seeded: '.MaestroReference::count());
    }

    /**
     * Hand-written breakdowns for the gerakan a beginner meets first.
     * Everything else falls back to the generated structure below.
     */
    private function instructions(): array
    {
        return [
            'sembahan' => [
                ['title' => 'Posisi Tubuh', 'points' => [
                    'Duduk bersimpuh dengan kedua kaki terlipat rapi ke belakang',
                    'Punggung tegak namun tidak kaku, bahu rileks',
                    'Kepala sedikit menunduk sebagai tanda hormat',
                ]],
                ['title' => 'Posisi Tangan', 'points' => [
                    'Kedua telapak tangan disatukan di depan dada',
                    'Jari-jari mengarah ke atas, ibu jari menyentuh dada',
                    'Siku rileks dan tidak terangkat tinggi',
                ]],
                ['title' => 'Ekspresi', 'points' => [
                    'Mata setengah tertutup, pandangan ke bawah',
                    'Wajah tenang dan khusyuk',
                    'Napas perlahan dan teratur',
                ]],
            ],
            'tanjak_klana' => [
                ['title' => 'Posisi Kaki', 'points' => [
                    'Buka kaki lebih lebar dari bahu - Klana harus terlihat gagah dan menguasai panggung',
                    'Tekuk kedua lutut membentuk kuda-kuda rendah yang kokoh',
                    'Telapak kaki membuka ke luar sekitar 45 derajat',
                ]],
                ['title' => 'Posisi Badan', 'points' => [
                    'Dada dibusungkan, bahu ditarik ke belakang dan dibuka lebar',
                    'Berat badan merata, pusat gravitasi rendah dan stabil',
                    'Punggung tetap tegak, jangan membungkuk',
                ]],
                ['title' => 'Posisi Tangan', 'points' => [
                    'Kedua lengan diangkat setinggi bahu dengan siku ditekuk tegas',
                    'Jari-jari mengembang penuh (ngruji), tidak lemas',
                    'Pergelangan tangan aktif menekuk ke atas',
                ]],
            ],
            'gangsingan' => [
                ['title' => 'Persiapan', 'points' => [
                    'Mulai dari posisi tanjak dengan kuda-kuda kuat',
                    'Pandangan mengunci satu titik sebelum berputar',
                ]],
                ['title' => 'Putaran', 'points' => [
                    'Putar badan dengan poros pada kaki tumpu, bukan pada pinggang',
                    'Jaga tinggi badan tetap konstan - jangan naik-turun saat berputar',
                    'Lengan tetap terbuka lebar untuk menjaga keseimbangan',
                ]],
                ['title' => 'Penyelesaian', 'points' => [
                    'Berhenti tepat pada hitungan, tanpa langkah koreksi',
                    'Kembali ke tanjak dengan tegas dan mantap',
                ]],
            ],
        ];
    }

    private function tips(): array
    {
        return [
            'sembahan' => [
                ['icon' => '⏱️', 'title' => 'Durasi', 'text' => 'Tahan posisi sembahan selama 4-8 hitungan gamelan'],
                ['icon' => '🎵', 'title' => 'Irama', 'text' => 'Mulai bersamaan dengan bunyi gong pembuka'],
                ['icon' => '👁️', 'title' => 'Fokus', 'text' => 'Jaga konsentrasi dan kekhusyukan selama gerakan'],
                ['icon' => '✨', 'title' => 'Kualitas', 'text' => 'Gerakan halus dan mengalir, tidak patah-patah'],
            ],
            'tanjak_klana' => [
                ['icon' => '💪', 'title' => 'Kekuatan', 'text' => 'Gunakan otot paha untuk menahan kuda-kuda rendah'],
                ['icon' => '📐', 'title' => 'Sudut Lutut', 'text' => 'Target sudut lutut sekitar 120-140 derajat'],
                ['icon' => '🔥', 'title' => 'Karakter', 'text' => 'Klana harus terlihat angkuh - jangan ragu membuka badan'],
                ['icon' => '⚖️', 'title' => 'Stabilitas', 'text' => 'Jaga berat badan merata, jangan condong ke satu sisi'],
            ],
        ];
    }

    private function genericInstructions(array $karakter, array $gerakan): array
    {
        return [
            ['title' => 'Petunjuk Umum', 'points' => [
                'Perhatikan postur tubuh sesuai pakem karakter '.$karakter['name'],
                'Ikuti irama gamelan dengan tepat ('.$gerakan['hitungan'].' hitungan)',
                'Jaga keseimbangan dan kontrol setiap transisi gerakan',
            ]],
            ['title' => 'Karakter '.$karakter['name'], 'points' => [
                $karakter['description'],
                'Ekspresi yang diharapkan: '.implode(', ', $karakter['ekspresi']),
            ]],
        ];
    }

    private function genericTips(array $karakter, array $gerakan): array
    {
        return [
            ['icon' => '⏱️', 'title' => 'Durasi', 'text' => $gerakan['hitungan'].' hitungan gamelan'],
            ['icon' => '🎭', 'title' => 'Karakter', 'text' => $karakter['name'].' - '.$karakter['difficulty']],
            ['icon' => '🎵', 'title' => 'Tempo', 'text' => $karakter['tempo'][0].'-'.$karakter['tempo'][1].' BPM'],
        ];
    }
}
