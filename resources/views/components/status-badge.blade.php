@props(['submission'])
@use('App\Enums\SubmissionStatus')
@php
    $color = match ($submission->status) {
        SubmissionStatus::Draft => 'bg-slate-100 text-slate-700',
        SubmissionStatus::Pending => 'bg-amber-100 text-amber-900',
        SubmissionStatus::Approved => 'bg-emerald-100 text-emerald-900',
        SubmissionStatus::Rejected => 'bg-rose-100 text-rose-900',
        SubmissionStatus::Cancelled => 'bg-slate-100 text-slate-500',
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap {{ $color }}">{{ $submission->statusLabel() }}</span>
