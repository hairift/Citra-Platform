<?php

namespace App\Http\Controllers;

use App\Models\MaestroReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload a new maestro recording.
     *
     * The file lands in the Python backend's raw folder, which is where
     * ``build_dataset.py`` looks for it. A reference row is created but left
     * unpublished until the pose dataset has actually been extracted, so the
     * tutorial never offers a video that cannot be scored against.
     */
    public function upload(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->isAdmin(), 403, 'Hanya admin yang dapat mengunggah referensi maestro.');

        $validated = $request->validate([
            'video'        => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska', 'max:512000'],
            'karakter'     => ['required', 'string', 'in:panji,samba,rumyang,tumenggung,klana'],
            'gerakan_name' => ['required', 'string', 'max:100'],
            'gerakan_slug' => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'difficulty'   => ['nullable', 'string', 'in:mudah,menengah,sulit'],
            'role'         => ['nullable', 'string', 'in:maestro,latihan'],
        ], [
            'video.max'       => 'Ukuran video maksimal 500 MB.',
            'video.mimetypes' => 'Format video harus MP4, MOV, AVI, atau MKV.',
        ]);

        $file = $request->file('video');
        $slug = Str::slug($validated['karakter'].'-'.$validated['gerakan_name']).'-'.time();
        $filename = $slug.'.'.$file->getClientOriginalExtension();

        // backend/maestro_data/raw is the pipeline's input directory.
        $rawDir = base_path('../backend/maestro_data/raw');
        if (!is_dir($rawDir)) {
            mkdir($rawDir, 0755, true);
        }

        $file->move($rawDir, $filename);

        $reference = MaestroReference::create([
            'slug'         => $slug,
            'karakter'     => $validated['karakter'],
            'gerakan_name' => $validated['gerakan_name'],
            'gerakan_slug' => $validated['gerakan_slug'] ?? null,
            'role'         => $validated['role'] ?? 'maestro',
            'video_path'   => null,          // set once transcoded to public/videos
            'description'  => $validated['description'] ?? null,
            'difficulty'   => $validated['difficulty'] ?? 'menengah',
            'is_published' => false,
            'order_index'  => (MaestroReference::where('karakter', $validated['karakter'])->max('order_index') ?? 0) + 1,
        ]);

        return redirect()->back()->with('success',
            'Video "'.$reference->gerakan_name.'" berhasil diunggah ke '.$filename.'. '
            .'Jalankan "python build_dataset.py --force" di folder backend untuk '
            .'mengekstrak titik sendi, lalu "php artisan citra:sync-dataset" untuk '
            .'mempublikasikannya.'
        );
    }
}
