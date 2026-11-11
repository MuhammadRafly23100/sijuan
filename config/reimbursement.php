<?php

return [
    // BR-16: pengajuan paling lambat sekian hari setelah tanggal transaksi.
    'max_hari_pengajuan' => 30,

    // BR-15 & BR-17: kunci => batas nominal per klaim dalam rupiah (null = tanpa batas).
    // Nilai batas hanya contoh; sesuaikan dengan kebijakan perusahaan.
    'kategori' => [
        'transportasi' => null,
        'makan_entertain' => 500_000,
        'perjalanan_dinas' => null,
        'komunikasi' => 300_000,
        'perlengkapan_kantor' => 1_000_000,
        'lainnya' => 500_000,
    ],
];
