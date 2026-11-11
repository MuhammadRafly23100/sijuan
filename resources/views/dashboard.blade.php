@use('App\Enums\Role')
@use('App\Enums\SubmissionType')
<x-layout title="Dashboard">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Halo, {{ $user->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $user->role->label() }}@if ($user->atasan) · Atasan: {{ $user->atasan->name }}@endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-slate-600">Buat pengajuan:</span>
            @foreach (SubmissionType::cases() as $type)
                <a href="{{ route('submissions.create', $type) }}" class="btn">{{ $type->label() }}</a>
            @endforeach
        </div>
    </div>

    <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Sisa cuti', $user->sisa_cuti.' hari'],
            ['Menunggu', $counts['pending'] ?? 0],
            ['Disetujui', $counts['approved'] ?? 0],
            ['Ditolak', $counts['rejected'] ?? 0],
        ] as [$label, $value])
            <div class="card px-4 py-3">
                <dt class="text-xs font-medium text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($user->role !== Role::Staff)
        <section class="mt-8" aria-labelledby="antrean">
            <h2 id="antrean" class="text-base font-semibold">Perlu persetujuan Anda</h2>

            @if ($awaiting->isEmpty())
                <p class="card mt-3 px-4 py-6 text-sm text-slate-500">Tidak ada pengajuan yang menunggu Anda.</p>
            @else
                <div class="card mt-3 overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200">
                            <tr>
                                <th class="th">Pemohon</th>
                                <th class="th">Jenis</th>
                                <th class="th">Judul</th>
                                <th class="th">Diajukan</th>
                                <th class="th"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($awaiting as $s)
                                <tr>
                                    <td class="td">{{ $s->user->name }}</td>
                                    <td class="td">{{ $s->type->label() }}</td>
                                    <td class="td">{{ $s->title }}</td>
                                    <td class="td whitespace-nowrap text-slate-600">{{ $s->submitted_at->translatedFormat('j M Y') }}</td>
                                    <td class="td text-right">
                                        <a href="{{ route('submissions.show', $s) }}" class="font-medium text-teal-800 underline-offset-2 hover:underline">Periksa</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <section class="mt-8" aria-labelledby="terakhir">
        <div class="flex items-center justify-between">
            <h2 id="terakhir" class="text-base font-semibold">Pengajuan terakhir saya</h2>
            <a href="{{ route('submissions.index') }}" class="text-sm font-medium text-teal-800 underline-offset-2 hover:underline">Lihat semua</a>
        </div>

        @if ($mine->isEmpty())
            <p class="card mt-3 px-4 py-6 text-sm text-slate-500">Anda belum punya pengajuan.</p>
        @else
            <ul class="card mt-3 divide-y divide-slate-100">
                @foreach ($mine as $s)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <a href="{{ route('submissions.show', $s) }}" class="text-sm font-medium underline-offset-2 hover:underline">
                            {{ $s->title }}
                            <span class="font-normal text-slate-500">· {{ $s->type->label() }}</span>
                        </a>
                        <x-status-badge :submission="$s" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (in_array($user->role, [Role::Hr, Role::Finance]))
        <section class="mt-8" aria-labelledby="rekap">
            <h2 id="rekap" class="text-base font-semibold">Rekap bulanan</h2>
            <form method="GET" action="{{ route('reports.monthly') }}" class="card mt-3 flex flex-wrap items-end gap-3 px-4 py-4">
                <x-field name="month" label="Bulan">
                    <input id="month" name="month" type="month" value="{{ now()->format('Y-m') }}" required class="input">
                </x-field>
                <button class="btn">Unduh CSV</button>
            </form>
        </section>
    @endif
</x-layout>
