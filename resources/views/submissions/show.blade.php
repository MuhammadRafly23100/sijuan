@use('App\Enums\Role')
@use('App\Enums\SubmissionStatus')
@use('App\Enums\SubmissionType')
@php
    $data = $submission->data ?? [];
    $approvedSteps = $submission->logs->where('action', 'approved')->pluck('step');
    $actionLabels = ['submitted' => 'mengajukan', 'approved' => 'menyetujui', 'rejected' => 'menolak', 'cancelled' => 'membatalkan'];
@endphp
<x-layout :title="$submission->title">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">{{ $submission->type->label() }} · No. {{ $submission->id }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $submission->title }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                Pemohon: {{ $submission->user->name }}@if ($submission->user->atasan) · Atasan: {{ $submission->user->atasan->name }}@endif
            </p>
        </div>
        <x-status-badge :submission="$submission" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-5" aria-labelledby="rincian">
                <h2 id="rincian" class="text-base font-semibold">Rincian</h2>

                <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @switch($submission->type)
                        @case(SubmissionType::Cuti)
                            <div>
                                <dt class="text-slate-500">Tanggal</dt>
                                <dd class="mt-0.5 font-medium">
                                    {{ $submission->start_date->translatedFormat('j M Y') }} sampai {{ $submission->end_date->translatedFormat('j M Y') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Jumlah hari kerja</dt>
                                <dd class="mt-0.5 font-medium">{{ $submission->days }} hari</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Alasan</dt>
                                <dd class="mt-0.5 whitespace-pre-line">{{ $data['alasan'] ?? '-' }}</dd>
                            </div>
                            @break

                        @case(SubmissionType::Reimbursement)
                            <div>
                                <dt class="text-slate-500">Nominal</dt>
                                <dd class="mt-0.5 font-medium tabular-nums">{{ $submission->rupiah() }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Kategori</dt>
                                <dd class="mt-0.5 font-medium">{{ str($data['kategori'] ?? '-')->headline() }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Tanggal transaksi</dt>
                                <dd class="mt-0.5 font-medium">{{ \Illuminate\Support\Carbon::parse($data['tanggal_transaksi'])->translatedFormat('j M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Nota</dt>
                                <dd class="mt-0.5">
                                    @if ($submission->attachment_path)
                                        <a href="{{ route('submissions.attachment', $submission) }}" target="_blank" rel="noopener"
                                           class="font-medium text-teal-800 underline-offset-2 hover:underline">Lihat nota</a>
                                    @else
                                        <span class="text-slate-500">Belum diunggah</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Keterangan</dt>
                                <dd class="mt-0.5 whitespace-pre-line">{{ $data['keterangan'] ?? '-' }}</dd>
                            </div>
                            @break

                        @case(SubmissionType::Surat)
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">Keperluan</dt>
                                <dd class="mt-0.5 font-medium">{{ $data['keperluan'] ?? '-' }}</dd>
                            </div>
                            @if ($submission->status === SubmissionStatus::Approved)
                                <div>
                                    <dt class="text-slate-500">Nomor surat</dt>
                                    <dd class="mt-0.5 font-medium">{{ $submission->letterNumber() }}</dd>
                                </div>
                            @endif
                            @break
                    @endswitch
                </dl>
            </section>

            <section class="card p-5" aria-labelledby="riwayat">
                <h2 id="riwayat" class="text-base font-semibold">Riwayat</h2>

                @if ($submission->logs->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">Belum ada aktivitas. Pengajuan ini masih draft.</p>
                @else
                    <ol class="mt-4 space-y-4 border-l border-slate-200 pl-5">
                        @foreach ($submission->logs as $log)
                            <li class="relative text-sm">
                                <span class="absolute top-1.5 -left-[1.6rem] size-2.5 rounded-full bg-slate-400 ring-4 ring-white" aria-hidden="true"></span>
                                <p>
                                    <span class="font-medium">{{ $log->user->name }}</span>
                                    <span class="text-slate-500">({{ $log->user->role->label() }})</span>
                                    {{ $actionLabels[$log->action] ?? $log->action }}
                                </p>
                                <p class="text-xs text-slate-500">{{ $log->created_at->translatedFormat('j M Y, H:i') }}</p>
                                @if ($log->note)
                                    <p class="mt-1 rounded-md bg-slate-50 px-3 py-2 whitespace-pre-line text-slate-700">{{ $log->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section class="card p-5" aria-labelledby="alur">
                <h2 id="alur" class="text-base font-semibold">Alur persetujuan</h2>

                <ol class="mt-4 space-y-3 text-sm">
                    @foreach ($submission->chain() as $i => $role)
                        @php
                            [$state, $color] = match (true) {
                                $approvedSteps->contains($i) => ['Disetujui', 'text-emerald-800'],
                                $submission->status === SubmissionStatus::Pending && $submission->step === $i => ['Menunggu', 'text-amber-800'],
                                $submission->status === SubmissionStatus::Rejected && $submission->step === $i => ['Ditolak', 'text-rose-800'],
                                $submission->status !== SubmissionStatus::Draft && $i < $submission->step => ['Dilewati', 'text-slate-500'],
                                default => ['Belum', 'text-slate-500'],
                            };
                        @endphp
                        <li class="flex items-center justify-between gap-3">
                            <span>{{ $i + 1 }}. {{ Role::from($role)->label() }}</span>
                            <span class="font-medium {{ $color }}">{{ $state }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            @canany(['update', 'cancel', 'letter'], $submission)
                <section class="card space-y-3 p-5" aria-labelledby="aksi">
                    <h2 id="aksi" class="text-base font-semibold">Aksi</h2>

                    @can('update', $submission)
                        <form method="POST" action="{{ route('submissions.submit', $submission) }}">
                            @csrf
                            <button class="btn btn-primary w-full">Ajukan</button>
                        </form>
                        <a href="{{ route('submissions.edit', $submission) }}" class="btn w-full">Edit draft</a>
                    @endcan

                    @can('letter', $submission)
                        <a href="{{ route('submissions.letter', $submission) }}" class="btn btn-primary w-full">Unduh surat (PDF)</a>
                    @endcan

                    @can('cancel', $submission)
                        <form method="POST" action="{{ route('submissions.cancel', $submission) }}">
                            @csrf
                            <button class="btn btn-danger w-full">Batalkan pengajuan</button>
                        </form>
                    @endcan
                </section>
            @endcanany

            @can('act', $submission)
                <form method="POST" class="card p-5" aria-labelledby="keputusan">
                    @csrf
                    <h2 id="keputusan" class="text-base font-semibold">Keputusan Anda</h2>

                    <div class="mt-3">
                        <x-field name="note" label="Catatan" hint="Wajib diisi jika menolak.">
                            <textarea id="note" name="note" rows="3" maxlength="1000" class="input">{{ old('note') }}</textarea>
                        </x-field>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button class="btn btn-primary" formaction="{{ route('submissions.approve', $submission) }}">Setujui</button>
                        <button class="btn btn-danger" formaction="{{ route('submissions.reject', $submission) }}">Tolak</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
</x-layout>
