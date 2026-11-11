<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    /** Satu sumber kebenaran dengan daftar pengajuan: scope visibleTo. Dipakai juga untuk unduh nota (BR-14). */
    public function view(User $user, Submission $submission): bool
    {
        return Submission::visibleTo($user)->whereKey($submission->id)->exists();
    }

    /** BR-05: edit dan submit hanya oleh pemilik, selama masih draft. */
    public function update(User $user, Submission $submission): bool
    {
        return $submission->user_id === $user->id
            && $submission->status === SubmissionStatus::Draft;
    }

    /** Approve / reject step saat ini (BR-01 sampai BR-03). */
    public function act(User $user, Submission $submission): bool
    {
        return $submission->canBeActedBy($user);
    }

    public function cancel(User $user, Submission $submission): bool
    {
        return $submission->canBeCancelledBy($user);
    }

    /** BR-18: PDF surat hanya untuk pemohon, setelah disetujui. */
    public function letter(User $user, Submission $submission): bool
    {
        return $submission->user_id === $user->id
            && $submission->type === SubmissionType::Surat
            && $submission->status === SubmissionStatus::Approved;
    }
}
