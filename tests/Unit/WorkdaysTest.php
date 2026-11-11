<?php

namespace Tests\Unit;

use App\Models\Submission;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WorkdaysTest extends TestCase
{
    /** BR-08: hanya Senin sampai Jumat yang dihitung, kedua ujung inklusif. */
    public function test_hari_kerja_tidak_menghitung_akhir_pekan(): void
    {
        $jumat = Carbon::parse('2026-11-13');
        $senin = Carbon::parse('2026-11-16');

        $this->assertSame(2, Submission::countWorkdays($jumat, $senin));
        $this->assertSame(1, Submission::countWorkdays($senin, $senin));
        $this->assertSame(5, Submission::countWorkdays($senin, Carbon::parse('2026-11-22')));
        $this->assertSame(0, Submission::countWorkdays(Carbon::parse('2026-11-14'), Carbon::parse('2026-11-15')));
    }
}
