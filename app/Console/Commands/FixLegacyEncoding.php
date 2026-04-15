<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixLegacyEncoding extends Command
{
    protected $signature = 'legacy:fix-encoding
        {--dry-run : Show the rows that would be updated without writing}
        {--sample=20 : Number of remaining broken samples to print after processing}';

    protected $description = 'Fix Turkish replacement characters left in legacy lookup tables';

    private const TARGETS = [
        ['table' => 'tbAraUrun', 'key' => 'No', 'column' => 'AraUrunAdi'],
        ['table' => 'tbAraUrun', 'key' => 'No', 'column' => 'UrunCesidi'],
        ['table' => 'tbBolum', 'key' => 'No', 'column' => 'BolumAdi'],
        ['table' => 'tbUrunler', 'key' => 'No', 'column' => 'SistemAdi'],
    ];

    /**
     * Ordered from more-specific to more-generic so partial matches do not
     * prevent the fuller phrase from being repaired first.
     */
    private const REPLACEMENTS = [
        'i�in i�i a��lm��' => 'için içi açılmış',
        'Nihayi �r�n' => 'Nihayi Ürün',
        'Ara Mam�l' => 'Ara Mamül',
        '�r�n Depo' => 'Ürün Depo',
        '��� BO�' => 'İÇİ BOŞ',
        'k�r��ll�' => 'kırçıllı',
        'kazaya��' => 'kazayağı',
        'sand�kl�' => 'sandıklı',
        'Sand�kl�' => 'Sandıklı',
        'tak�m�' => 'takımı',
        'Tak�m�' => 'Takımı',
        'k�l�f�' => 'kılıfı',
        'K�l�f�' => 'Kılıfı',
        'g�m��' => 'gümüş',
        'G�m��' => 'Gümüş',
        'k��e' => 'köşe',
        'K��e' => 'Köşe',
        'tak�m' => 'takım',
        'Tak�m' => 'Takım',
        'alt�n' => 'altın',
        'Alt�n' => 'Altın',
        'alt�' => 'altı',
        'Alt�' => 'Altı',
        'ye�il' => 'yeşil',
        'Ye�il' => 'Yeşil',
        'k�r�k' => 'kırık',
        'K�r�k' => 'Kırık',
        'alt�gen' => 'altıgen',
        'Alt�gen' => 'Altıgen',
        'yast�k' => 'yastık',
        'Yast�k' => 'Yastık',
        'k�l�f' => 'kılıf',
        'K�l�f' => 'Kılıf',
        'sand�k' => 'sandık',
        'Sand�k' => 'Sandık',
        'd��eme' => 'döşeme',
        'D��eme' => 'Döşeme',
        'b�y�k' => 'büyük',
        'B�y�k' => 'Büyük',
        'k���k' => 'küçük',
        'K���k' => 'Küçük',
        'ard�sman' => 'ardışman',
        'Ard�sman' => 'Ardışman',
        's�nger' => 'sünger',
        'S�nger' => 'Sünger',
        'm�hendis' => 'mühendis',
        'M�hendis' => 'Mühendis',
        'ah�ap' => 'ahşap',
        'Ah�ap' => 'Ahşap',
        'bah�e' => 'bahçe',
        'Bah�e' => 'Bahçe',
        'ki�ilik' => 'kişilik',
        'Ki�ilik' => 'Kişilik',
        'se�im' => 'seçim',
        'Se�im' => 'Seçim',
        's�tl�' => 'sütlü',
        'S�tl�' => 'Sütlü',
        's�rt' => 'sırt',
        'S�rt' => 'Sırt',
        'ak�ll�' => 'akıllı',
        'Ak�ll�' => 'Akıllı',
        'pelu�' => 'peluş',
        'Pelu�' => 'Peluş',
        'do�al' => 'doğal',
        'Do�al' => 'Doğal',
        'nat�rel' => 'natürel',
        'Nat�rel' => 'Natürel',
        'ba�lant�' => 'bağlantı',
        'Ba�lant�' => 'Bağlantı',
        'beyaz�t' => 'beyazıt',
        'Beyaz�t' => 'Beyazıt',
        'g�rgen' => 'gürgen',
        'G�rgen' => 'Gürgen',
        'G�RGEN' => 'GÜRGEN',
        '�er�eve' => 'çerçeve',
        '�er�eve' => 'Çerçeve',
        'yarmal�' => 'yarmalı',
        'Yarmal�' => 'Yarmalı',
        'yarmas�z' => 'yarmasız',
        'Yarmas�z' => 'Yarmasız',
        'sallan�r' => 'sallanır',
        'Sallan�r' => 'Sallanır',
        'kulakl�' => 'kulaklı',
        'Kulakl�' => 'Kulaklı',
        'kaps�l' => 'kapsül',
        'Kaps�l' => 'Kapsül',
        'me�e' => 'meşe',
        'Me�e' => 'Meşe',
        'z�mpara' => 'zımpara',
        'Z�mpara' => 'Zımpara',
        't�rnakl�' => 'tırnaklı',
        'T�rnakl�' => 'Tırnaklı',
        'makarnas�' => 'makarnası',
        'Makarnas�' => 'Makarnası',
        'ayarl�' => 'ayarlı',
        'Ayarl�' => 'Ayarlı',
        'aya��' => 'ayağı',
        'Aya��' => 'Ayağı',
        '��ta' => 'çıta',
        '�ift' => 'çift',
        '�ap' => 'çap',
        '�st�' => 'üstü',
        '�st' => 'üst',
        '�PTAL' => 'İPTAL',
        '�elik' => 'çelik',
        '�ila' => 'şila',
        '�zel' => 'özel',
        'k�pe' => 'küpe',
        'K�pe' => 'Küpe',
        'd�z' => 'düz',
        'D�z' => 'Düz',
        's�ra' => 'sıra',
        'S�ra' => 'Sıra',
        'kal�n' => 'kalın',
        'Kal�n' => 'Kalın',
        'a�a�' => 'ağaç',
        'A�a�' => 'Ağaç',
        'a��lm��' => 'açılmış',
        'A��lm��' => 'Açılmış',
        'i� iskelet' => 'iç iskelet',
        'i�i' => 'içi',
        'i�in' => 'için',
        'S�LME' => 'SİLME',
        'SA�' => 'SAĞ',
        'BO�' => 'BOŞ',
        'Mekanizmal�' => 'Mekanizmalı',
        'Ayakl�' => 'Ayaklı',
        'Mod�ler' => 'Modüler',
        'Kuma�' => 'Kumaş',
        'J�t' => 'Jüt',
        'Tar��n' => 'Tarçın',
        'A��k' => 'Açık',
        'Katlan�r' => 'Katlanır',
        'Yatakl�' => 'Yataklı',
        'Hazeranl�' => 'Hazeranlı',
        'Mobilyas�' => 'Mobilyası',
        'Espa�os' => 'Espaços',
        '�ay' => 'Çay',
        'Mam�l' => 'Mamül',
        '�r�n' => 'Ürün',
        'IK�L�' => 'İKİLİ',
        'TEKL�' => 'TEKLİ',
        'K��E' => 'KÖŞE',
        'sa�' => 'sağ',
        '3l�' => '3lü',
        'D��/' => 'DÖŞ/',
        'espa�os' => 'espaços',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sample = max(0, (int) $this->option('sample'));
        $brokenChar = "\u{FFFD}";

        $allUpdates = [];
        $allStillBroken = [];
        $allBrokenRows = 0;

        foreach (self::TARGETS as $target) {
            $rows = DB::table($target['table'])
                ->select($target['key'], $target['column'])
                ->whereRaw("HEX({$target['column']}) LIKE ?", ['%EFBFBD%'])
                ->orderBy($target['key'])
                ->get();

            if ($rows->isEmpty()) {
                $this->info(sprintf('%s.%s icin bozuk kayit bulunmadi.', $target['table'], $target['column']));
                continue;
            }

            $allBrokenRows += $rows->count();
            $updates = [];
            $stillBroken = [];

            foreach ($rows as $row) {
                $current = (string) $row->{$target['column']};
                $fixed = $this->repairValue($current);

                if ($fixed !== $current) {
                    $updates[] = [
                        'table' => $target['table'],
                        'column' => $target['column'],
                        'id' => (int) $row->{$target['key']},
                        'before' => $current,
                        'after' => $fixed,
                    ];
                }

                if (str_contains($fixed, $brokenChar)) {
                    $stillBroken[] = [
                        'table' => $target['table'],
                        'column' => $target['column'],
                        'id' => (int) $row->{$target['key']},
                        'value' => $fixed,
                    ];
                }
            }

            $allUpdates = array_merge($allUpdates, $updates);
            $allStillBroken = array_merge($allStillBroken, $stillBroken);

            $this->info(sprintf(
                '%s.%s tarandi: %d bozuk satir, %d satir duzelecek, %d satirda kontrol gerekecek.',
                $target['table'],
                $target['column'],
                $rows->count(),
                count($updates),
                count($stillBroken)
            ));
        }

        if ($allBrokenRows === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line('Dry-run modunda calisildi, veritabani yazilmadi.');
        } else {
            DB::transaction(function () use ($allUpdates) {
                foreach ($allUpdates as $update) {
                    DB::table($update['table'])
                        ->where('No', $update['id'])
                        ->update([$update['column'] => $update['after']]);
                }
            });

            $this->info(sprintf('%d satir guncellendi.', count($allUpdates)));
        }

        if (!empty($allUpdates)) {
            $this->table(
                ['Tablo', 'Kolon', 'No', 'Once', 'Sonra'],
                array_map(
                    fn (array $update) => [$update['table'], $update['column'], $update['id'], $update['before'], $update['after']],
                    array_slice($allUpdates, 0, 12)
                )
            );
        }

        if ($sample > 0 && !empty($allStillBroken)) {
            $this->warn('Hala manuel kontrol gerektiren ornekler:');
            $this->table(
                ['Tablo', 'Kolon', 'No', 'Deger'],
                array_map(
                    fn (array $row) => [$row['table'], $row['column'], $row['id'], $row['value']],
                    array_slice($allStillBroken, 0, $sample)
                )
            );
        }

        return empty($allStillBroken) ? self::SUCCESS : self::FAILURE;
    }

    private function repairValue(string $value): string
    {
        $fixed = str_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $value);

        // Lower-case "d��" in free text means "dis"; the explicit D��/ stage
        // prefix is already handled above as DÖŞ/.
        $fixed = preg_replace('/(^|[\s(])d��(?=$|[\s)])/u', '$1dış', $fixed) ?? $fixed;

        // Some words lose the information needed to decide case; repair them
        // only when they appear in title-position contexts.
        $fixed = preg_replace('/(^|[\s(,\/])�kili/u', '$1İkili', $fixed) ?? $fixed;
        $fixed = preg_replace('/(^|[\s(,\/])��l�/u', '$1Üçlü', $fixed) ?? $fixed;
        $fixed = preg_replace('/(^|[\s(,\/])�rina/u', '$1İrina', $fixed) ?? $fixed;
        $fixed = preg_replace('/(^|[\s(,\/:\[])�ok\b/u', '$1Çok', $fixed) ?? $fixed;
        $fixed = preg_replace('/(^|[\s(,\/])�izgili/u', '$1Çizgili', $fixed) ?? $fixed;
        $fixed = preg_replace('/(^|[\s(,\/])�ift/u', '$1Çift', $fixed) ?? $fixed;

        // Numeric suffixes such as 140l�k / 3l� appear throughout component names.
        $fixed = preg_replace('/(?<=\d)l�k\b/u', 'lık', $fixed) ?? $fixed;
        $fixed = preg_replace('/(?<=\d)l�\b/u', 'lü', $fixed) ?? $fixed;

        return $fixed;
    }
}
