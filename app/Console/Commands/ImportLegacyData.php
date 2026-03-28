<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Department;
use App\Models\User;
use App\Models\Product;
use App\Models\Component;
use App\Models\Task;
use App\Models\ProductionPool;
use App\Models\DepartmentStock;
use App\Models\OrderItem;

class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy-data {--path=storage/app/legacy_data : Path to CSV folder}';
    protected $description = 'Imports ALL V1 and V2 MSSQL data from CSV files into MySQL structure';

    public function handle()
    {
        $path = base_path($this->option('path'));
        if (!is_dir($path)) {
            $this->error("Directory not found at: {$path}");
            return 1;
        }

        $this->info("Importing Comprehensive Legacy Data from {$path}...");

        DB::beginTransaction();
        try {
            $this->importDepartments($path . '/tbBolum.csv');
            $this->importPersonnel($path . '/tbPersonel.csv');
            $this->importProducts($path . '/tbUrunler.csv');
            $this->importComponents($path . '/tbAraUrun.csv');
            $this->importDepartmentStocks($path . '/tbBolumAraStok.csv');
            $this->importProductionPools($path . '/tbBolumHavuz.csv');
            $this->importTasks($path . '/tbPersonelGorev.csv');
            $this->importOrders($path . '/tbSiparisSatir.csv');
            
            DB::commit();
            $this->info("All Data imported flawlessly!");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import Failed: " . $e->getMessage());
            return 1;
        }
        return 0;
    }

    private function readCsv(string $file): array
    {
        if (!file_exists($file)) return [];
        $data = array_map('str_getcsv', file($file));
        if (empty($data)) return [];
        $header = array_shift($data);
        $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]); // BOM Fix
        return array_map(function($row) use ($header) {
            if(count($row) < count($header)) $row = array_pad($row, count($header), null);
            return array_combine($header, $row);
        }, $data);
    }

    private function importDepartments(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue; 
            DB::table('departments')->updateOrInsert(
                ['id' => $row['No']],
                ['name' => $row['BolumAdi'], 'bolum_no' => $row['No']] 
            );
        }
        $this->info("Departments: " . count($rows));
    }

    private function importPersonnel(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['Mail'])) continue;
            DB::table('users')->updateOrInsert(
                ['email' => trim($row['Mail'])],
                [
                    'personnel_no' => $row['PersonelNo'] ?? null,
                    'name' => $row['Ad'],
                    'surname' => $row['Soyad'] ?? '',
                    'password' => $row['Sifre'] ?? '', 
                    'department_id' => ($row['BolumAdiNo'] == 0) ? null : $row['BolumAdiNo']
                ]
            );
        }
        $this->info("Personnel: " . count($rows));
    }

    // Extended with missing V1 fields
    private function importProducts(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue;
            DB::table('products')->updateOrInsert(
                ['id' => $row['No']],
                [
                    'urun_id' => $row['UrunID'] ?? null, 
                    'system_name' => $row['SistemAdi'] ?? null,
                    'system_code' => $row['SistemKodu'] ?? null,
                    'path' => $row['AraAdlarYol'] ?? null
                ]
            );
        }
        $this->info("Products: " . count($rows));
    }

    private function importComponents(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue;
            DB::table('components')->updateOrInsert(
                ['id' => $row['No']],
                [
                    'name' => $row['AraUrunAdi'],
                    'performance' => $row['Performans'] ?? 0,
                    'department_id' => $row['BolumAdiNo'] ?: null,
                    'min_quantity' => $row['MinAdet'] ?? 0,
                    'category' => $row['UrunCesidi'] ?? null,
                    'path' => $row['Yol'] ?? null,
                    'image' => $row['Resim'] ?? null
                ]
            );
        }
        $this->info("Components: " . count($rows));
    }

    private function importDepartmentStocks(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue;
            DB::table('department_stocks')->updateOrInsert(
                ['id' => $row['No']],
                [
                    'department_id' => $row['BolumAdiNo'],
                    'component_id' => $row['AraUrunAdiNo'],
                    'product_id' => $row['UrunIDNo'] ?: null,
                    'quantity' => $row['Adet'] ?? 0,
                    'buffer_quantity' => $row['TamponMiktar'] ?? 0
                ]
            );
        }
        $this->info("Stocks: " . count($rows));
    }

    private function importProductionPools(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue;
            DB::table('production_pools')->updateOrInsert(
                ['id' => $row['No']],
                [
                    'product_id' => $row['UrunIDNo'] ?: null,
                    'task_start_date' => $row['GorevBaslangicTarihi'] ?? null,
                    'department_id' => $row['BolumAdiNo'] ?: null,
                    'component_id' => $row['AraUrunAdiNo'] ?: null,
                    'quantity' => $row['Adet'] ?? 0,
                    'total_quantity' => $row['ToplamAdet'] ?? 0,
                    'step_order' => $row['AdimSirasi'] ?? 0
                ]
            );
        }
        $this->info("Production Pools: " . count($rows));
    }

    private function importTasks(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue;
            DB::table('tasks')->updateOrInsert(
                ['id' => $row['No']],
                [
                    'product_id' => $row['UrunIDNo'] ?: null,
                    'user_id' => $row['PersonelNo'],
                    'quantity' => $row['Adet'] ?? 0,
                    'pending_quantity' => $row['BekleyenAdet'] ?? 0,
                    'approval' => $row['Onay'] ?? null,
                    'component_id' => $row['AraUrunAdiNo'] ?: null
                ]
            );
        }
        $this->info("Tasks: " . count($rows));
    }

    private function importOrders(string $file) {
        $rows = $this->readCsv($file);
        foreach ($rows as $row) {
            if(!isset($row['No'])) continue;
            DB::table('order_items')->updateOrInsert(
                ['id' => $row['No']],
                [
                    'order_no' => $row['SiparisNo'],
                    'marketplace' => $row['Pazaryeri'] ?? null,
                    'store' => $row['Magaza'] ?? null,
                    'customer' => $row['Musteri'] ?? null,
                    'product_name' => $row['UrunAdi'],
                    'quantity' => $row['Adet'] ?? 1,
                    'status' => $row['Durum'] ?? 'UretimBekliyor'
                ]
            );
        }
        $this->info("Orders: " . count($rows));
    }
}
