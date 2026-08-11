# Database CITRA — File SQL untuk phpMyAdmin

Semua file di folder ini adalah dump MySQL 8.0 dengan charset `utf8mb4`.

| File | Isi | Ukuran | Gunakan untuk |
|---|---|---:|---|
| `citra_db_full.sql` | Skema **+ seluruh data demo** | ~978 KB | Instalasi cepat, demo, presentasi |
| `citra_db_schema.sql` | Skema saja (17 tabel, tanpa data) | ~18 KB | Instalasi bersih, deployment |
| `citra_db_reference_only.sql` | Skema + data referensi (gerakan, dataset, pencapaian) tanpa akun/sesi | ~148 KB | Produksi — konten siap, pengguna mulai dari nol |

---

## Cara impor lewat phpMyAdmin

1. Buka `http://localhost/phpmyadmin`
2. Login ke server **Laragon MySQL (CITRA) :3307** sebagai `root` (tanpa password)
3. Klik tab **Import**
4. **Choose File** → pilih `citra_db_full.sql`
5. Pastikan *Character set of the file* = **utf8mb4**
6. Klik **Go**

> `citra_db_full.sql` dan `citra_db_schema.sql` sudah berisi
> `CREATE DATABASE IF NOT EXISTS citra_db` dan `USE citra_db`, jadi Anda
> **tidak perlu** membuat database terlebih dahulu — cukup impor dari halaman
> utama phpMyAdmin.
>
> `citra_db_reference_only.sql` **tidak** berisi `CREATE DATABASE`, jadi pilih
> dulu database `citra_db` sebelum mengimpornya.

---

## Cara impor lewat terminal

```powershell
$mysql = "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"

Get-Content database\citra_db_full.sql -Raw |
  & $mysql -u root -h 127.0.0.1 -P 3307 --protocol=TCP --default-character-set=utf8mb4
```

---

## Isi data demo (`citra_db_full.sql`)

| Tabel | Baris | Keterangan |
|---|---:|---|
| `users` | 6 | 1 admin + 5 pengguna demo |
| `practice_sessions` | 165 | Riwayat latihan ~45 hari terakhir |
| `maestro_references` | 33 | 29 gerakan kurikulum + 4 rekaman utuh |
| `pose_datasets` | 4 | Metadata ekstraksi 4 video Klana |
| `achievements` | 12 | Definisi pencapaian |
| `user_achievements` | 27 | Pencapaian yang sudah terbuka |
| `leaderboard` | 24 | Peringkat per karakter |
| `gerakan_progress` | 85 | Progres per gerakan per pengguna |
| `citra_notifications` | 27 | Notifikasi in-app |
| *lainnya* | | `migrations`, `sessions`, `cache`, `jobs`, dll |

**Total 17 tabel.**

---

## Membuat ulang dump

```powershell
$dump = "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"

# Lengkap (skema + data)
& $dump -u root -h 127.0.0.1 -P 3307 --protocol=TCP --databases citra_db `
  --add-drop-database --add-drop-table --default-character-set=utf8mb4 `
  --skip-set-charset --column-statistics=0 `
  --result-file="database\citra_db_full.sql"

# Skema saja
& $dump -u root -h 127.0.0.1 -P 3307 --protocol=TCP --no-data --databases citra_db `
  --add-drop-database --add-drop-table --default-character-set=utf8mb4 `
  --skip-set-charset --column-statistics=0 `
  --result-file="database\citra_db_schema.sql"

# Data referensi saja (tanpa akun & sesi)
& $dump -u root -h 127.0.0.1 -P 3307 --protocol=TCP citra_db `
  achievements maestro_references pose_datasets migrations `
  --default-character-set=utf8mb4 --skip-set-charset --column-statistics=0 `
  --result-file="database\citra_db_reference_only.sql"
```

---

## Catatan

- **Sumber kebenaran skema adalah migrasi Laravel** (`frontend/database/migrations`).
  File SQL ini hasil ekspor dari skema tersebut. Untuk perubahan struktur, buat
  migrasi baru lalu ekspor ulang — jangan mengubah file `.sql` secara manual.
- Backend Flask memakai database yang **sama** dan tidak pernah membuat tabel
  sendiri (`db.create_all()` sengaja tidak dipanggil).
- Password disimpan sebagai bcrypt `$2y$` sehingga kompatibel dua arah antara
  Laravel (PHP) dan Flask (Python).
- Kolom JSON (`feedback`, `timeline`, `score_series`, `joint_scores`,
  `segments`, `settings`) memakai tipe `json` bawaan MySQL 8.
