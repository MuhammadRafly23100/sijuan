<?php

// Hanya aturan yang dipakai aplikasi ini. Aturan lain jatuh ke bahasa Inggris bawaan Laravel.

return [
    'after_or_equal' => ':Attribute tidak boleh sebelum :date.',
    'before_or_equal' => ':Attribute tidak boleh setelah :date.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'date_format' => ':Attribute harus berformat :format.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'file' => ':Attribute harus berupa file.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa angka bulat.',
    'max' => [
        'file' => ':Attribute maksimal :max KB.',
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'mimes' => ':Attribute harus berupa file: :values.',
    'min' => [
        'numeric' => ':Attribute minimal :min.',
    ],
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',

    'attributes' => [
        'title' => 'judul',
        'type' => 'jenis pengajuan',
        'start_date' => 'tanggal mulai',
        'end_date' => 'tanggal selesai',
        'alasan' => 'alasan',
        'amount' => 'nominal',
        'kategori' => 'kategori',
        'tanggal_transaksi' => 'tanggal transaksi',
        'keterangan' => 'keterangan',
        'attachment' => 'nota',
        'keperluan' => 'keperluan',
        'note' => 'catatan',
        'month' => 'bulan',
        'password' => 'kata sandi',
        'today' => 'hari ini',
    ],
];
