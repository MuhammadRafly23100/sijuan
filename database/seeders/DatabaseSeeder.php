<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\SubmissionType;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Akun dan pengajuan demo. Sengaja tidak memakai WithoutModelEvents karena
 * model Submission menghitung jumlah hari cuti lewat event "saving".
 *
 * Tidak memakai factory supaya bisa dijalankan di produksi, tempat paket
 * faker (dependency dev) tidak terpasang. Aman dijalankan berulang: jika
 * sudah ada user, seeder tidak melakukan apa-apa.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::exists()) {
            return;
        }

        $user = fn (string $name, string $email, Role $role, ?User $atasan = null) => User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password', // akun demo; di-hash otomatis oleh cast model
            'role' => $role,
            'atasan_id' => $atasan?->id,
        ]);

        $direktur = $user('Dewi Direktur', 'direktur@sijuan.test', Role::Manager);
        $manager = $user('Made Manager', 'manager@sijuan.test', Role::Manager, $direktur);
        $hr = $user('Hana HR', 'hr1@sijuan.test', Role::Hr);
        $user('Hendra HR', 'hr2@sijuan.test', Role::Hr);
        $user('Fitri Finance', 'finance@sijuan.test', Role::Finance);
        $staff1 = $user('Sari Staff', 'staff1@sijuan.test', Role::Staff, $manager);
        $staff2 = $user('Satria Staff', 'staff2@sijuan.test', Role::Staff, $manager);
        $staff3 = $user('Sinta Staff', 'staff3@sijuan.test', Role::Staff, $manager);

        $draft = function (User $owner, SubmissionType $type, string $title, array $attributes) {
            $submission = (new Submission)->forceFill(
                ['user_id' => $owner->id, 'type' => $type, 'title' => $title] + $attributes
            );
            $submission->save();

            return $submission;
        };

        $senin = today()->next(Carbon::MONDAY);

        // Cuti: menunggu Manager.
        $draft($staff1, SubmissionType::Cuti, 'Cuti acara keluarga', [
            'start_date' => $senin,
            'end_date' => $senin->copy()->addDays(2),
            'data' => ['alasan' => 'Menghadiri pernikahan saudara di luar kota.'],
        ])->submit();

        // Cuti: sudah disetujui Manager, menunggu HR.
        $cuti = $draft($staff2, SubmissionType::Cuti, 'Cuti pulang kampung', [
            'start_date' => $senin->copy()->addWeek(),
            'end_date' => $senin->copy()->addWeek()->addDay(),
            'data' => ['alasan' => null],
        ]);
        $cuti->submit();
        $cuti->approve($manager);

        // Cuti: disetujui penuh, kuota terpotong.
        $cuti = $draft($staff3, SubmissionType::Cuti, 'Cuti urusan pribadi', [
            'start_date' => $senin->copy()->addWeeks(2),
            'end_date' => $senin->copy()->addWeeks(2),
            'data' => ['alasan' => null],
        ]);
        $cuti->submit();
        $cuti->approve($manager);
        $cuti->approve($hr, 'Selamat berlibur.');

        // Surat: disetujui, PDF bisa diunduh pemohon.
        $surat = $draft($staff3, SubmissionType::Surat, 'Surat keterangan untuk KPR', [
            'data' => ['keperluan' => 'Pengajuan KPR'],
        ]);
        $surat->submit();
        $surat->approve($hr);

        // Reimbursement butuh nota; dilewati jika ekstensi GD tidak ada.
        if ($nota = $this->contohNota('taksi', 'Taksi ke kantor klien', 185_000)) {
            $klaim = $draft($staff1, SubmissionType::Reimbursement, 'Taksi ke kantor klien', [
                'amount' => 185_000,
                'attachment_path' => $nota,
                'data' => [
                    'kategori' => 'transportasi',
                    'tanggal_transaksi' => today()->subDays(3)->toDateString(),
                    'keterangan' => 'Pulang pergi untuk presentasi proposal.',
                ],
            ]);
            $klaim->submit();
            $klaim->approve($manager);

            $klaim = $draft($staff2, SubmissionType::Reimbursement, 'Makan siang dengan klien', [
                'amount' => 420_000,
                'attachment_path' => $this->contohNota('makan', 'Makan siang 4 orang', 420_000),
                'data' => [
                    'kategori' => 'makan_entertain',
                    'tanggal_transaksi' => today()->subDays(5)->toDateString(),
                    'keterangan' => null,
                ],
            ]);
            $klaim->submit();
            $klaim->reject($manager, 'Nota tidak mencantumkan nama klien. Mohon ajukan ulang dengan nota yang lengkap.');
        }
    }

    /** Membuat gambar nota contoh di disk private dan mengembalikan path-nya. */
    private function contohNota(string $nama, string $baris, int $total): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor(420, 180);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $ink = imagecolorallocate($image, 30, 30, 30);

        imagestring($image, 5, 20, 20, 'CONTOH NOTA (data demo)', $ink);
        imagestring($image, 4, 20, 70, $baris, $ink);
        imagestring($image, 4, 20, 100, 'Total: Rp'.number_format($total, 0, ',', '.'), $ink);

        ob_start();
        imagepng($image);
        $path = "nota/contoh-{$nama}.png";
        Storage::put($path, ob_get_clean());

        return $path;
    }
}
