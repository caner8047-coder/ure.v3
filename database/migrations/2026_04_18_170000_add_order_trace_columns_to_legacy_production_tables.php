<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTraceColumns('tbBolumHavuz');
        $this->addTraceColumns('tbPersonelGorev');
        $this->addTraceColumns('tbGorevler');

        // Kök iş emri kayıtlarında eşleşme nettir: tbSiparisSatir.GorevNo -> tbGorevler.No
        DB::statement("
            UPDATE tbGorevler gr
            INNER JOIN tbSiparisSatir ss ON ss.GorevNo = gr.No
            SET
                gr.SiparisSatirNo = ss.No,
                gr.SiparisNo = ss.SiparisNo
            WHERE
                (gr.SiparisSatirNo IS NULL OR gr.SiparisSatirNo = 0)
        ");
    }

    public function down(): void
    {
        $this->dropTraceColumns('tbBolumHavuz');
        $this->dropTraceColumns('tbPersonelGorev');
        $this->dropTraceColumns('tbGorevler');
    }

    private function addTraceColumns(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (!Schema::hasColumn($table, 'SiparisSatirNo')) {
                $blueprint->integer('SiparisSatirNo')->nullable()->after('UrunIDNo');
            }

            if (!Schema::hasColumn($table, 'SiparisNo')) {
                $blueprint->string('SiparisNo', 50)->nullable()->after('SiparisSatirNo');
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (!$this->indexExists($table, $table . '_siparis_satir_no_idx')) {
                $blueprint->index('SiparisSatirNo', $table . '_siparis_satir_no_idx');
            }

            if (!$this->indexExists($table, $table . '_siparis_no_idx')) {
                $blueprint->index('SiparisNo', $table . '_siparis_no_idx');
            }
        });
    }

    private function dropTraceColumns(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if ($this->indexExists($table, $table . '_siparis_satir_no_idx')) {
                $blueprint->dropIndex($table . '_siparis_satir_no_idx');
            }

            if ($this->indexExists($table, $table . '_siparis_no_idx')) {
                $blueprint->dropIndex($table . '_siparis_no_idx');
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (Schema::hasColumn($table, 'SiparisNo')) {
                $blueprint->dropColumn('SiparisNo');
            }

            if (Schema::hasColumn($table, 'SiparisSatirNo')) {
                $blueprint->dropColumn('SiparisSatirNo');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return intval($result->cnt ?? 0) > 0;
    }
};
