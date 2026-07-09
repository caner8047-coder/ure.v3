<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tbSiparisSatir')) {
            return;
        }

        Schema::table('tbSiparisSatir', function (Blueprint $table) {
            if (!Schema::hasColumn('tbSiparisSatir', 'AnaStokUretimNo')) {
                $table->integer('AnaStokUretimNo')->nullable();
            }

            if (!Schema::hasColumn('tbSiparisSatir', 'StokUretimTipi')) {
                $table->string('StokUretimTipi', 20)->nullable();
            }

            if (!Schema::hasColumn('tbSiparisSatir', 'StokUretimIlaveSira')) {
                $table->integer('StokUretimIlaveSira')->nullable();
            }
        });

        $updates = [];
        if (Schema::hasColumn('tbSiparisSatir', 'AnaStokUretimNo')) {
            $updates['AnaStokUretimNo'] = DB::raw('No');
        }
        if (Schema::hasColumn('tbSiparisSatir', 'StokUretimTipi')) {
            $updates['StokUretimTipi'] = DB::raw("CASE WHEN StokUretimTipi IS NULL OR StokUretimTipi = '' THEN 'ana' ELSE StokUretimTipi END");
        }
        if (Schema::hasColumn('tbSiparisSatir', 'StokUretimIlaveSira')) {
            $updates['StokUretimIlaveSira'] = DB::raw('CASE WHEN StokUretimIlaveSira IS NULL THEN 0 ELSE StokUretimIlaveSira END');
        }

        if (!empty($updates) && Schema::hasColumn('tbSiparisSatir', 'AnaStokUretimNo')) {
            DB::table('tbSiparisSatir')
                ->where('Musteri', 'LIKE', 'ÖZEL ÜRETİM (SERBEST%')
                ->where(function ($query) {
                    $query->whereNull('AnaStokUretimNo')
                        ->orWhere('AnaStokUretimNo', 0);
                })
                ->update($updates);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tbSiparisSatir')) {
            return;
        }

        Schema::table('tbSiparisSatir', function (Blueprint $table) {
            if (Schema::hasColumn('tbSiparisSatir', 'StokUretimIlaveSira')) {
                $table->dropColumn('StokUretimIlaveSira');
            }

            if (Schema::hasColumn('tbSiparisSatir', 'StokUretimTipi')) {
                $table->dropColumn('StokUretimTipi');
            }

            if (Schema::hasColumn('tbSiparisSatir', 'AnaStokUretimNo')) {
                $table->dropColumn('AnaStokUretimNo');
            }
        });
    }
};
