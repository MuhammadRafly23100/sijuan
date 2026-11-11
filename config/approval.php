<?php

/*
| Rantai approval per jenis pengajuan: daftar role, urut dari step 0.
| Menambah jenis pengajuan baru = tambah satu baris di sini, satu case di
| App\Enums\SubmissionType, dan field form-nya.
*/

return [
    'cuti' => ['manager', 'hr'],
    'reimbursement' => ['manager', 'finance'],
    'surat' => ['hr'],
];
