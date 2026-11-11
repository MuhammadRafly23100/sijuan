@php
    $approval = $submission->logs->where('action', 'approved')->last();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Keterangan Kerja {{ $submission->letterNumber() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; line-height: 1.6; color: #111; margin: 40px 50px; }
        .kop { border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 28px; }
        .kop h1 { font-size: 16pt; margin: 0; }
        .kop p { margin: 2px 0 0; font-size: 9pt; color: #444; }
        .judul { text-align: center; margin-bottom: 24px; }
        .judul h2 { font-size: 13pt; margin: 0; text-decoration: underline; }
        .judul p { margin: 2px 0 0; }
        table { margin: 12px 0 12px 20px; }
        td { padding: 2px 8px 2px 0; vertical-align: top; }
        .ttd { margin-top: 48px; width: 240px; margin-left: auto; }
        .ttd .nama { margin-top: 70px; font-weight: bold; text-decoration: underline; }
        .catatan { margin-top: 40px; font-size: 8pt; color: #666; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ config('app.name') }}</h1>
        <p>Perusahaan contoh untuk keperluan demo aplikasi. Bukan dokumen resmi.</p>
    </div>

    <div class="judul">
        <h2>SURAT KETERANGAN KERJA</h2>
        <p>Nomor: {{ $submission->letterNumber() }}</p>
    </div>

    <p>Yang bertanda tangan di bawah ini, bagian Human Resources {{ config('app.name') }}, menerangkan bahwa:</p>

    <table>
        <tr><td>Nama</td><td>:</td><td>{{ $submission->user->name }}</td></tr>
        <tr><td>Email</td><td>:</td><td>{{ $submission->user->email }}</td></tr>
        <tr><td>Jabatan</td><td>:</td><td>{{ $submission->user->role->label() }}</td></tr>
    </table>

    <p>adalah benar karyawan aktif di {{ config('app.name') }} sampai dengan surat ini diterbitkan.</p>

    <p>Surat keterangan ini dibuat untuk keperluan: <strong>{{ $submission->data['keperluan'] }}</strong>.</p>

    <p>Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

    <div class="ttd">
        <p>{{ $approval->created_at->translatedFormat('j F Y') }}<br>Human Resources</p>
        <p class="nama">{{ $approval->user->name }}</p>
    </div>

    <p class="catatan">Dokumen ini dibuat otomatis oleh sistem setelah pengajuan disetujui.</p>
</body>
</html>
