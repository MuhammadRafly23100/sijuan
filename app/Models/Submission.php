<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu pengajuan (cuti, reimbursement, atau surat) beserta seluruh aturan
 * workflow-nya. Semua perpindahan status hanya terjadi lewat submit(),
 * approve(), reject(), dan cancel() di class ini.
 */
#[Fillable(['type', 'title', 'start_date', 'end_date', 'amount', 'data'])]
class Submission extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'step' => 0,
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'type' => SubmissionType::class,
            'status' => SubmissionStatus::class,
            'step' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'amount' => 'integer',
            'data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // BR-08: jumlah hari cuti selalu dihitung ulang dari tanggalnya.
        static::saving(function (self $s) {
            if ($s->type === SubmissionType::Cuti && $s->start_date && $s->end_date) {
                $s->days = self::countWorkdays($s->start_date, $s->end_date);
            }
        });
    }

    // ---------------------------------------------------------------- Relasi

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class)->orderBy('id');
    }

    // ----------------------------------------------------------------- Scope

    /** Pengajuan yang boleh dilihat user: miliknya sendiri + yang relevan dengan role-nya. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id);

            $q->orWhere(function (Builder $q) use ($user) {
                $q->where('status', '!=', SubmissionStatus::Draft);

                match ($user->role) {
                    Role::Manager => $q->whereIn('user_id', $user->bawahan()->select('id')),
                    Role::Hr => $q->whereIn('type', [SubmissionType::Cuti, SubmissionType::Surat]),
                    Role::Finance => $q->where('type', SubmissionType::Reimbursement),
                    Role::Staff => $q->whereRaw('0 = 1'),
                };
            });
        });
    }

    /** Pengajuan yang sedang menunggu aksi user ini. Harus sejalan dengan canBeActedBy(). */
    public function scopeAwaiting(Builder $query, User $user): void
    {
        $query
            ->where('status', SubmissionStatus::Pending)
            ->where('user_id', '!=', $user->id)
            ->where(function (Builder $q) use ($user) {
                $q->whereRaw('0 = 1');

                foreach (SubmissionType::cases() as $type) {
                    foreach ($type->chain() as $step => $role) {
                        if ($role === $user->role->value) {
                            $q->orWhere(fn (Builder $q) => $q->where('type', $type)->where('step', $step));
                        }
                    }
                }
            });

        if ($user->role === Role::Manager) {
            $query->whereIn('user_id', $user->bawahan()->select('id'));
        }
    }

    // -------------------------------------------------------------- Workflow

    /** draft -> pending. Aturan bisnis per jenis dicek di sini. */
    public function submit(): void
    {
        $this->locked(function (self $s) {
            throw_unless(
                $s->status === SubmissionStatus::Draft,
                AuthorizationException::class,
                'Pengajuan ini sudah pernah diajukan.',
            );

            $s->assertCanSubmit();

            // BR-02: pemohon tanpa atasan melewati step manager.
            // ponytail: mengandaikan step manager hanya ada di posisi pertama dan
            // bukan satu-satunya step; ubah di sini jika rantai di config berubah bentuk.
            $s->step = ($s->chain()[0] === Role::Manager->value && $s->user->atasan_id === null) ? 1 : 0;
            $s->status = SubmissionStatus::Pending;
            $s->submitted_at = now();
            $s->save();

            $s->log($s->user, 'submitted');
        });
    }

    public function approve(User $actor, ?string $note = null): void
    {
        $this->locked(function (self $s) use ($actor, $note) {
            $s->assertActor($actor);
            $s->log($actor, 'approved', $note);

            $s->step++;

            if ($s->step >= count($s->chain())) {
                $s->status = SubmissionStatus::Approved;

                if ($s->type === SubmissionType::Cuti) {
                    $s->deductLeave();
                }
            }

            $s->save();
        });
    }

    public function reject(User $actor, string $note): void
    {
        $this->locked(function (self $s) use ($actor, $note) {
            $s->assertActor($actor);
            $s->log($actor, 'rejected', $note);

            $s->status = SubmissionStatus::Rejected;
            $s->save();
        });
    }

    public function cancel(User $actor): void
    {
        $this->locked(function (self $s) use ($actor) {
            throw_unless(
                $s->canBeCancelledBy($actor),
                AuthorizationException::class,
                'Pengajuan ini tidak bisa dibatalkan.',
            );

            $s->log($actor, 'cancelled');

            $s->status = SubmissionStatus::Cancelled;
            $s->save();
        });
    }

    /** BR-01 sampai BR-03: siapa yang boleh approve/reject step saat ini. */
    public function canBeActedBy(User $actor): bool
    {
        if ($this->status !== SubmissionStatus::Pending || $actor->id === $this->user_id) {
            return false;
        }

        $role = $this->currentRole();

        if ($actor->role->value !== $role) {
            return false;
        }

        return $role !== Role::Manager->value || $this->user->atasan_id === $actor->id;
    }

    /** Pemohon boleh membatalkan draft, atau pengajuan yang belum disetujui siapa pun. */
    public function canBeCancelledBy(User $actor): bool
    {
        if ($actor->id !== $this->user_id) {
            return false;
        }

        return $this->status === SubmissionStatus::Draft
            || ($this->status === SubmissionStatus::Pending
                && ! $this->logs()->where('action', 'approved')->exists());
    }

    // ---------------------------------------------------------------- Helper

    /** @return list<string> */
    public function chain(): array
    {
        return $this->type->chain();
    }

    /** Role yang sedang ditunggu, atau null jika rantai sudah selesai. */
    public function currentRole(): ?string
    {
        return $this->chain()[$this->step] ?? null;
    }

    public function statusLabel(): string
    {
        if ($this->status === SubmissionStatus::Pending && $this->currentRole()) {
            return 'Menunggu '.Role::from($this->currentRole())->label();
        }

        return $this->status->label();
    }

    /** BR-08: hari Senin sampai Jumat dari $start sampai $end, inklusif. */
    public static function countWorkdays(CarbonInterface $start, CarbonInterface $end): int
    {
        $days = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }

    public function rupiah(): string
    {
        return 'Rp'.number_format((int) $this->amount, 0, ',', '.');
    }

    /** BR-18: nomor surat diturunkan dari id, jadi pasti unik. */
    public function letterNumber(): string
    {
        return sprintf('SK/%d/%06d', $this->submitted_at->year, $this->id);
    }

    // --------------------------------------------------------- Aturan bisnis

    /**
     * BR-07: jalankan aksi pada salinan baris yang dikunci di dalam transaksi,
     * supaya klik ganda atau dua approver bersamaan tidak memproses dua kali.
     */
    private function locked(callable $action): void
    {
        DB::transaction(fn () => $action(self::lockForUpdate()->findOrFail($this->id)));

        $this->refresh();
    }

    private function assertActor(User $actor): void
    {
        throw_unless(
            $this->canBeActedBy($actor),
            AuthorizationException::class,
            'Anda tidak berhak memproses pengajuan ini.',
        );
    }

    private function log(User $actor, string $action, ?string $note = null): void
    {
        $this->logs()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'step' => $this->step,
            'note' => $note,
        ]);
    }

    private function assertCanSubmit(): void
    {
        $errors = match ($this->type) {
            SubmissionType::Cuti => $this->cutiErrors(),
            SubmissionType::Reimbursement => $this->reimbursementErrors(),
            SubmissionType::Surat => [],
        };

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, string> */
    private function cutiErrors(): array
    {
        $pendingDays = (int) self::where('user_id', $this->user_id)
            ->where('type', SubmissionType::Cuti)
            ->where('status', SubmissionStatus::Pending)
            ->sum('days');

        $available = $this->user->sisa_cuti - $pendingDays;

        return array_filter([
            // BR-09
            'start_date' => $this->start_date->lt(today()) ? 'Tanggal mulai tidak boleh di masa lalu.' : null,
            'end_date' => $this->days < 1 ? 'Rentang tanggal tidak berisi hari kerja.' : null,
            'cuti' => match (true) {
                // BR-10
                $this->hasOverlap() => 'Tanggal bentrok dengan pengajuan cuti Anda yang lain.',
                // BR-11
                $this->days > $available => "Sisa cuti tidak cukup: tersedia {$available} hari, diminta {$this->days} hari.",
                default => null,
            },
        ]);
    }

    /** @return array<string, string> */
    private function reimbursementErrors(): array
    {
        $limit = config('reimbursement.kategori.'.($this->data['kategori'] ?? ''));
        $maxHari = config('reimbursement.max_hari_pengajuan');
        $tanggal = Carbon::parse($this->data['tanggal_transaksi']);

        return array_filter([
            // BR-13
            'attachment' => $this->attachment_path ? null : 'Nota wajib diunggah.',
            // BR-16
            'tanggal_transaksi' => $tanggal->lt(today()->subDays($maxHari))
                ? "Pengajuan maksimal {$maxHari} hari setelah tanggal transaksi."
                : null,
            // BR-17
            'amount' => $limit !== null && $this->amount > $limit
                ? 'Nominal melebihi batas kategori ini (Rp'.number_format($limit, 0, ',', '.').').'
                : null,
        ]);
    }

    /** BR-10: bentrok dengan cuti lain milik pemohon yang pending/approved. */
    private function hasOverlap(): bool
    {
        return self::where('user_id', $this->user_id)
            ->where('type', SubmissionType::Cuti)
            ->whereIn('status', [SubmissionStatus::Pending, SubmissionStatus::Approved])
            ->whereKeyNot($this->id)
            ->whereDate('start_date', '<=', $this->end_date->toDateString())
            ->whereDate('end_date', '>=', $this->start_date->toDateString())
            ->exists();
    }

    /** BR-12: potong kuota di transaksi yang sama, dengan baris user dikunci. */
    private function deductLeave(): void
    {
        $owner = User::lockForUpdate()->findOrFail($this->user_id);

        if ($this->hasOverlap()) {
            throw ValidationException::withMessages([
                'approval' => 'Tanggal cuti bentrok dengan cuti lain milik pemohon.',
            ]);
        }

        if ($owner->sisa_cuti < $this->days) {
            throw ValidationException::withMessages([
                'approval' => "Sisa cuti pemohon ({$owner->sisa_cuti} hari) tidak cukup untuk {$this->days} hari.",
            ]);
        }

        $owner->decrement('sisa_cuti', $this->days);
    }
}
