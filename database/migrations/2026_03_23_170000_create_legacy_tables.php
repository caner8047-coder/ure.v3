<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * MSSQL → MySQL tam migration.
 * Orijinal tablo ve kolon isimleri aynen korunur (tb prefix, PascalCase).
 * Laravel'in kendi tabloları (users, sessions, jobs vb.) dokunulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- V1 TABLOLAR (Orijinal MSSQL'den gelen) ----

        if (!Schema::hasTable('tbBolum')) {
            Schema::create('tbBolum', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->string('BolumAdi', 200)->nullable();
            });
        }

        if (!Schema::hasTable('tbUrunler')) {
            Schema::create('tbUrunler', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->string('UrunID', 500)->nullable();
                $table->text('AraAdlarYol')->nullable();
                $table->string('SistemAdi', 500)->nullable();
                $table->string('SistemKodu', 500)->nullable();
                $table->longText('Resim')->nullable();
            });
        }

        if (!Schema::hasTable('tbAraUrun')) {
            Schema::create('tbAraUrun', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->string('AraUrunAdi', 500)->nullable();
                $table->integer('Performans')->nullable()->default(0);
                $table->integer('BolumAdiNo')->nullable();
                $table->integer('MinAdet')->nullable()->default(0);
                $table->string('UrunCesidi', 100)->nullable();
                $table->text('Yol')->nullable();
                $table->longText('Resim')->nullable();
            });
        }

        if (!Schema::hasTable('tbPersonel')) {
            Schema::create('tbPersonel', function (Blueprint $table) {
                $table->integer('PersonelNo')->primary(); // Removed autoIncrement to avoid issues with imported IDs
                $table->string('Ad', 200)->nullable();
                $table->string('Soyad', 200)->nullable();
                $table->string('Telefon', 50)->nullable();
                $table->string('Adres', 200)->nullable();
                $table->string('Mail', 300)->nullable();
                $table->string('Sifre', 200)->nullable();
                $table->integer('BolumAdiNo')->nullable();
            });
        }

        if (!Schema::hasTable('tbBolumAraStok')) {
            Schema::create('tbBolumAraStok', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('BolumAdiNo')->nullable();
                $table->integer('Adet')->nullable()->default(0);
                $table->integer('AraUrunAdiNo')->nullable();
                $table->integer('UrunIDNo')->nullable();
                $table->integer('TamponMiktar')->nullable()->default(0);
            });
        }

        if (!Schema::hasTable('tbBolumHavuz')) {
            Schema::create('tbBolumHavuz', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('UrunIDNo')->nullable();
                $table->dateTime('GorevBaslangicTarihi')->nullable();
                $table->integer('BolumAdiNo')->nullable();
                $table->integer('AraUrunAdiNo')->nullable();
                $table->integer('Adet')->nullable()->default(0);
                $table->integer('ToplamAdet')->nullable()->default(0);
                $table->integer('AdimSirasi')->nullable()->default(0);
            });
        }

        if (!Schema::hasTable('tbGorevler')) {
            Schema::create('tbGorevler', function (Blueprint $table) {
                $table->integer('No')->primary();
                $table->integer('UrunIDNo')->nullable();
                $table->string('GorevBaslamaTarihi', 50)->nullable();
                $table->string('GorevBitisTarihi', 50)->nullable();
                $table->integer('ToplamAdet')->nullable()->default(0);
                $table->integer('BolumAdiNo')->nullable();
                $table->integer('AraUrunAdiNo')->nullable();
                $table->integer('UretilenAdet')->nullable()->default(0);
                $table->integer('PersonelNo')->nullable();
            });
        }

        if (!Schema::hasTable('tbPersonelGorev')) {
            Schema::create('tbPersonelGorev', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('UrunIDNo')->nullable();
                $table->integer('PersonelNo')->nullable();
                $table->integer('Adet')->nullable()->default(0);
                $table->integer('BekleyenAdet')->nullable()->default(0);
                $table->tinyInteger('Onay')->nullable()->default(0);
                $table->integer('AraUrunAdiNo')->nullable();
                $table->integer('BolumAdiNo')->nullable();
                $table->dateTime('GorevTarihi')->nullable();
            });
        }

        if (!Schema::hasTable('tbIletisim')) {
            Schema::create('tbIletisim', function (Blueprint $table) {
                $table->integer('MesajNo')->autoIncrement();
                $table->integer('PersonelNo')->nullable();
                $table->integer('BolumAdiNo')->nullable();
                $table->text('Mesaj')->nullable();
                $table->dateTime('Tarih')->nullable();
                $table->tinyInteger('Okundu')->nullable()->default(0);
            });
        }

        if (!Schema::hasTable('tbVerilenGorevler')) {
            Schema::create('tbVerilenGorevler', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('UrunIDNo')->nullable();
                $table->dateTime('GorevTarihi')->nullable();
                $table->integer('ToplamAdet')->nullable()->default(0);
                $table->text('Aciklama')->nullable();
            });
        }

        // ---- V2 TABLOLAR (SiparisApi.ashx tarafından oluşturulan) ----

        if (!Schema::hasTable('tbSiparisSatir')) {
            Schema::create('tbSiparisSatir', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->string('SiparisNo', 50);
                $table->string('Pazaryeri', 50)->nullable();
                $table->string('Magaza', 100)->nullable();
                $table->dateTime('SiparisTarihi')->nullable();
                $table->string('Musteri', 200)->nullable();
                $table->string('UrunAdi', 300);
                $table->integer('Adet')->default(1);
                $table->string('MusteriNotu', 500)->nullable();
                $table->dateTime('KargoSonTeslim')->nullable();
                $table->string('Kategori', 100)->nullable();
                $table->string('Durum', 30)->default('UretimBekliyor');
                $table->boolean('Aktif')->default(true);
                $table->integer('EslesenUrunNo')->nullable();
                $table->string('EslesenUrunTur', 10)->nullable();
                $table->integer('EslesmePuani')->nullable();
                $table->string('EslesmeYontemi', 10)->nullable();
                $table->dateTime('IsEmriTarihi')->nullable();
                $table->integer('GorevNo')->nullable();
                $table->string('StokKodu', 50)->nullable();
                $table->boolean('SetMi')->default(false);
                $table->integer('SetNo')->nullable();
                $table->integer('AnaSetSatirNo')->nullable();
                $table->text('TamponDusumleri')->nullable();
                $table->integer('BagliOlduguOzelUretimNo')->nullable();
                $table->dateTime('YuklemeTarihi')->nullable();
                $table->dateTime('GuncellemeTarihi')->nullable();
            });
        }

        if (!Schema::hasTable('tbUrunEslestirmeOnbellek')) {
            Schema::create('tbUrunEslestirmeOnbellek', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->string('ExcelUrunAdi', 300);
                $table->integer('EslesenUrunNo');
                $table->string('EslesenUrunTur', 10);
                $table->dateTime('OlusturmaTarihi')->nullable();
                $table->unique('ExcelUrunAdi', 'IX_Eslestirme_ExcelAdi');
            });
        }

        if (!Schema::hasTable('tbSetTanimlari')) {
            Schema::create('tbSetTanimlari', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->string('ExcelSetAdi', 500);
                $table->string('SetAdi', 200)->nullable();
                $table->dateTime('OlusturmaTarihi')->nullable();
                $table->boolean('Aktif')->default(true);
                $table->unique('ExcelSetAdi', 'IX_Set_ExcelAdi');
            });
        }

        if (!Schema::hasTable('tbSetIcerikleri')) {
            Schema::create('tbSetIcerikleri', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('SetNo');
                $table->integer('UrunNo');
                $table->integer('Adet')->default(1);
            });
        }

        if (!Schema::hasTable('tbKritikStokEsik')) {
            Schema::create('tbKritikStokEsik', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('AraUrunAdiNo');
                $table->integer('EsikMiktar')->default(5);
                $table->boolean('OtomatikIsEmri')->default(false);
                $table->integer('IsEmriAdet')->nullable()->default(10);
                $table->integer('UrunIDNo')->nullable();
                $table->boolean('Aktif')->default(true);
                $table->dateTime('SonKontrolTarihi')->nullable();
                $table->dateTime('SonUyariTarihi')->nullable();
                $table->dateTime('OlusturmaTarihi')->nullable();
                $table->unique('AraUrunAdiNo', 'IX_Esik_AraUrun');
            });
        }

        if (!Schema::hasTable('tbKritikStokUyari')) {
            Schema::create('tbKritikStokUyari', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('AraUrunAdiNo');
                $table->integer('EsikMiktar');
                $table->integer('MevcutStok');
                $table->string('UyariTipi', 30)->default('Uyari');
                $table->boolean('OtomatikIsEmriVerildi')->default(false);
                $table->boolean('Okundu')->default(false);
                $table->dateTime('OlusturmaTarihi')->nullable();
            });
        }

        if (!Schema::hasTable('tbIsEmriGecmisi')) {
            Schema::create('tbIsEmriGecmisi', function (Blueprint $table) {
                $table->integer('No')->autoIncrement();
                $table->integer('SiparisSatirNo');
                $table->string('SiparisNo', 50)->nullable();
                $table->string('Musteri', 200)->nullable();
                $table->string('UrunAdi', 300)->nullable();
                $table->string('SistemUrunAdi', 300)->nullable();
                $table->integer('Adet')->nullable();
                $table->string('Kategori', 100)->nullable();
                $table->dateTime('IsEmriTarihi')->nullable();
                $table->string('IslemTipi', 30);
                $table->dateTime('IslemTarihi')->nullable();
                $table->integer('GorevNo')->nullable();
                $table->integer('EslesenUrunNo')->nullable();
                $table->string('EslesenUrunTur', 10)->nullable();
                $table->dateTime('KargoSonTeslim')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbIsEmriGecmisi');
        Schema::dropIfExists('tbKritikStokUyari');
        Schema::dropIfExists('tbKritikStokEsik');
        Schema::dropIfExists('tbSetIcerikleri');
        Schema::dropIfExists('tbSetTanimlari');
        Schema::dropIfExists('tbUrunEslestirmeOnbellek');
        Schema::dropIfExists('tbSiparisSatir');
        Schema::dropIfExists('tbVerilenGorevler');
        Schema::dropIfExists('tbIletisim');
        Schema::dropIfExists('tbPersonelGorev');
        Schema::dropIfExists('tbGorevler');
        Schema::dropIfExists('tbBolumHavuz');
        Schema::dropIfExists('tbBolumAraStok');
        Schema::dropIfExists('tbPersonel');
        Schema::dropIfExists('tbAraUrun');
        Schema::dropIfExists('tbUrunler');
        Schema::dropIfExists('tbBolum');
    }
};
