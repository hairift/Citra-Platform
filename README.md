# CITRA Platform

**Pengembangan Platform CITRA Berbasis Deep Learning dan Motion Analysis untuk Pelestarian Tari Topeng Cirebon**

Platform pembelajaran tari tradisional dengan penilaian gerakan real-time berbasis
computer vision dan deep learning.

---

## DAFTAR ISI

1. [Fitur Utama](#fitur-utama)
2. [Arsitektur](#arsitektur)
3. [Struktur Project](#struktur-project)
4. [Instalasi (Laragon + Windows)](#instalasi-laragon--windows)
5. [Menjalankan Aplikasi](#menjalankan-aplikasi)
6. [Pipeline Dataset & Model AI](#pipeline-dataset--model-ai)
7. [Akun Demo](#akun-demo)
8. [Referensi API](#referensi-api)
9. [Troubleshooting](#troubleshooting)

---

## FITUR UTAMA

### Penilaian Tari (Wiraga · Wirama · Wirasa)

| Aspek | Yang dinilai | Metode |
|---|---|---|
| **Wiraga** | Ketepatan gerak & bentuk tubuh | 12 sudut sendi + kemiripan postur ternormalisasi + DTW sekuens |
| **Wirama** | Keselarasan dengan irama gamelan | Deteksi ketukan (gong/kenong) + ketepatan aksen gerak + kecocokan tempo |
| **Wirasa** | Penghayatan & pembawaan karakter | Sikap kepala, bukaan badan, kestabilan (topeng menutup wajah) |

### Mode Latihan
- Deteksi pose real-time di browser (MediaPipe Holistic, 33 titik sendi + 42 titik tangan)
- Perbandingan langsung dengan pose maestro pada waktu yang sama
- Mode banding (split-screen) dengan video maestro
- Feedback per-sendi dalam bahasa Indonesia (`"Perbaiki tekukan siku kiri - selisih 24° dari maestro"`)
- Hitung mundur, rekam latihan (WebM), screenshot pose, PiP, layar penuh
- Skor tersimpan lengkap: skor per detik, timeline feedback, rincian 12 sendi

### Dashboard & Progres
- Grafik progres 7 hari dari data sesi nyata
- Penguasaan 5 karakter (gabungan kualitas skor, pengalaman, dan cakupan gerakan)
- Streak harian, 12 pencapaian, notifikasi in-app
- Leaderboard per karakter dan per periode (minggu/bulan/sepanjang waktu)
- Riwayat dengan filter karakter/periode/urutan + halaman detail sesi

### Tutorial & Dataset
- Kurikulum lengkap 5 karakter, 29 gerakan
- Pemutar video maestro + galeri frame beranotasi titik sendi
- Halaman **Dataset AI**: statistik ekstraksi, timeline segmentasi gerakan, galeri frame

### Deep Learning
| Model | Arsitektur | Kegunaan |
|---|---|---|
| Gerakan Classifier | Bi-LSTM (96→64) | Mengenali fase gerakan dari jendela 24 frame |
| Pose Autoencoder | LSTM Autoencoder (latent 32) | Skor "kemiripan gaya maestro" tanpa label |
| Tempo Regressor | 1D-CNN | Estimasi kecepatan gerak untuk umpan balik tempo |

---

## ARSITEKTUR

```
┌──────────────────────┐         ┌──────────────────────┐
│   Laravel 12 (PHP)   │         │   Flask (Python)     │
│   :8000              │◄───────►│   :5000              │
│                      │  HTTP + │                      │
│  • UI (Blade)        │ Socket  │  • MediaPipe pose    │
│  • Auth & sesi       │   .IO   │  • Scoring engine    │
│  • Leaderboard       │         │  • TensorFlow models │
│  • Tutorial/Dataset  │         │  • librosa (gamelan) │
└──────────┬───────────┘         └──────────┬───────────┘
           │                                │
           └───────────► MySQL ◄────────────┘
                       citra_db
                     (satu database)
```

**Penting:** kedua sisi memakai **satu database MySQL yang sama**. Skema dimiliki
oleh migrasi Laravel; Flask membaca/menulis tabel yang sama lewat SQLAlchemy dan
**tidak pernah** memanggil `db.create_all()`.

**Mode offline:** jika server Flask tidak berjalan, Mode Latihan tetap berfungsi —
deteksi pose dan penilaian sudut sendi berjalan di browser memakai keyframes yang
disajikan Laravel. Server Flask menambahkan lapisan deep learning di atasnya.

---

## STRUKTUR PROJECT

```
citra-platform/
├── backend/                          # Flask + AI
│   ├── ai/
│   │   ├── pose_utils.py             # Geometri pose (dipakai bersama train & runtime)
│   │   ├── pose_estimator.py         # MediaPipe wrapper + perbandingan pose
│   │   ├── rhythm_analyzer.py        # Deteksi ketukan gamelan
│   │   ├── evaluation_engine.py      # Scoring per-sesi (thread-safe)
│   │   ├── dataset_builder.py        # Ekstraksi dataset dari video
│   │   └── deep_models.py            # Bi-LSTM, Autoencoder, Tempo CNN
│   ├── maestro_data/
│   │   ├── raw/                      # Video sumber (asli, tidak untuk web)
│   │   └── dataset/klana/
│   │       ├── keypoints/            # *_keypoints.json, *_keyframes.json,
│   │       │                         # *_segments.json, *_features.npy
│   │       └── frames/               # Frame beranotasi titik sendi
│   ├── models/                       # Model terlatih (.keras) + metadata
│   ├── app.py                        # Flask + Socket.IO
│   ├── models.py                     # SQLAlchemy (mirror skema Laravel)
│   ├── config.py
│   ├── build_dataset.py              # CLI ekstraksi dataset
│   ├── train_models.py               # CLI pelatihan model
│   └── requirements.txt
│
├── frontend/                         # Laravel 12
│   ├── app/
│   │   ├── Console/Commands/SyncDataset.php
│   │   ├── Http/Controllers/         # Dashboard, Practice, Tutorial, ...
│   │   ├── Models/                   # User, PracticeSession, MaestroReference, ...
│   │   └── Services/                 # StatsService, AchievementService, AiBackendService
│   ├── config/citra.php              # ★ Sumber kebenaran: karakter, gerakan, bobot
│   ├── database/migrations/
│   ├── database/seeders/
│   ├── public/
│   │   ├── videos/maestro/           # Video web 720p + poster
│   │   └── pose-frames/klana/frames/ # Frame beranotasi (publik)
│   ├── resources/views/
│   │   ├── layouts/app.blade.php     # Shell aplikasi
│   │   └── partials/                 # styles, sidebar, topbar, flash, scripts
│   └── routes/{web,api}.php
│
├── database/
│   ├── citra_db_full.sql             # Dump lengkap (skema + data) → phpMyAdmin
│   └── citra_db_schema.sql           # Skema saja
│
└── README.md
```

---

## INSTALASI (LARAGON + WINDOWS)

### Prasyarat
- **Laragon** (Apache + MySQL + phpMyAdmin)
- **PHP 8.2+** dengan ekstensi `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`
- **Composer**
- **Python 3.10 / 3.11** (TensorFlow 2.15 belum mendukung 3.12+)
- **Node.js 18+** (opsional — hanya untuk Vite/Tailwind)
- **FFmpeg** (opsional — untuk transcoding video maestro baru)

---

### 1. Konfigurasi MySQL Laragon

> **Catatan penting:** jika di komputer Anda sudah ada service Windows **MySQL80**
> (installer MySQL terpisah), service itu memakai port **3306** dan MySQL Laragon
> tidak bisa jalan. Pada instalasi ini MySQL Laragon dipindah ke port **3307**
> supaya keduanya bisa hidup berdampingan.

**Cek port yang dipakai:**
```powershell
netstat -ano | findstr :3306
Get-Service | Where-Object { $_.Name -match 'mysql' }
```

**Konfigurasi yang dipakai project ini** (sudah diterapkan):

`C:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini`
```ini
[client]
port=3307

[mysqld]
port=3307
```

`C:\laragon\etc\apps\phpMyAdmin\config.inc.php`
```php
$cfg['Servers'][1]['host'] = '127.0.0.1';   // bukan 'localhost' — agar port dihormati
$cfg['Servers'][1]['port'] = 3307;          // Laragon (CITRA)
$cfg['Servers'][2]['port'] = 3306;          // MySQL80 service
```

Lalu **Start All** di Laragon.

**Jika port 3306 kosong di komputer Anda,** cukup pakai 3306 dan sesuaikan
`DB_PORT` di kedua file `.env`.

---

### 2. Buat Database

**Via phpMyAdmin** (`http://localhost/phpmyadmin`):
1. Pilih server *Laragon MySQL (CITRA) :3307*
2. **New** → nama `citra_db` → collation `utf8mb4_unicode_ci` → **Create**

**Atau via terminal:**
```powershell
& "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root -h 127.0.0.1 -P 3307 --protocol=TCP `
  -e "CREATE DATABASE IF NOT EXISTS citra_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

### 3. Setup Frontend (Laravel)

```powershell
cd frontend
composer install

# .env sudah tersedia. Jika belum:
copy .env.example .env
php artisan key:generate
```

Pastikan `.env` berisi:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=citra_db
DB_USERNAME=root
DB_PASSWORD=

CITRA_AI_URL=http://localhost:5000
CITRA_AI_WS_URL=http://localhost:5000
```

**Jalankan migrasi + seeder:**
```powershell
php artisan migrate:fresh --seed
```

> **Alternatif — impor langsung dari file .sql:**
> phpMyAdmin → pilih `citra_db` → tab **Import** → pilih
> `database/citra_db_full.sql` → **Go**.
> Cara ini sudah termasuk seluruh data demo, jadi tidak perlu `migrate --seed`.

---

### 4. Setup Backend (Flask)

```powershell
cd backend
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
```

`.env` di folder `backend` sudah berisi konfigurasi yang sama dengan Laravel:
```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=citra_db
DB_USERNAME=root
DB_PASSWORD=
FLASK_PORT=5000
```

---

## MENJALANKAN APLIKASI

Buka **dua terminal**:

**Terminal 1 — Laravel**
```powershell
cd frontend
php artisan serve
```
→ `http://localhost:8000`

**Terminal 2 — AI backend**
```powershell
cd backend
venv\Scripts\activate
python app.py
```
→ `http://localhost:5000`

Saat startup, Flask memverifikasi koneksi database dan memuat model:
```
[citra] AI modules initialised
[citra] database OK (17 tables)
[citra] deep models: {'gerakan_classifier': True, 'pose_autoencoder': True, ...}
[citra] starting on 0.0.0.0:5000
```

Status server AI terlihat pada pil **"AI aktif / AI nonaktif"** di pojok kanan atas UI.

> **Opsional (Vite/Tailwind):** `cd frontend && npm install && npm run dev`.
> Tidak wajib — seluruh halaman memakai CSS inline dan berfungsi tanpanya.

---

## PIPELINE DATASET & MODEL AI

### Alur lengkap

```
Video maestro (.mp4)
        │
        ├─ ffmpeg ──────────► public/videos/maestro/*.mp4   (720p untuk web)
        │
        └─ build_dataset.py ─► maestro_data/dataset/klana/
                                  ├── keypoints/*.json      (titik sendi + sudut)
                                  ├── keypoints/*.npy       (matriks fitur latih)
                                  └── frames/*.jpg          (frame beranotasi)
                                          │
                    ┌─────────────────────┴──────────────────────┐
                    ▼                                            ▼
        php artisan citra:sync-dataset            python train_models.py
        (impor ke database)                       (latih 3 model deep learning)
```

### 1. Ekstraksi dataset dari video

Letakkan video di `backend/maestro_data/raw/`, daftarkan di `SOURCES`
(dalam `build_dataset.py`), lalu:

```powershell
cd backend
venv\Scripts\activate
python build_dataset.py --sample-fps 6 --model-complexity 2 --publish-frames
```

Opsi berguna:
```powershell
python build_dataset.py --only klana_maestro_full_cam1 --force
python build_dataset.py --annotate-every 10 --max-annotated 140
```

**Hasil ekstraksi pada project ini:**

| Video | Peran | Frame | Terdeteksi | Segmen | Durasi |
|---|---|---:|---:|---:|---:|
| klana_maestro_full_cam1 | maestro | 2.829 | 100,0% | 157 | 7:33 |
| klana_maestro_full_cam2 | maestro | 2.832 | 100,0% | 164 | 7:33 |
| klana_latihan_sesi1 | latihan | 2.746 | 100,0% | 158 | 7:39 |
| klana_latihan_sesi2 | latihan | 2.697 | 100,0% | 155 | 7:36 |
| **TOTAL** | | **11.104** | **99,98%** | **634** | **30:21** |

### 2. Impor dataset ke database

```powershell
cd frontend
php artisan citra:sync-dataset --publish
```

### 3. Latih model deep learning

```powershell
cd backend
python train_models.py --karakter klana --clusters 8 --epochs 60
```

Model tersimpan di `backend/models/` dan otomatis dimuat saat Flask start.

**Hasil pelatihan pada dataset ini:**

| Model | Metrik | Nilai |
|---|---|---|
| Gerakan Classifier | akurasi latih / validasi | 95,6% / **66,8%** (8 kelas, baseline acak 12,5%) |
| Pose Autoencoder | val_loss | 0,331 |
| Tempo Regressor | val_MAE | 0,071 |

> **Catatan jujur soal classifier:** selisih akurasi latih dan validasi
> menunjukkan *overfitting*. Ini wajar karena label gerakan **belum dianotasi
> manual** — label dihasilkan otomatis lewat K-Means atas ciri kinematik tiap
> segmen. Akurasi validasi 66,8% tetap jauh di atas tebak acak 12,5%, jadi model
> memang menangkap pola gerakan nyata. Untuk hasil yang lebih kuat, langkah
> berikutnya adalah menganotasi nama gerakan secara manual (ubah
> `backend/models/label_map.json`) dan menambah variasi video.
>
> **Penilaian Wiraga tidak bergantung pada model ini.** Skor utama memakai
> perbandingan geometris (sudut sendi + DTW) yang deterministik dan sudah akurat.
> Model deep learning adalah lapisan tambahan.

---

## AKUN DEMO

Setelah `php artisan migrate:fresh --seed` atau impor `citra_db_full.sql`:

| Email | Password | Peran |
|---|---|---|
| `admin@citra.test` | `admin12345` | Admin (bisa unggah video maestro) |
| `siti@citra.test` | `password123` | Master — 58 sesi |
| `ahmad@citra.test` | `password123` | Mahir — 42 sesi |
| `dewi@citra.test` | `password123` | Mahir — 35 sesi |
| `budi@citra.test` | `password123` | Menengah — 21 sesi |
| `eko@citra.test` | `password123` | Pemula — 9 sesi |

> Akun yang dibuat di Laravel **bisa langsung login lewat API Flask** dan
> sebaliknya — hash bcrypt diterjemahkan antara format `$2y$` (PHP) dan `$2b$` (Python).

---

## REFERENSI API

### Laravel (`http://localhost:8000`)

Sesi web (`auth` middleware) — dipakai oleh UI.

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/ping` | Cek server (publik) |
| GET | `/api/user/stats` | Ringkasan statistik pengguna |
| GET | `/api/user/weekly-progress` | Progres harian (`?days=7`) |
| GET | `/api/user/character-mastery` | Penguasaan 5 karakter |
| GET | `/api/user/achievements` | Daftar pencapaian + status |
| GET | `/api/practice/history` | Riwayat (`?limit=&karakter=&period=`) |
| GET | `/api/maestro/references` | Referensi maestro (`?karakter=`) |
| GET | `/api/maestro/{id}/keyframes` | Pose acuan untuk penilaian di browser |
| GET | `/api/datasets` | Dataset pose yang tersedia |
| GET | `/api/leaderboard` | Peringkat (`?karakter=&period=&limit=`) |
| GET | `/api/notifications` | Notifikasi in-app |
| POST | `/api/notifications/read` | Tandai semua terbaca |
| GET | `/api/ai/status` | Status server Flask |

### Flask (`http://localhost:5000`)

JWT Bearer token (kecuali yang ditandai publik).

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/health` | Status AI + database + model *(publik)* |
| POST | `/api/register` | Registrasi |
| POST | `/api/login` | Login → `access_token` |
| GET/PUT | `/api/user` | Profil |
| POST | `/api/practice/start` | Mulai sesi penilaian |
| POST | `/api/practice/end` | Akhiri sesi & simpan hasil |
| GET | `/api/practice/history` | Riwayat |
| GET | `/api/practice/stats` | Statistik + streak |
| GET | `/api/maestro` | Daftar referensi *(publik)* |
| GET | `/api/maestro/{id}/keyframes` | Keyframes; `?t=12.5` → pose terdekat *(publik)* |
| GET | `/api/dataset` | Dataset pose *(publik)* |
| GET | `/api/dataset/{slug}/segments` | Segmentasi gerakan *(publik)* |
| POST | `/api/maestro/upload` | Unggah video maestro *(admin)* |
| POST | `/api/analyze/pose` | Analisis 1 frame *(publik)* |
| POST | `/api/analyze/audio` | Analisis audio gamelan *(publik)* |
| POST | `/api/analyze/sequence` | Inferensi deep learning *(publik)* |
| GET | `/api/leaderboard` | Peringkat *(publik)* |

### WebSocket (Socket.IO)

| Event | Arah | Keterangan |
|---|---|---|
| `join_session` | client → | Ikat socket ke sesi latihan |
| `pose_frame` | client → | Kirim landmark (atau JPEG) untuk dinilai |
| `pose_result` | → client | Skor, perbandingan, feedback |
| `audio_chunk` | client → | Potongan audio untuk deteksi ketukan |
| `audio_result` | → client | Ketukan terdeteksi + energi |
| `movement_accent` | client → | Aksen gerakan (untuk Wirama) |
| `request_feedback` | client → | Minta snapshot skor terkini |
| `leave_session` | client → | Lepas ikatan sesi |

---

## TROUBLESHOOTING

### `SQLSTATE[HY000] [2002]` — tidak bisa konek MySQL
MySQL Laragon belum jalan atau salah port.
```powershell
netstat -ano | findstr :3307
```
Pastikan `DB_PORT` di `frontend/.env` **dan** `backend/.env` sama dengan port MySQL Laragon.

### phpMyAdmin masuk ke database yang salah
phpMyAdmin dikonfigurasi dengan dua server. Pilih **Laragon MySQL (CITRA) :3307**
di layar login. Host harus `127.0.0.1`, bukan `localhost` — dengan `localhost`
klien MySQL di Windows mengabaikan setting port.

### Halaman `/dataset` mengembalikan 404
Pastikan **tidak ada** folder `frontend/public/dataset`. Direktori di `public/`
disajikan sebelum router, jadi folder bernama `dataset` akan menutupi rute
`/dataset`. Folder yang benar adalah `public/pose-frames`.

### Pil status menunjukkan "AI nonaktif"
Server Flask belum jalan. Mode Latihan tetap bisa dipakai (penilaian di browser),
tetapi lapisan deep learning tidak aktif.
```powershell
cd backend && venv\Scripts\activate && python app.py
```

### `[citra] missing tables`
Migrasi Laravel belum dijalankan:
```powershell
cd frontend && php artisan migrate --seed
```

### Kamera tidak bisa diakses
- Browser hanya mengizinkan kamera pada `localhost` atau HTTPS
- Klik ikon kamera di address bar → **Izinkan**
- Tutup aplikasi lain yang memakai kamera (Zoom, Teams, OBS)

### `ModuleNotFoundError: No module named 'mediapipe'`
Virtual environment belum aktif:
```powershell
cd backend && venv\Scripts\activate && pip install -r requirements.txt
```

### TensorFlow gagal diinstal
TensorFlow 2.15 memerlukan **Python 3.10 atau 3.11**. Cek dengan `python --version`.

### Video maestro tidak tampil
```powershell
cd frontend && php artisan citra:sync-dataset --publish
```
Pastikan file ada di `frontend/public/videos/maestro/`.

### Reset total
```powershell
cd frontend
php artisan migrate:fresh --seed
php artisan citra:sync-dataset --publish
php artisan optimize:clear
```

---

## DEPLOYMENT (PRODUKSI)

**Backend**
```bash
pip install gunicorn eventlet
gunicorn --worker-class eventlet -w 1 app:app -b 0.0.0.0:5000
```
> Wajib `-w 1`: Socket.IO menyimpan state sesi di memori proses.

**Frontend**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Set `APP_DEBUG=false` dan `APP_ENV=production`.

**Nginx**
```nginx
server {
    listen 80;
    server_name citra.example.com;
    client_max_body_size 512M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    location /socket.io/ {
        proxy_pass http://127.0.0.1:5000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

---

## LISENSI

MIT License — CITRA Project
