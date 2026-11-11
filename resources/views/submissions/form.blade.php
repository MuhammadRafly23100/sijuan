@use('App\Enums\SubmissionType')
@php
    $type = $submission->type;
    $editing = $submission->exists;
    $data = $submission->data ?? [];
@endphp
<x-layout :title="($editing ? 'Edit ' : 'Buat ').$type->label()">
    <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Edit' : 'Buat' }} pengajuan: {{ $type->label() }}</h1>
    <p class="mt-1 text-sm text-slate-600">
        Disimpan sebagai draft dulu. Pengajuan baru masuk ke approver setelah Anda klik Ajukan di halaman berikutnya.
    </p>

    <form method="POST"
          action="{{ $editing ? route('submissions.update', $submission) : route('submissions.store') }}"
          @if ($type === SubmissionType::Reimbursement) enctype="multipart/form-data" @endif
          class="card mt-6 max-w-2xl space-y-5 p-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @else
            <input type="hidden" name="type" value="{{ $type->value }}">
        @endif

        <x-field name="title" label="Judul">
            <input id="title" name="title" type="text" maxlength="150" required class="input"
                   value="{{ old('title', $submission->title) }}">
        </x-field>

        @switch($type)
            @case(SubmissionType::Cuti)
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="start_date" label="Tanggal mulai">
                        <input id="start_date" name="start_date" type="date" required class="input"
                               value="{{ old('start_date', $submission->start_date?->toDateString()) }}">
                    </x-field>
                    <x-field name="end_date" label="Tanggal selesai" hint="Sabtu dan Minggu tidak dihitung.">
                        <input id="end_date" name="end_date" type="date" required class="input"
                               value="{{ old('end_date', $submission->end_date?->toDateString()) }}">
                    </x-field>
                </div>
                <x-field name="alasan" label="Alasan (opsional)">
                    <textarea id="alasan" name="alasan" rows="3" maxlength="1000" class="input">{{ old('alasan', $data['alasan'] ?? '') }}</textarea>
                </x-field>
                <p class="text-sm text-slate-600">Sisa cuti Anda: <strong>{{ auth()->user()->sisa_cuti }} hari</strong>.</p>
                @break

            @case(SubmissionType::Reimbursement)
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="kategori" label="Kategori">
                        <select id="kategori" name="kategori" required class="input">
                            <option value="" disabled @selected(! old('kategori', $data['kategori'] ?? null))>Pilih kategori</option>
                            @foreach (config('reimbursement.kategori') as $key => $limit)
                                <option value="{{ $key }}" @selected(old('kategori', $data['kategori'] ?? null) === $key)>
                                    {{ str($key)->headline() }}{{ $limit ? ' (maks. Rp'.number_format($limit, 0, ',', '.').')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="tanggal_transaksi" label="Tanggal transaksi"
                             :hint="'Maksimal '.config('reimbursement.max_hari_pengajuan').' hari yang lalu.'">
                        <input id="tanggal_transaksi" name="tanggal_transaksi" type="date" required class="input"
                               max="{{ today()->toDateString() }}"
                               value="{{ old('tanggal_transaksi', $data['tanggal_transaksi'] ?? '') }}">
                    </x-field>
                </div>
                <x-field name="amount" label="Nominal (Rp)">
                    <input id="amount" name="amount" type="number" min="1" step="1" inputmode="numeric" required class="input"
                           value="{{ old('amount', $submission->amount) }}">
                </x-field>
                <x-field name="attachment" label="Nota"
                         :hint="'JPG, PNG, atau PDF, maksimal 2 MB.'.($submission->attachment_path ? ' Kosongkan jika tidak ingin mengganti nota yang sudah ada.' : '')">
                    <input id="attachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.pdf"
                           @required(! $submission->attachment_path)
                           class="block w-full text-sm text-slate-700 file:mr-3 file:cursor-pointer file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium">
                </x-field>
                <x-field name="keterangan" label="Keterangan (opsional)">
                    <textarea id="keterangan" name="keterangan" rows="3" maxlength="1000" class="input">{{ old('keterangan', $data['keterangan'] ?? '') }}</textarea>
                </x-field>
                @break

            @case(SubmissionType::Surat)
                <x-field name="keperluan" label="Keperluan" hint="Contoh: pengajuan KPR, pembuatan visa.">
                    <input id="keperluan" name="keperluan" type="text" maxlength="255" required class="input"
                           value="{{ old('keperluan', $data['keperluan'] ?? '') }}">
                </x-field>
                @break
        @endswitch

        <div class="flex gap-2 border-t border-slate-100 pt-5">
            <button class="btn btn-primary">Simpan draft</button>
            <a href="{{ $editing ? route('submissions.show', $submission) : route('submissions.index') }}" class="btn">Batal</a>
        </div>
    </form>
</x-layout>
