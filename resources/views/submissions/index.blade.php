@use('App\Enums\SubmissionStatus')
@use('App\Enums\SubmissionType')
<x-layout title="Pengajuan">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-semibold tracking-tight">Pengajuan</h1>

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-slate-600">Buat pengajuan:</span>
            @foreach (SubmissionType::cases() as $type)
                <a href="{{ route('submissions.create', $type) }}" class="btn">{{ $type->label() }}</a>
            @endforeach
        </div>
    </div>

    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
        <x-field name="type" label="Jenis">
            <select id="type" name="type" class="input">
                <option value="">Semua</option>
                @foreach (SubmissionType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="status" label="Status">
            <select id="status" name="status" class="input">
                <option value="">Semua</option>
                @foreach (SubmissionStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </x-field>

        <button class="btn">Terapkan</button>
    </form>

    @if ($submissions->isEmpty())
        <p class="card mt-4 px-4 py-8 text-sm text-slate-500">Tidak ada pengajuan yang cocok.</p>
    @else
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-slate-200">
                    <tr>
                        <th class="th">No.</th>
                        <th class="th">Judul</th>
                        <th class="th">Jenis</th>
                        <th class="th">Pemohon</th>
                        <th class="th">Diajukan</th>
                        <th class="th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($submissions as $s)
                        <tr>
                            <td class="td text-slate-500 tabular-nums">{{ $s->id }}</td>
                            <td class="td">
                                <a href="{{ route('submissions.show', $s) }}" class="font-medium text-teal-800 underline-offset-2 hover:underline">{{ $s->title }}</a>
                            </td>
                            <td class="td">{{ $s->type->label() }}</td>
                            <td class="td">{{ $s->user->name }}</td>
                            <td class="td whitespace-nowrap text-slate-600">{{ $s->submitted_at?->translatedFormat('j M Y') ?? '-' }}</td>
                            <td class="td"><x-status-badge :submission="$s" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $submissions->links() }}</div>
    @endif
</x-layout>
