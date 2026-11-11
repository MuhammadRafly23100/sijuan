<?php

namespace App\Enums;

enum SubmissionType: string
{
    case Cuti = 'cuti';
    case Reimbursement = 'reimbursement';
    case Surat = 'surat';

    public function label(): string
    {
        return match ($this) {
            self::Cuti => 'Cuti',
            self::Reimbursement => 'Reimbursement',
            self::Surat => 'Surat Keterangan Kerja',
        };
    }

    /**
     * Rantai approval jenis ini: daftar role, urut dari step 0.
     *
     * @return list<string>
     */
    public function chain(): array
    {
        return config("approval.{$this->value}");
    }
}
