<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Http\Requests\SubmissionRequest;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(SubmissionType::class)],
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
        ]);

        $submissions = Submission::visibleTo($request->user())
            ->with('user')
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('submissions.index', compact('submissions', 'filters'));
    }

    public function create(SubmissionType $type)
    {
        return view('submissions.form', ['submission' => new Submission(['type' => $type])]);
    }

    public function store(SubmissionRequest $request)
    {
        $submission = new Submission(['type' => $request->validated('type')]);
        $submission->user_id = $request->user()->id;

        $this->persist($submission, $request);

        return redirect()
            ->route('submissions.show', $submission)
            ->with('status', 'Draft tersimpan. Periksa isinya, lalu klik Ajukan.');
    }

    public function show(Submission $submission)
    {
        Gate::authorize('view', $submission);

        $submission->load(['user.atasan', 'logs.user']);

        return view('submissions.show', compact('submission'));
    }

    public function edit(Submission $submission)
    {
        Gate::authorize('update', $submission);

        return view('submissions.form', compact('submission'));
    }

    public function update(SubmissionRequest $request, Submission $submission)
    {
        Gate::authorize('update', $submission);

        $this->persist($submission, $request);

        return redirect()
            ->route('submissions.show', $submission)
            ->with('status', 'Draft diperbarui.');
    }

    public function submit(Submission $submission)
    {
        Gate::authorize('update', $submission);

        $submission->submit();

        return back()->with('status', 'Pengajuan terkirim dan menunggu persetujuan.');
    }

    public function cancel(Request $request, Submission $submission)
    {
        Gate::authorize('cancel', $submission);

        $submission->cancel($request->user());

        return back()->with('status', 'Pengajuan dibatalkan.');
    }

    /** BR-14: nota hanya bisa dibuka oleh yang boleh melihat pengajuannya. */
    public function attachment(Submission $submission)
    {
        Gate::authorize('view', $submission);

        abort_unless($submission->attachment_path, 404);

        return Storage::response($submission->attachment_path);
    }

    /** BR-18: PDF dibuat saat diminta, tidak disimpan. */
    public function letter(Submission $submission)
    {
        Gate::authorize('letter', $submission);

        $submission->load(['user', 'logs.user']);

        return Pdf::loadView('submissions.letter', compact('submission'))
            ->download("surat-keterangan-kerja-{$submission->id}.pdf");
    }

    private function persist(Submission $submission, SubmissionRequest $request): void
    {
        $input = $request->validated();

        $submission->title = $input['title'];

        match ($submission->type) {
            SubmissionType::Cuti => $submission->fill([
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'data' => ['alasan' => $input['alasan'] ?? null],
            ]),
            SubmissionType::Reimbursement => $submission->fill([
                'amount' => $input['amount'],
                'data' => Arr::only($input, ['kategori', 'tanggal_transaksi', 'keterangan']),
            ]),
            SubmissionType::Surat => $submission->fill([
                'data' => ['keperluan' => $input['keperluan']],
            ]),
        };

        if ($request->hasFile('attachment')) {
            if ($submission->attachment_path) {
                Storage::delete($submission->attachment_path);
            }

            // Disk default "local" bersifat private (storage/app/private).
            $submission->attachment_path = $request->file('attachment')->store('nota');
        }

        $submission->save();
    }
}
