<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminImportController extends Controller
{
    public function importModule(Request $request, string $module)
    {
        if (!in_array($module, ['personnel', 'departments', 'components', 'products'], true)) {
            return response()->json(['success' => false, 'message' => 'Geçersiz içeri aktarma modülü.'], 404);
        }

        $rows = collect($request->input('rows', []))
            ->filter(fn ($row) => is_array($row) && $this->importRowHasContent($row))
            ->values();

        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'İçeri aktarılacak satır bulunamadı.'], 422);
        }

        $summary = ['success' => true, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $nextPersonnelNo = $module === 'personnel' ? ((int) (DB::table('tbPersonel')->max('PersonelNo') ?? 0)) + 1 : 1;

        DB::transaction(function () use ($rows, $module, &$summary, &$nextPersonnelNo) {
            foreach ($rows as $index => $row) {
                try {
                    $result = match ($module) {
                        'personnel' => $this->importPersonnelRow($row, $nextPersonnelNo),
                        'departments' => $this->importDepartmentRow($row),
                        'components' => $this->importComponentRow($row),
                        'products' => $this->importProductRow($row),
                    };
                    if ($result === 'inserted') $summary['inserted']++;
                    elseif ($result === 'updated') $summary['updated']++;
                    else $summary['skipped']++;
                } catch (\Throwable $e) {
                    $summary['skipped']++;
                    if (count($summary['errors']) < 10) {
                        $summary['errors'][] = 'Satır ' . ($index + 2) . ': ' . $e->getMessage();
                    }
                }
            }
        });

        $summary['message'] = "{$summary['inserted']} eklendi, {$summary['updated']} güncellendi, {$summary['skipped']} atlandı.";
        return response()->json($summary);
    }

    private function importPersonnelRow(array $row, int &$nextPersonnelNo): string
    {
        $personnelNo = $this->importInteger($this->importValue($row, 'id', 'PersonelNo', 'Personel No', 'No'));
        $name = $this->importText($row, 'name', 'Ad', 'Personel Adı', 'Personel Adi');
        $surname = $this->importText($row, 'surname', 'Soyad');
        $address = $this->importText($row, 'address', 'Adres');
        $phone = $this->importText($row, 'phone', 'Telefon');
        $email = $this->importText($row, 'email', 'Mail', 'E-posta', 'Eposta');
        $departmentId = $this->importDepartmentId($row);

        if (!$personnelNo) {
            if ($name === null && $email === null) throw new \RuntimeException('Personel adı veya mail alanı boş.');
            $personnelNo = $nextPersonnelNo++;
        } elseif ($personnelNo >= $nextPersonnelNo) {
            $nextPersonnelNo = $personnelNo + 1;
        }

        $exists = DB::table('tbPersonel')->where('PersonelNo', $personnelNo)->exists();
        $values = [];
        if ($name !== null) $values['Ad'] = $name;
        if ($surname !== null) $values['Soyad'] = $surname;
        if ($address !== null) $values['Adres'] = $address;
        if ($phone !== null) $values['Telefon'] = $phone;
        if ($email !== null) $values['Mail'] = $email;
        if ($departmentId !== null) $values['BolumAdiNo'] = $departmentId;

        $password = $this->importText($row, 'new_password', 'password', 'Sifre', 'Şifre');
        if ($password !== null && $password !== '***') {
            $values['Sifre'] = $this->normalizeImportedPassword($password);
        }

        if ($exists) {
            if (empty($values)) return 'skipped';
            DB::table('tbPersonel')->where('PersonelNo', $personnelNo)->update($values);
            return 'updated';
        }

        DB::table('tbPersonel')->insert(array_merge([
            'PersonelNo' => $personnelNo, 'Ad' => $name ?? '', 'Soyad' => $surname ?? '',
            'Adres' => $address ?? '', 'Telefon' => $phone ?? '', 'Mail' => $email ?? '',
            'Sifre' => Hash::make('123'), 'BolumAdiNo' => $departmentId,
        ], $values));
        return 'inserted';
    }

    private function importDepartmentRow(array $row): string
    {
        $id = $this->importInteger($this->importValue($row, 'id', 'bolum_no', 'No', 'Bölüm No', 'Bolum No'));
        $name = $this->importText($row, 'name', 'BolumAdi', 'Bolum Adi', 'Bölüm Adı');
        if ($name === null) throw new \RuntimeException('Bölüm adı boş.');

        if ($id && DB::table('tbBolum')->where('No', $id)->exists()) {
            DB::table('tbBolum')->where('No', $id)->update(['BolumAdi' => $name]);
            return 'updated';
        }

        $insert = ['BolumAdi' => $name];
        if ($id) { $insert['No'] = $id; DB::table('tbBolum')->insert($insert); }
        else { DB::table('tbBolum')->insertGetId($insert, 'No'); }
        return 'inserted';
    }

    private function importComponentRow(array $row): string
    {
        $id = $this->importInteger($this->importValue($row, 'id', 'No', 'AraUrunNo', 'Ara Ürün No', 'Ara Urun No'));
        $name = $this->importText($row, 'name', 'AraUrunAdi', 'Ara Urun Adi', 'Ara Ürün Adı', 'Bileşen', 'Bilesen');
        $type = $this->importText($row, 'type', 'UrunCesidi', 'Urun Cesidi', 'Ürün Çeşidi', 'Tür', 'Tur');
        $path = $this->importText($row, 'path', 'Yol', 'BOM', 'BOM Yolu');
        $image = $this->importText($row, 'image', 'Resim');
        $departmentId = $this->importDepartmentId($row);
        $performance = $this->importInteger($this->importValue($row, 'performance_score', 'Performans'));
        $minQuantity = $this->importInteger($this->importValue($row, 'min_quantity', 'MinAdet', 'Min Adet'));

        $exists = $id ? DB::table('tbAraUrun')->where('No', $id)->exists() : false;
        if (!$exists && $name === null) throw new \RuntimeException('Ara ürün adı boş.');

        $values = [];
        if ($name !== null) $values['AraUrunAdi'] = $name;
        if ($performance !== null) $values['Performans'] = $performance;
        if ($minQuantity !== null) $values['MinAdet'] = $minQuantity;
        if ($type !== null) $values['UrunCesidi'] = $type;
        if ($path !== null) $values['Yol'] = $path;
        if ($image !== null && Schema::hasColumn('tbAraUrun', 'Resim')) $values['Resim'] = $image;
        if ($departmentId !== null) $values['BolumAdiNo'] = $departmentId ?: null;

        if ($exists) {
            if (empty($values)) return 'skipped';
            DB::table('tbAraUrun')->where('No', $id)->update($values);
            $this->syncFinalProductFromComponent($name, $type);
            return 'updated';
        }

        $insert = array_merge(['AraUrunAdi' => $name, 'Performans' => $performance ?? 0, 'MinAdet' => $minQuantity ?? 0, 'UrunCesidi' => $type ?? '', 'Yol' => $path ?? '', 'BolumAdiNo' => $departmentId ?: null], $id ? ['No' => $id] : []);
        if (Schema::hasColumn('tbAraUrun', 'Resim')) $insert['Resim'] = $image ?? '';
        if ($id) DB::table('tbAraUrun')->insert($insert); else DB::table('tbAraUrun')->insertGetId($insert, 'No');
        $this->syncFinalProductFromComponent($name, $type);
        return 'inserted';
    }

    private function importProductRow(array $row): string
    {
        $id = $this->importInteger($this->importValue($row, 'id', 'No', 'UrunNo', 'Ürün No', 'Urun No'));
        $name = $this->importText($row, 'name', 'UrunID', 'Urun ID', 'Ürün Adı', 'Urun Adi', 'Nihai Ürün Adı', 'Nihai Urun Adi');
        $systemName = $this->importText($row, 'system_name', 'SistemAdi', 'Sistem Adi', 'Sistem Adı');
        $systemCode = $this->importText($row, 'system_code', 'SistemKodu', 'Sistem Kodu');
        $path = $this->importText($row, 'path', 'AraAdlarYol', 'Ara Adlar Yol', 'Yol', 'BOM', 'BOM Yolu');
        $image = $this->importText($row, 'image', 'Resim');

        $exists = $id ? DB::table('tbUrunler')->where('No', $id)->exists() : false;
        if (!$exists && $name === null) throw new \RuntimeException('Ürün adı boş.');

        $values = [];
        if ($name !== null) $values['UrunID'] = $name;
        if ($systemName !== null) $values['SistemAdi'] = $systemName;
        if ($systemCode !== null) $values['SistemKodu'] = $systemCode;
        if ($path !== null) $values['AraAdlarYol'] = $path;
        if ($image !== null && Schema::hasColumn('tbUrunler', 'Resim')) $values['Resim'] = $image;

        if ($exists) {
            if (empty($values)) return 'skipped';
            DB::table('tbUrunler')->where('No', $id)->update($values);
            return 'updated';
        }

        $insert = array_merge(['UrunID' => $name, 'SistemAdi' => $systemName ?? '', 'SistemKodu' => $systemCode ?? '', 'AraAdlarYol' => $path ?? ''], $id ? ['No' => $id] : []);
        if ($image !== null && Schema::hasColumn('tbUrunler', 'Resim')) $insert['Resim'] = $image;
        if ($id) DB::table('tbUrunler')->insert($insert); else DB::table('tbUrunler')->insertGetId($insert, 'No');
        return 'inserted';
    }

    private function importRowHasContent(array $row): bool
    {
        foreach ($row as $value) { if (trim((string) ($value ?? '')) !== '') return true; }
        return false;
    }

    private function importValue(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) ($row[$key] ?? '')) !== '') return $row[$key];
        }
        $normalizedRow = [];
        foreach ($row as $key => $value) { $normalizedRow[$this->normalizeImportKey((string) $key)] = $value; }
        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeImportKey($key);
            if (array_key_exists($normalizedKey, $normalizedRow) && trim((string) ($normalizedRow[$normalizedKey] ?? '')) !== '') return $normalizedRow[$normalizedKey];
        }
        return null;
    }

    private function importText(array $row, string ...$keys): ?string
    {
        $value = $this->importValue($row, ...$keys);
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function importInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $normalized = str_replace(',', '.', trim((string) $value));
        if (is_numeric($normalized)) return (int) round((float) $normalized);
        $digits = preg_replace('/[^\d-]+/', '', $normalized);
        return $digits === '' ? null : (int) $digits;
    }

    private function importDepartmentId(array $row): ?int
    {
        $departmentId = $this->importInteger($this->importValue($row, 'department_id', 'BolumAdiNo', 'Bolum No', 'Bölüm No', 'department'));
        if ($departmentId !== null) return $departmentId;

        $departmentName = $this->importText($row, 'department_name', 'BolumAdi', 'Bolum Adi', 'Bölüm Adı', 'Bölüm', 'Bolum');
        if ($departmentName === null) return null;

        $resolvedId = DB::table('tbBolum')->where('BolumAdi', $departmentName)->value('No');
        return $resolvedId !== null ? (int) $resolvedId : null;
    }

    private function normalizeImportKey(string $key): string
    {
        $key = strtr($key, ['İ'=>'i','I'=>'i','ı'=>'i','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ş'=>'s','ş'=>'s','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c']);
        return preg_replace('/[^a-z0-9]+/', '', strtolower($key)) ?? '';
    }

    private function normalizeImportedPassword(string $password): string
    {
        if (str_starts_with($password, '$2y$') || str_starts_with($password, '$argon2')) return $password;
        if (preg_match('/^[a-f0-9]{64}$/i', $password)) return $password;
        return Hash::make($password);
    }

    private function syncFinalProductFromComponent(?string $name, ?string $type): void
    {
        if ($name === null || !in_array($this->normalizeImportKey((string) $type), ['nihayiurun', 'nihaiurun'], true)) return;
        $this->urunlerTablosunaEkle($name);
    }

    private function urunlerTablosunaEkle(string $urunAdi): void
    {
        $araUrunler = DB::table('tbAraUrun')->where('AraUrunAdi', $urunAdi)->get();
        if ($araUrunler->isEmpty()) return;
        $bomService = app(\App\Services\BomService::class);
        foreach ($araUrunler as $araUrun) {
            $tumYol = $bomService->tumYolHazirla((string) $araUrun->No);
            DB::table('tbUrunler')->where('UrunID', $araUrun->AraUrunAdi)->delete();
            DB::table('tbUrunler')->insert(['UrunID' => $araUrun->AraUrunAdi, 'AraAdlarYol' => $tumYol, 'SistemAdi' => $araUrun->AraUrunAdi, 'Resim' => $araUrun->Resim ?? '']);
        }
    }
}
