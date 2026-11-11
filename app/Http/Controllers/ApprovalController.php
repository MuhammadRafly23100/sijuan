<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApprovalController extends Controller
{
    public function approve(Request $request, Submission $submission)
    {
        Gate::authorize('act', $submission);

        $input = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $submission->approve($request->user(), $input['note'] ?? null);

        return back()->with('status', 'Pengajuan disetujui.');
    }

    public function reject(Request $request, Submission $submission)
    {
        Gate::authorize('act', $submission);

        // BR-04: penolakan wajib menyertakan catatan.
        $input = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $submission->reject($request->user(), $input['note']);

        return back()->with('status', 'Pengajuan ditolak.');
    }
}
