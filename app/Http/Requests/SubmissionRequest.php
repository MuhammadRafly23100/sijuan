<?php

namespace App\Http\Requests;

use App\Enums\SubmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi bentuk isian untuk simpan dan edit draft. Aturan bisnis
 * (kuota, bentrok, batas nominal, dll.) dicek saat submit di model Submission.
 */
class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi per pengajuan dilakukan controller lewat SubmissionPolicy.
        return true;
    }

    public function rules(): array
    {
        $existing = $this->route('submission');
        $type = $existing?->type ?? SubmissionType::tryFrom((string) $this->input('type'));

        $rules = ['title' => ['required', 'string', 'max:150']];

        if (! $existing) {
            $rules['type'] = ['required', Rule::enum(SubmissionType::class)];
        }

        return $rules + match ($type) {
            SubmissionType::Cuti => [
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'alasan' => ['nullable', 'string', 'max:1000'],
            ],
            SubmissionType::Reimbursement => [
                'amount' => ['required', 'integer', 'min:1'],
                'kategori' => ['required', Rule::in(array_keys(config('reimbursement.kategori')))],
                'tanggal_transaksi' => ['required', 'date', 'before_or_equal:today'],
                'keterangan' => ['nullable', 'string', 'max:1000'],
                // BR-13: nota wajib; saat edit boleh kosong jika sudah ada.
                'attachment' => [
                    $existing?->attachment_path ? 'nullable' : 'required',
                    'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048',
                ],
            ],
            SubmissionType::Surat => [
                'keperluan' => ['required', 'string', 'max:255'],
            ],
            null => [],
        };
    }
}
