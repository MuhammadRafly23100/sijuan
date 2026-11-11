<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Menguji aturan bisnis di docs/PERANCANGAN.md lewat HTTP, sehingga route,
 * policy, validasi, dan workflow teruji sekaligus.
 */
class SubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $manager;

    private User $hr;

    private User $hr2;

    private User $finance;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu. Senin berikutnya 2026-11-16.
        $this->travelTo(Carbon::parse('2026-11-11 09:00:00'));

        Storage::fake('local');

        $this->director = User::factory()->create(['role' => Role::Manager]);
        $this->manager = User::factory()->create(['role' => Role::Manager, 'atasan_id' => $this->director->id]);
        $this->hr = User::factory()->create(['role' => Role::Hr]);
        $this->hr2 = User::factory()->create(['role' => Role::Hr]);
        $this->finance = User::factory()->create(['role' => Role::Finance]);
        $this->staff = User::factory()->create(['atasan_id' => $this->manager->id]);
    }

    // ------------------------------------------------------------- BR umum

    public function test_pemohon_tidak_bisa_menyetujui_pengajuannya_sendiri(): void
    {
        $surat = $this->createSurat($this->hr);
        $this->submit($surat)->assertSessionHasNoErrors();

        $this->approve($this->hr, $surat)->assertForbidden();
        $this->assertSame(SubmissionStatus::Pending, $surat->fresh()->status);

        $this->approve($this->hr2, $surat)->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Approved, $surat->fresh()->status);
    }

    public function test_manager_hanya_bisa_memproses_pengajuan_bawahannya(): void
    {
        $cuti = $this->createCuti($this->staff, '2026-11-16', '2026-11-18');
        $this->submit($cuti);

        // Direktur ber-role manager, tetapi bukan atasan langsung staff.
        $this->approve($this->director, $cuti)->assertForbidden();
        $this->assertSame(0, $cuti->fresh()->step);

        $this->approve($this->manager, $cuti)->assertSessionHasNoErrors();
        $this->assertSame(1, $cuti->fresh()->step);
    }

    public function test_alur_cuti_penuh_memotong_kuota(): void
    {
        $cuti = $this->createCuti($this->staff, '2026-11-16', '2026-11-18');
        $this->assertSame(3, $cuti->days);

        $this->submit($cuti)->assertSessionHasNoErrors();
        $this->approve($this->manager, $cuti);

        $cuti->refresh();
        $this->assertSame(SubmissionStatus::Pending, $cuti->status);
        $this->assertSame(12, $this->staff->fresh()->sisa_cuti);

        $this->approve($this->hr, $cuti)->assertSessionHasNoErrors();

        $cuti->refresh();
        $this->assertSame(SubmissionStatus::Approved, $cuti->status);
        $this->assertSame(9, $this->staff->fresh()->sisa_cuti);
        $this->assertSame(['submitted', 'approved', 'approved'], $cuti->logs->pluck('action')->all());
    }

    public function test_menolak_wajib_menyertakan_catatan(): void
    {
        $cuti = $this->createCuti($this->staff, '2026-11-16', '2026-11-18');
        $this->submit($cuti);

        $this->reject($this->manager, $cuti, null)->assertSessionHasErrors('note');
        $this->assertSame(SubmissionStatus::Pending, $cuti->fresh()->status);

        $this->reject($this->manager, $cuti, 'Jadwal bentrok dengan rilis.')->assertSessionHasNoErrors();

        $cuti->refresh();
        $this->assertSame(SubmissionStatus::Rejected, $cuti->status);
        $this->assertSame('Jadwal bentrok dengan rilis.', $cuti->logs->last()->note);
        $this->assertSame(12, $this->staff->fresh()->sisa_cuti);
    }

    public function test_pemohon_tanpa_atasan_melewati_step_manager(): void
    {
        $cuti = $this->createCuti($this->director, '2026-11-16', '2026-11-16');
        $this->submit($cuti)->assertSessionHasNoErrors();

        $this->assertSame(1, $cuti->fresh()->step);

        $this->approve($this->hr, $cuti)->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Approved, $cuti->fresh()->status);
    }

    public function test_pembatalan_hanya_sebelum_ada_yang_menyetujui(): void
    {
        $first = $this->createCuti($this->staff, '2026-11-16', '2026-11-16');
        $this->submit($first);
        $this->actingAs($this->staff)->post(route('submissions.cancel', $first))->assertSessionHasNoErrors();
        $this->assertSame(SubmissionStatus::Cancelled, $first->fresh()->status);

        $second = $this->createCuti($this->staff, '2026-11-17', '2026-11-17');
        $this->submit($second);
        $this->approve($this->manager, $second);
        $this->actingAs($this->staff)->post(route('submissions.cancel', $second))->assertForbidden();
        $this->assertSame(SubmissionStatus::Pending, $second->fresh()->status);
    }

    public function test_draft_tidak_terlihat_dan_tidak_bisa_diubah_orang_lain(): void
    {
        $draft = $this->createCuti($this->staff, '2026-11-16', '2026-11-16');

        $this->actingAs($this->manager)->get(route('submissions.show', $draft))->assertForbidden();
        $this->actingAs($this->hr)->get(route('submissions.show', $draft))->assertForbidden();
        $this->actingAs($this->manager)->post(route('submissions.submit', $draft))->assertForbidden();

        // BR-05: setelah diajukan, pemilik pun tidak bisa mengedit.
        $this->submit($draft);
        $this->actingAs($this->staff)->get(route('submissions.edit', $draft))->assertForbidden();
    }

    // ---------------------------------------------------------------- Cuti

    public function test_cuti_bentrok_ditolak_saat_submit(): void
    {
        $this->submit($this->createCuti($this->staff, '2026-11-16', '2026-11-18'));

        $overlap = $this->createCuti($this->staff, '2026-11-18', '2026-11-19');
        $this->submit($overlap)->assertSessionHasErrors('cuti');

        $this->assertSame(SubmissionStatus::Draft, $overlap->fresh()->status);
    }

    public function test_cuti_melebihi_kuota_ditolak_saat_submit(): void
    {
        $this->staff->update(['sisa_cuti' => 4]);

        // 3 hari pending + 2 hari baru melebihi sisa 4 hari (BR-11).
        $this->submit($this->createCuti($this->staff, '2026-11-16', '2026-11-18'))->assertSessionHasNoErrors();

        $second = $this->createCuti($this->staff, '2026-11-23', '2026-11-24');
        $this->submit($second)->assertSessionHasErrors('cuti');

        $this->assertSame(SubmissionStatus::Draft, $second->fresh()->status);
    }

    public function test_cuti_di_masa_lalu_ditolak_saat_submit(): void
    {
        $cuti = $this->createCuti($this->staff, '2026-11-09', '2026-11-10');

        $this->submit($cuti)->assertSessionHasErrors('start_date');
    }

    public function test_approval_final_gagal_jika_kuota_tidak_cukup(): void
    {
        $cuti = $this->createCuti($this->staff, '2026-11-16', '2026-11-18');
        $this->submit($cuti);
        $this->approve($this->manager, $cuti);

        $this->staff->update(['sisa_cuti' => 1]);

        $this->approve($this->hr, $cuti)->assertSessionHasErrors('approval');

        // Seluruh aksi dibatalkan: status, step, kuota, dan log tidak berubah.
        $cuti->refresh();
        $this->assertSame(SubmissionStatus::Pending, $cuti->status);
        $this->assertSame(1, $cuti->step);
        $this->assertSame(1, $this->staff->fresh()->sisa_cuti);
        $this->assertCount(2, $cuti->logs);
    }

    // ------------------------------------------------------- Reimbursement

    public function test_nota_hanya_bisa_dibuka_pihak_terkait(): void
    {
        $klaim = $this->createReimbursement($this->staff);
        $this->submit($klaim)->assertSessionHasNoErrors();

        $rekan = User::factory()->create(['atasan_id' => $this->manager->id]);
        $url = route('submissions.attachment', $klaim);

        $this->actingAs($rekan)->get($url)->assertForbidden();
        $this->actingAs($this->hr)->get($url)->assertForbidden();
        $this->actingAs($this->director)->get($url)->assertForbidden();

        $this->actingAs($this->staff)->get($url)->assertOk();
        $this->actingAs($this->manager)->get($url)->assertOk();
        $this->actingAs($this->finance)->get($url)->assertOk();
    }

    public function test_reimbursement_tanpa_nota_ditolak(): void
    {
        $this->actingAs($this->staff)->post(route('submissions.store'), [
            'type' => 'reimbursement',
            'title' => 'Parkir',
            'amount' => 10_000,
            'kategori' => 'transportasi',
            'tanggal_transaksi' => '2026-11-10',
        ])->assertSessionHasErrors('attachment');

        $this->assertSame(0, Submission::count());
    }

    public function test_reimbursement_melebihi_batas_kategori_ditolak_saat_submit(): void
    {
        $klaim = $this->createReimbursement($this->staff, ['kategori' => 'makan_entertain', 'amount' => 600_000]);

        $this->submit($klaim)->assertSessionHasErrors('amount');
        $this->assertSame(SubmissionStatus::Draft, $klaim->fresh()->status);
    }

    public function test_reimbursement_lewat_30_hari_ditolak_saat_submit(): void
    {
        // Tepat 30 hari yang lalu masih boleh; 31 hari tidak.
        $batas = $this->createReimbursement($this->staff, ['tanggal_transaksi' => '2026-10-12']);
        $this->submit($batas)->assertSessionHasNoErrors();

        $telat = $this->createReimbursement($this->staff, ['tanggal_transaksi' => '2026-10-11']);
        $this->submit($telat)->assertSessionHasErrors('tanggal_transaksi');
        $this->assertSame(SubmissionStatus::Draft, $telat->fresh()->status);
    }

    public function test_alur_reimbursement_manager_lalu_finance(): void
    {
        $klaim = $this->createReimbursement($this->staff);
        $this->submit($klaim);

        $this->approve($this->finance, $klaim)->assertForbidden();
        $this->approve($this->manager, $klaim)->assertSessionHasNoErrors();
        $this->approve($this->hr, $klaim)->assertForbidden();
        $this->approve($this->finance, $klaim)->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStatus::Approved, $klaim->fresh()->status);
    }

    // --------------------------------------------------- Surat dan laporan

    public function test_surat_yang_disetujui_bisa_diunduh_sebagai_pdf(): void
    {
        $surat = $this->createSurat($this->staff);
        $this->submit($surat);
        $url = route('submissions.letter', $surat);

        $this->actingAs($this->staff)->get($url)->assertForbidden();

        $this->approve($this->hr, $surat);

        $this->actingAs($this->hr)->get($url)->assertForbidden();
        $this->actingAs($this->staff)->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rekap_csv_hanya_untuk_hr_dan_finance(): void
    {
        $this->submit($this->createCuti($this->staff, '2026-11-16', '2026-11-18'));
        $this->submit($this->createReimbursement($this->staff));
        $url = route('reports.monthly', ['month' => '2026-11']);

        $this->actingAs($this->staff)->get($url)->assertForbidden();
        $this->actingAs($this->manager)->get($url)->assertForbidden();

        $hr = $this->actingAs($this->hr)->get($url)->assertOk()->streamedContent();
        $this->assertStringContainsString('Cuti tahunan', $hr);
        $this->assertStringNotContainsString('Taksi', $hr);

        $finance = $this->actingAs($this->finance)->get($url)->assertOk()->streamedContent();
        $this->assertStringContainsString('Taksi', $finance);
        $this->assertStringNotContainsString('Cuti tahunan', $finance);
    }

    // ------------------------------------------------------------- Halaman

    public function test_tamu_diarahkan_ke_login_dan_bisa_masuk(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();

        $this->post(route('login'), ['email' => $this->staff->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => $this->staff->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_semua_halaman_bisa_dirender(): void
    {
        $cuti = $this->createCuti($this->staff, '2026-11-16', '2026-11-18');
        $klaim = $this->createReimbursement($this->staff);
        $surat = $this->createSurat($this->staff);

        foreach (['cuti', 'reimbursement', 'surat'] as $type) {
            $this->actingAs($this->staff)->get(route('submissions.create', $type))->assertOk();
        }

        foreach ([$cuti, $klaim, $surat] as $submission) {
            $this->actingAs($this->staff)->get(route('submissions.edit', $submission))->assertOk();
            $this->actingAs($this->staff)->get(route('submissions.show', $submission))->assertOk();
            $this->submit($submission)->assertSessionHasNoErrors();
        }

        $this->approve($this->manager, $cuti);
        $this->reject($this->manager, $klaim, 'Nota kurang jelas.');
        $this->approve($this->hr, $surat);

        foreach ([$this->staff, $this->manager, $this->hr, $this->finance] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->actingAs($user)->get(route('submissions.index', ['type' => 'cuti', 'status' => 'pending']))->assertOk();
        }

        foreach ([$cuti, $klaim, $surat] as $submission) {
            $this->actingAs($this->staff)->get(route('submissions.show', $submission))->assertOk();
        }

        $this->actingAs($this->hr)->get(route('submissions.show', $cuti))->assertOk()->assertSee('Keputusan Anda');
        $this->actingAs($this->manager)->get(route('dashboard'))->assertDontSee('Cuti tahunan');
        $this->actingAs($this->hr)->get(route('dashboard'))->assertSee('Cuti tahunan');
    }

    // -------------------------------------------------------------- Helper

    private function createCuti(User $owner, string $start, string $end): Submission
    {
        return $this->store($owner, [
            'type' => 'cuti',
            'title' => 'Cuti tahunan',
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function createReimbursement(User $owner, array $overrides = []): Submission
    {
        return $this->store($owner, $overrides + [
            'type' => 'reimbursement',
            'title' => 'Taksi ke klien',
            'amount' => 100_000,
            'kategori' => 'transportasi',
            'tanggal_transaksi' => '2026-11-10',
            'attachment' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
        ]);
    }

    private function createSurat(User $owner): Submission
    {
        return $this->store($owner, [
            'type' => 'surat',
            'title' => 'Surat untuk KPR',
            'keperluan' => 'Pengajuan KPR',
        ]);
    }

    private function store(User $owner, array $input): Submission
    {
        $this->actingAs($owner)->post(route('submissions.store'), $input)->assertSessionHasNoErrors();

        return Submission::latest('id')->firstOrFail();
    }

    private function submit(Submission $submission): TestResponse
    {
        return $this->actingAs($submission->user)->post(route('submissions.submit', $submission));
    }

    private function approve(User $actor, Submission $submission): TestResponse
    {
        return $this->actingAs($actor)->post(route('submissions.approve', $submission));
    }

    private function reject(User $actor, Submission $submission, ?string $note): TestResponse
    {
        return $this->actingAs($actor)->post(route('submissions.reject', $submission), ['note' => $note]);
    }
}
