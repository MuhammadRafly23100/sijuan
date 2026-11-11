<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /** Rekap bulanan dalam CSV: HR untuk cuti dan surat, Finance untuk reimbursement. */
    public function __invoke(Request $request)
    {
        $types = match ($request->user()->role) {
            Role::Hr => [SubmissionType::Cuti, SubmissionType::Surat],
            Role::Finance => [SubmissionType::Reimbursement],
            default => abort(403),
        };

        $input = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $input['month']);

        $rows = Submission::with('user')
            ->whereIn('type', $types)
            ->where('status', '!=', SubmissionStatus::Draft)
            ->whereBetween('submitted_at', [$month, $month->copy()->endOfMonth()])
            ->orderBy('submitted_at')
            ->get();

        // Cegah CSV/formula injection: sel yang diawali = + - @ dibuka Excel sebagai rumus.
        $safe = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');

            // ponytail: pemisah koma + BOM UTF-8. Excel dengan format regional Indonesia
            // memakai titik koma; kalau itu jadi masalah, ganti ke paket Excel (.xlsx).
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Jenis', 'Pemohon', 'Judul', 'Status', 'Diajukan', 'Mulai', 'Selesai', 'Hari', 'Kategori', 'Nominal'], escape: '');

            foreach ($rows as $s) {
                fputcsv($out, array_map($safe, [
                    $s->id,
                    $s->type->label(),
                    $s->user->name,
                    $s->title,
                    $s->statusLabel(),
                    $s->submitted_at->format('Y-m-d H:i'),
                    $s->start_date?->toDateString(),
                    $s->end_date?->toDateString(),
                    $s->days,
                    $s->data['kategori'] ?? null,
                    $s->amount,
                ]), escape: '');
            }

            fclose($out);
        }, "rekap-{$input['month']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
