<?php

declare(strict_types=1);

/**
 * MSSQL T-SQL dump → MySQL uyumlu SQL dönüştürücü
 * Kullanım: php scripts/mssql_to_mysql.php mssql_export.sql > mysql_import.sql
 */

if ($argc < 2) {
    echo "Kullanım: php scripts/mssql_to_mysql.php <mssql_dump.sql>\n";
    echo "Çıktıyı dosyaya yazmak için: php scripts/mssql_to_mysql.php input.sql > output.sql\n";
    exit(1);
}

$inputFile = $argv[1];
if (!file_exists($inputFile)) {
    echo "Hata: Dosya bulunamadı: {$inputFile}\n";
    exit(1);
}

$content = file_get_contents($inputFile);
if ($content === false) {
    echo "Hata: Dosya okunamadı.\n";
    exit(1);
}

echo "-- MySQL Import Script (MSSQL'den dönüştürüldü)\n";
echo "-- Oluşturulma: " . date('Y-m-d H:i:s') . "\n";
echo "-- Orijinal dosya: " . basename($inputFile) . "\n\n";

echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

// Satır satır işle
$lines = explode("\n", $content);
$output = [];
$inCreateTable = false;
$skipBlock = false;
$currentTable = '';

foreach ($lines as $line) {
    $trimmed = trim($line);

    // === ATLANACAK SATIRLAR ===

    // SET ANSI_NULLS, SET QUOTED_IDENTIFIER, SET ANSI_PADDING, SET ANSI_WARNINGS
    if (preg_match('/^SET\s+(ANSI_NULLS|QUOTED_IDENTIFIER|ANSI_PADDING|ANSI_WARNINGS)\s+(ON|OFF)/i', $trimmed)) {
        continue;
    }

    // GO satırları
    if (preg_match('/^GO\s*$/i', $trimmed)) {
        continue;
    }

    // USE [database]
    if (preg_match('/^USE\s+\[/i', $trimmed)) {
        continue;
    }

    // IF NOT EXISTS ... BEGIN ve END blokları (basit)
    if (preg_match('/^IF\s+NOT\s+EXISTS.*BEGIN/i', $trimmed)) {
        $skipBlock = true;
        continue;
    }
    if ($skipBlock && preg_match('/^END\b/i', $trimmed)) {
        $skipBlock = false;
        continue;
    }
    if ($skipBlock) {
        continue;
    }

    // ALTER TABLE ... CHECK CONSTRAINT
    if (preg_match('/^ALTER\s+TABLE.*CHECK\s+CONSTRAINT/i', $trimmed)) {
        continue;
    }

    // ALTER TABLE ... WITH CHECK ADD CONSTRAINT
    if (preg_match('/^ALTER\s+TABLE.*WITH\s+CHECK\s+ADD\s+CONSTRAINT/i', $trimmed)) {
        continue;
    }

    // === TABLO ADI DÖNÜŞÜMÜ ===
    // [dbo].[tbXxx] → `tbXxx`
    $line = preg_replace('/\[dbo\]\.\[(\w+)\]/', '`$1`', $line);

    // [sütun_adı] → `sütun_adı`
    $line = preg_replace('/\[([A-Za-z_]\w*)\]/', '`$1`', $line);

    // === VERİ TİPİ DÖNÜŞÜMLERİ ===
    // NVARCHAR → VARCHAR
    $line = preg_replace('/\bNVARCHAR\b/i', 'VARCHAR', $line);
    $line = preg_replace('/\bNCHAR\b/i', 'CHAR', $line);
    $line = preg_replace('/\bNTEXT\b/i', 'TEXT', $line);

    // BIT → TINYINT(1)
    $line = preg_replace('/\bBIT\b/i', 'TINYINT(1)', $line);

    // IDENTITY(x,y) → AUTO_INCREMENT
    $line = preg_replace('/\bIDENTITY\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'AUTO_INCREMENT', $line);

    // COLLATE ... sil
    $line = preg_replace('/\s+COLLATE\s+\S+/i', '', $line);

    // ON [PRIMARY] sil
    $line = preg_replace('/\s+ON\s+\[PRIMARY\]/i', '', $line);

    // === FONKSIYON DÖNÜŞÜMÜ ===
    // GETDATE() → NOW()
    $line = preg_replace('/\bGETDATE\s*\(\s*\)/i', 'NOW()', $line);

    // === INSERT İÇİN VERİ DÖNÜŞÜMÜ ===
    // N'...' → '...' (Unicode string prefix)
    $line = preg_replace("/N'((?:[^']|'')*)'/", "'$1'", $line);

    // === CREATE TABLE BAŞLANGICI ===
    if (preg_match('/^CREATE\s+TABLE\s+`(\w+)`/i', $trimmed, $m)) {
        $inCreateTable = true;
        $currentTable = $m[1];
    }

    // CREATE TABLE bloğu bitti
    if ($inCreateTable && preg_match('/^\)/', $trimmed)) {
        $inCreateTable = false;

        // AUTO_INCREMENT satırını tablo sonuna ekle
        $line .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1";
    }

    // CREATE TABLE içinde PRIMARY KEY satırı
    if ($inCreateTable && preg_match('/PRIMARY\s+KEY/i', $trimmed)) {
        // PRIMARY KEY CLUSTERED → PRIMARY KEY
        $line = preg_replace('/PRIMARY\s+KEY\s+CLUSTERED/i', 'PRIMARY KEY', $line);
    }

    // CREATE TABLE içinde NONCLUSTERED index → INDEX
    if ($inCreateTable && preg_match('/NONCLUSTERED/i', $trimmed)) {
        $line = preg_replace('/\bNONCLUSTERED\b/i', '', $line);
    }

    // NOT FOR REPLICATION sil
    $line = preg_replace('/\s+NOT\s+FOR\s+REPLICATION/i', '', $line);

    // PAD_INDEX, FILLFACTOR, IGNORE_DUP_KEY, STATISTICS_NORECOMPUTE vb. sil
    $line = preg_replace('/\s+(PAD_INDEX|FILLFACTOR|IGNORE_DUP_KEY|STATISTICS_NORECOMPUTE|STATISTICS_INCREMENTAL|ALLOW_ROW_LOCKS|ALLOW_PAGE_LOCKS|OPTIMIZE_FOR_SEQUENTIAL_KEY)\s*=\s*\w+/i', '', $line);

    // CONSTRAINT ... DEFAULT ... FOR ... → DEFAULT ... (basitleştir)
    // ALTER TABLE ADD CONSTRAINT DF_xxx DEFAULT value FOR [column]
    if (preg_match('/ALTER\s+TABLE\s+`(\w+)`\s+ADD\s+CONSTRAINT\s+\w+\s+DEFAULT\s+(.+?)\s+FOR\s+`(\w+)`/i', $line, $m)) {
        $line = "ALTER TABLE `{$m[1]}` ALTER COLUMN `{$m[3]}` SET DEFAULT {$m[2]};";
    }

    // CONSTRAINT ... PRIMARY KEY CLUSTERED (... ) → PRIMARY KEY (... )
    $line = preg_replace('/CONSTRAINT\s+\w+\s+PRIMARY\s+KEY\s+CLUSTERED/i', 'PRIMARY KEY', $line);

    $output[] = $line;
}

// Temizlik ve son dokunuşlar
$result = implode("\n", $output);

// Boş satırları temizle
$result = preg_replace("/\n{3,}/", "\n\n", $result);

echo $result;

echo "\n\nSET FOREIGN_KEY_CHECKS = 1;\n";

$lineCount = count($lines);
$outputCount = count($output);
fprintf(STDERR, "\nDönüştürme tamamlandı: %d satır → %d satır\n", $lineCount, $outputCount);
