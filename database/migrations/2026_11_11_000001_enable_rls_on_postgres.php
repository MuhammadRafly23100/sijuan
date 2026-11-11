<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hanya untuk PostgreSQL di Supabase. Supabase bisa membuka tabel di schema
 * "public" lewat Data API-nya; tanpa RLS, tabel seperti users bisa terbaca dari
 * luar. Mengaktifkan RLS tanpa policy menutup akses itu, sedangkan aplikasi
 * tetap jalan karena terhubung sebagai pemilik tabel.
 *
 * ponytail: hanya mencakup tabel yang ada saat migrasi ini jalan. Migrasi
 * berikutnya yang membuat tabel baru perlu mengaktifkan RLS sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setRowLevelSecurity('enable');
    }

    public function down(): void
    {
        $this->setRowLevelSecurity('disable');
    }

    private function setRowLevelSecurity(string $action): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (Schema::getTables() as $table) {
            if ($table['schema'] === 'public') {
                DB::statement(sprintf('alter table "public"."%s" %s row level security', $table['name'], $action));
            }
        }
    }
};
