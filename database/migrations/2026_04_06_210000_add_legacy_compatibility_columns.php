<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbBolumHavuz', function (Blueprint $table) {
            if (!Schema::hasColumn('tbBolumHavuz', 'Aciklama')) {
                $table->text('Aciklama')->nullable()->after('BolumAdiNo');
            }

            if (!Schema::hasColumn('tbBolumHavuz', 'GorevBaslangicSaati')) {
                $table->string('GorevBaslangicSaati', 50)->nullable()->after('GorevBaslangicTarihi');
            }
        });

        Schema::table('tbGorevler', function (Blueprint $table) {
            if (!Schema::hasColumn('tbGorevler', 'Performans')) {
                $table->integer('Performans')->nullable()->default(0)->after('PersonelNo');
            }
        });

        Schema::table('tbPersonelGorev', function (Blueprint $table) {
            if (!Schema::hasColumn('tbPersonelGorev', 'GorevBaslamaTarihi')) {
                $table->string('GorevBaslamaTarihi', 50)->nullable()->after('UrunIDNo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbPersonelGorev', function (Blueprint $table) {
            if (Schema::hasColumn('tbPersonelGorev', 'GorevBaslamaTarihi')) {
                $table->dropColumn('GorevBaslamaTarihi');
            }
        });

        Schema::table('tbGorevler', function (Blueprint $table) {
            if (Schema::hasColumn('tbGorevler', 'Performans')) {
                $table->dropColumn('Performans');
            }
        });

        Schema::table('tbBolumHavuz', function (Blueprint $table) {
            if (Schema::hasColumn('tbBolumHavuz', 'GorevBaslangicSaati')) {
                $table->dropColumn('GorevBaslangicSaati');
            }

            if (Schema::hasColumn('tbBolumHavuz', 'Aciklama')) {
                $table->dropColumn('Aciklama');
            }
        });
    }
};
