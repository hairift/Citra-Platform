<?php

/*
|--------------------------------------------------------------------------
| CITRA Platform Configuration
|--------------------------------------------------------------------------
|
| Domain knowledge for Tari Topeng Cirebon plus the wiring to the Flask AI
| backend. Keeping the five characters, their gerakan and the scoring weights
| in one place means the controllers, the Blade views and the seeders can
| never drift apart.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | AI backend (Flask + MediaPipe + TensorFlow)
    |----------------------------------------------------------------------
    */
    'ai' => [
        'url'     => env('CITRA_AI_URL', 'http://localhost:5000'),
        'ws_url'  => env('CITRA_AI_WS_URL', 'http://localhost:5000'),
        'timeout' => (int) env('CITRA_AI_TIMEOUT', 8),

        // Must match JWT_SECRET_KEY in backend/.env. Laravel mints a
        // short-lived token with this key so an already-signed-in user can talk
        // to the Flask service without logging in a second time.
        'jwt_secret'  => env('CITRA_AI_JWT_SECRET', 'citra-jwt-secret-production-2024'),
        'jwt_ttl'     => (int) env('CITRA_AI_JWT_TTL', 7200),   // seconds
    ],

    /*
    |----------------------------------------------------------------------
    | Practice defaults
    |----------------------------------------------------------------------
    */
    'practice' => [
        'default_karakter'    => env('CITRA_DEFAULT_KARAKTER', 'klana'),
        'target_fps'          => (int) env('CITRA_TARGET_FPS', 12),
        'min_session_seconds' => (int) env('CITRA_MIN_SESSION_SECONDS', 10),
    ],

    /*
    |----------------------------------------------------------------------
    | Scoring
    |----------------------------------------------------------------------
    | Global fallback weights for Wiraga / Wirama / Wirasa. Each character
    | can override these (see `karakters.*.weights`) because a Klana dancer
    | is judged more on dynamic movement than a Panji dancer is.
    */
    'weights' => [
        'wiraga' => 0.45,
        'wirama' => 0.30,
        'wirasa' => 0.25,
    ],

    'grades' => [
        ['min' => 95, 'grade' => 'A+', 'label' => 'Istimewa'],
        ['min' => 90, 'grade' => 'A',  'label' => 'Sangat Baik'],
        ['min' => 85, 'grade' => 'A-', 'label' => 'Sangat Baik'],
        ['min' => 80, 'grade' => 'B+', 'label' => 'Baik'],
        ['min' => 75, 'grade' => 'B',  'label' => 'Baik'],
        ['min' => 70, 'grade' => 'B-', 'label' => 'Cukup Baik'],
        ['min' => 65, 'grade' => 'C+', 'label' => 'Cukup'],
        ['min' => 60, 'grade' => 'C',  'label' => 'Cukup'],
        ['min' => 55, 'grade' => 'C-', 'label' => 'Kurang'],
        ['min' => 50, 'grade' => 'D',  'label' => 'Kurang'],
        ['min' => 0,  'grade' => 'E',  'label' => 'Perlu Latihan'],
    ],

    /*
    |----------------------------------------------------------------------
    | User levels
    |----------------------------------------------------------------------
    | Evaluated top-down; the first entry whose thresholds are all met wins.
    */
    'levels' => [
        ['name' => 'Master',   'min_sessions' => 50, 'min_score' => 6000],
        ['name' => 'Mahir',    'min_sessions' => 30, 'min_score' => 3000],
        ['name' => 'Menengah', 'min_sessions' => 15, 'min_score' => 1000],
        ['name' => 'Dasar',    'min_sessions' => 5,  'min_score' => 0],
        ['name' => 'Pemula',   'min_sessions' => 0,  'min_score' => 0],
    ],

    /*
    |----------------------------------------------------------------------
    | The five characters of Tari Topeng Cirebon
    |----------------------------------------------------------------------
    */
    'karakters' => [

        'panji' => [
            'name'        => 'Panji',
            'icon'        => '🎭',
            'color'       => '#60A5FA',
            'description' => 'Karakter halus dan lembut, melambangkan kesucian jiwa manusia yang baru lahir.',
            'filosofi'    => 'Panji menggambarkan bayi yang baru lahir - suci, tenang, dan belum tersentuh nafsu duniawi. Gerakannya paling halus di antara kelima topeng.',
            'difficulty'  => 'Mudah',
            'tempo'       => [60, 80],
            'weights'     => ['wiraga' => 0.40, 'wirama' => 0.30, 'wirasa' => 0.30],
            'ekspresi'    => ['tenang', 'khusyuk', 'halus'],
            'gerakan'     => [
                ['slug' => 'sembahan',        'name' => 'Sembahan Awal',   'desc' => 'Gerakan penghormatan pembuka',   'hitungan' => 8, 'difficulty' => 'mudah'],
                ['slug' => 'nindak',          'name' => 'Nindak',          'desc' => 'Langkah dasar yang lemah lembut', 'hitungan' => 4, 'difficulty' => 'mudah'],
                ['slug' => 'tanjak',          'name' => 'Tanjak',          'desc' => 'Posisi dasar berdiri',           'hitungan' => 4, 'difficulty' => 'mudah'],
                ['slug' => 'ngigel',          'name' => 'Ngigel',          'desc' => 'Gerakan tangan berputar luwes',  'hitungan' => 8, 'difficulty' => 'menengah'],
                ['slug' => 'nyawang',         'name' => 'Nyawang',         'desc' => 'Arah pandangan mata',            'hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'capang',          'name' => 'Capang',          'desc' => 'Gerakan kaki menyilang',         'hitungan' => 6, 'difficulty' => 'menengah'],
                ['slug' => 'klepat',          'name' => 'Klepat',          'desc' => 'Putaran badan',                  'hitungan' => 8, 'difficulty' => 'sulit'],
                ['slug' => 'sembahan_akhir',  'name' => 'Sembahan Akhir',  'desc' => 'Gerakan penutup',                'hitungan' => 8, 'difficulty' => 'mudah'],
            ],
        ],

        'samba' => [
            'name'        => 'Samba',
            'icon'        => '👹',
            'color'       => '#34D399',
            'description' => 'Karakter jenaka dan lincah, melambangkan masa kanak-kanak yang penuh keceriaan.',
            'filosofi'    => 'Samba (atau Pamindo) menggambarkan anak-anak yang lincah, jenaka, dan penuh rasa ingin tahu. Gerakannya cepat namun tetap terkontrol.',
            'difficulty'  => 'Mudah',
            'tempo'       => [80, 100],
            'weights'     => ['wiraga' => 0.50, 'wirama' => 0.25, 'wirasa' => 0.25],
            'ekspresi'    => ['ceria', 'jenaka', 'lincah'],
            'gerakan'     => [
                ['slug' => 'sembahan_samba', 'name' => 'Sembahan Samba', 'desc' => 'Pembukaan yang riang',     'hitungan' => 6, 'difficulty' => 'mudah'],
                ['slug' => 'trecet',         'name' => 'Trecet',         'desc' => 'Langkah kecil cepat',      'hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'ngalaga',        'name' => 'Ngalaga',        'desc' => 'Pose gagah bercanda',      'hitungan' => 8, 'difficulty' => 'menengah'],
                ['slug' => 'godeg',          'name' => 'Godeg',          'desc' => 'Gerakan kepala menoleh',   'hitungan' => 4, 'difficulty' => 'mudah'],
                ['slug' => 'mincid',         'name' => 'Mincid',         'desc' => 'Loncatan ringan',          'hitungan' => 6, 'difficulty' => 'sulit'],
            ],
        ],

        'rumyang' => [
            'name'        => 'Rumyang',
            'icon'        => '🌸',
            'color'       => '#F472B6',
            'description' => 'Karakter anggun dan dewasa, melambangkan masa remaja menuju kedewasaan.',
            'filosofi'    => 'Rumyang berasal dari kata "arum" (harum) dan "yang" (hyang). Melambangkan manusia yang mulai mengenal jati diri, anggun dan mengalir.',
            'difficulty'  => 'Menengah',
            'tempo'       => [70, 90],
            'weights'     => ['wiraga' => 0.35, 'wirama' => 0.35, 'wirasa' => 0.30],
            'ekspresi'    => ['anggun', 'lembut', 'mengalir'],
            'gerakan'     => [
                ['slug' => 'sembahan_rumyang', 'name' => 'Sembahan Rumyang', 'desc' => 'Pembukaan anggun',        'hitungan' => 8, 'difficulty' => 'mudah'],
                ['slug' => 'keupat',           'name' => 'Keupat',           'desc' => 'Ayunan tangan anggun',    'hitungan' => 6, 'difficulty' => 'menengah'],
                ['slug' => 'obah_bahu',        'name' => 'Obah Bahu',        'desc' => 'Gerakan bahu bergelombang','hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'geol',             'name' => 'Geol',             'desc' => 'Gerakan pinggul',         'hitungan' => 8, 'difficulty' => 'sulit'],
            ],
        ],

        'tumenggung' => [
            'name'        => 'Tumenggung',
            'icon'        => '⚔️',
            'color'       => '#FBBF24',
            'description' => 'Karakter gagah berwibawa, melambangkan manusia dewasa yang bertanggung jawab.',
            'filosofi'    => 'Tumenggung menggambarkan sosok pemimpin - tegas, berwibawa, dan penuh tanggung jawab. Kuda-kudanya kuat dan gerakannya mantap.',
            'difficulty'  => 'Menengah',
            'tempo'       => [90, 110],
            'weights'     => ['wiraga' => 0.50, 'wirama' => 0.30, 'wirasa' => 0.20],
            'ekspresi'    => ['tegas', 'berwibawa', 'mantap'],
            'gerakan'     => [
                ['slug' => 'sembahan_tumenggung', 'name' => 'Sembahan Tumenggung', 'desc' => 'Pembukaan tegas',   'hitungan' => 6, 'difficulty' => 'mudah'],
                ['slug' => 'adeg_adeg',           'name' => 'Adeg-adeg',           'desc' => 'Kuda-kuda kokoh',   'hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'jangkung_ilo',        'name' => 'Jangkung Ilo',        'desc' => 'Langkah gagah',     'hitungan' => 8, 'difficulty' => 'menengah'],
                ['slug' => 'ngabret',             'name' => 'Ngabret',             'desc' => 'Gerakan tegas',     'hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'nyeredet',            'name' => 'Nyeredet',            'desc' => 'Langkah menyeret',  'hitungan' => 6, 'difficulty' => 'sulit'],
            ],
        ],

        'klana' => [
            'name'        => 'Klana',
            'icon'        => '👺',
            'color'       => '#E85A20',
            'description' => 'Karakter dinamis dan angkara, melambangkan manusia yang dikuasai hawa nafsu.',
            'filosofi'    => 'Klana (Rahwana) menggambarkan puncak angkara murka - serakah, ambisius, dan penuh amarah. Gerakannya paling dinamis, kuat, dan menghentak.',
            'difficulty'  => 'Sulit',
            'tempo'       => [100, 130],
            'weights'     => ['wiraga' => 0.45, 'wirama' => 0.35, 'wirasa' => 0.20],
            'ekspresi'    => ['garang', 'angkuh', 'menghentak'],
            'gerakan'     => [
                ['slug' => 'sembahan_klana', 'name' => 'Sembahan Klana', 'desc' => 'Pembukaan angkuh',       'hitungan' => 8, 'difficulty' => 'menengah'],
                ['slug' => 'tanjak_klana',   'name' => 'Tanjak Klana',   'desc' => 'Kuda-kuda lebar & gagah','hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'ngabret_klana',  'name' => 'Ngabret',        'desc' => 'Sentakan keras',         'hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'gangsingan',     'name' => 'Gangsingan',     'desc' => 'Putaran kuat seperti gasing', 'hitungan' => 8, 'difficulty' => 'sulit'],
                ['slug' => 'bantingan',      'name' => 'Bantingan',      'desc' => 'Gerakan menghempas',     'hitungan' => 6, 'difficulty' => 'sulit'],
                ['slug' => 'ngepret',        'name' => 'Ngepret',        'desc' => 'Sentakan selendang',     'hitungan' => 4, 'difficulty' => 'menengah'],
                ['slug' => 'ngebrag',        'name' => 'Ngebrag',        'desc' => 'Hentakan kaki ke lantai','hitungan' => 6, 'difficulty' => 'sulit'],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Achievements
    |----------------------------------------------------------------------
    | `rule` is resolved by App\Services\AchievementService.
    */
    'achievements' => [
        ['slug' => 'first_step',      'name' => 'Langkah Pertama',   'icon' => '🌟', 'desc' => 'Selesaikan sesi latihan pertama',          'rule' => 'sessions', 'value' => 1],
        ['slug' => 'ten_sessions',    'name' => '10 Latihan',        'icon' => '🎯', 'desc' => 'Selesaikan 10 sesi latihan',               'rule' => 'sessions', 'value' => 10],
        ['slug' => 'fifty_sessions',  'name' => '50 Latihan',        'icon' => '🏅', 'desc' => 'Selesaikan 50 sesi latihan',               'rule' => 'sessions', 'value' => 50],
        ['slug' => 'streak_3',        'name' => '3 Hari Berturut',   'icon' => '🔥', 'desc' => 'Berlatih 3 hari berturut-turut',           'rule' => 'streak',   'value' => 3],
        ['slug' => 'streak_7',        'name' => 'Seminggu Penuh',    'icon' => '⚡', 'desc' => 'Berlatih 7 hari berturut-turut',           'rule' => 'streak',   'value' => 7],
        ['slug' => 'score_80',        'name' => 'Skor 80',           'icon' => '💪', 'desc' => 'Raih skor 80 dalam satu sesi',             'rule' => 'best_score', 'value' => 80],
        ['slug' => 'score_90',        'name' => 'Nyaris Sempurna',   'icon' => '💯', 'desc' => 'Raih skor 90 dalam satu sesi',             'rule' => 'best_score', 'value' => 90],
        ['slug' => 'master_panji',    'name' => 'Master Panji',      'icon' => '🎭', 'desc' => 'Rata-rata 85+ pada karakter Panji',        'rule' => 'karakter_avg', 'value' => 85, 'karakter' => 'panji'],
        ['slug' => 'master_klana',    'name' => 'Master Klana',      'icon' => '👺', 'desc' => 'Rata-rata 85+ pada karakter Klana',        'rule' => 'karakter_avg', 'value' => 85, 'karakter' => 'klana'],
        ['slug' => 'all_karakter',    'name' => 'Penjelajah Topeng', 'icon' => '🗺️', 'desc' => 'Coba kelima karakter topeng',              'rule' => 'karakter_variety', 'value' => 5],
        ['slug' => 'top_10',          'name' => 'Top 10',            'icon' => '👑', 'desc' => 'Masuk 10 besar leaderboard',               'rule' => 'rank',     'value' => 10],
        ['slug' => 'hour_practice',   'name' => 'Satu Jam Berlatih', 'icon' => '⏱️', 'desc' => 'Total 60 menit waktu latihan',             'rule' => 'minutes',  'value' => 60],
    ],

    /*
    |----------------------------------------------------------------------
    | Default user settings
    |----------------------------------------------------------------------
    */
    'default_settings' => [
        'camera'            => 'default',
        'videoQuality'      => 'medium',
        'showSkeleton'      => true,
        'showLandmarks'     => true,
        'mirrorMode'        => true,
        'musicVolume'       => 70,
        'feedbackVolume'    => 50,
        'soundFeedback'     => true,
        'difficulty'        => 'medium',
        'countdown'         => 3,
        'showMaestro'       => true,
        'autoSave'          => true,
        'reminderEnabled'   => false,
        'leaderboardNotify' => true,
        'achievementNotify' => true,
    ],
];
