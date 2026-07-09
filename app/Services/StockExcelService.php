<?php

namespace App\Services;

class StockExcelService
{
    public function buildStockWorkbook($stocks): string
    {
        $rows = [[
            'No',
            'Bölüm',
            'Ara Ürün',
            'Ürün Çeşidi',
            'Adet',
            'Görevdeki',
            'Boşta/Tampon',
        ]];

        foreach ($stocks as $row) {
            $adet = intval($row->Adet);
            $bostaTampon = max(0, min($adet, intval($row->TamponMiktar)));
            $gorevdeki = max(0, $adet - $bostaTampon);

            $rows[] = [
                $row->No === null ? null : intval($row->No),
                (string) $row->BolumAdi,
                (string) $row->AraUrunAdi,
                (string) $row->UrunCesidi,
                $adet,
                $gorevdeki,
                $bostaTampon,
            ];
        }

        return $this->buildXlsxWorkbook('Stoklar', $rows, [11, 22, 48, 18, 12, 14, 16]);
    }

    public function buildXlsxWorkbook(string $sheetName, array $rows, array $columnWidths): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Sunucuda Excel dosyası oluşturmak için ZipArchive eklentisi gerekli.');
        }

        $rows = array_values($rows);
        if (count($rows) === 1) {
            $rows[] = array_fill(0, count($rows[0]), null);
        }

        $columnCount = max(1, ...array_map(fn ($row) => max(1, count($row)), $rows));
        foreach ($rows as $index => $row) {
            $rows[$index] = array_pad(array_values($row), $columnCount, null);
        }

        $sheetName = $this->safeExcelSheetName($sheetName);
        $lastColumn = $this->excelColumnName($columnCount);
        $lastRow = count($rows);
        $tableRef = 'A1:' . $lastColumn . $lastRow;

        $tmp = tempnam(sys_get_temp_dir(), 'stocks_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Excel dosyası için geçici alan oluşturulamadı.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Excel dosyası oluşturulamadı.');
        }

        $zip->addFromString('[Content_Types].xml', $this->buildXlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildXlsxRootRelsXml());
        $zip->addFromString('docProps/core.xml', $this->buildXlsxCoreXml());
        $zip->addFromString('docProps/app.xml', $this->buildXlsxAppXml($sheetName));
        $zip->addFromString('xl/workbook.xml', $this->buildXlsxWorkbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildXlsxWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildXlsxStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->buildXlsxWorksheetXml($rows, $columnWidths, $tableRef));
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->buildXlsxWorksheetRelsXml());
        $zip->addFromString('xl/tables/table1.xml', $this->buildXlsxTableXml($rows[0], $tableRef));
        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        if ($content === false) {
            throw new \RuntimeException('Excel dosyası okunamadı.');
        }

        return $content;
    }

    public function readStockImportRows($file): array
    {
        $path = $file->getRealPath();
        if (!$path || !is_file($path)) {
            throw new \RuntimeException('Dosya okunamadı.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $probe = file_get_contents($path, false, null, 0, 512);
        $probe = $probe === false ? '' : ltrim($probe, "\xEF\xBB\xBF\r\n\t ");

        if (str_starts_with($probe, 'PK')) {
            return $this->readXlsxRows($path);
        }

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($path);
        }

        if ($extension === 'xls' || str_starts_with(strtolower($probe), '<')) {
            return $this->readHtmlTableRows($path);
        }

        return $this->readCsvRows($path);
    }

    public function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('CSV dosyası okunamadı.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new \RuntimeException('Geçersiz CSV formatı.');
            }

            $delimiter = $this->detectCsvDelimiter($firstLine);
            $rows = [str_getcsv($firstLine, $delimiter)];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    public function readHtmlTableRows(string $path): array
    {
        $html = file_get_contents($path);
        if ($html === false || trim($html) === '') {
            throw new \RuntimeException('Excel tablosu okunamadı.');
        }

        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html) ?? $html;

        if (!class_exists(\DOMDocument::class)) {
            return $this->readHtmlTableRowsWithRegex($html);
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->readHtmlTableRowsWithRegex($html);
        }

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            throw new \RuntimeException('Excel tablosu bulunamadı.');
        }

        $rows = [];
        foreach ($tables->item(0)->getElementsByTagName('tr') as $tr) {
            $row = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE || !in_array(strtolower($cell->nodeName), ['th', 'td'], true)) {
                    continue;
                }
                $row[] = $this->normalizeSpreadsheetText($cell->textContent);
            }
            if ($this->spreadsheetRowHasContent($row)) {
                $rows[] = $row;
            }
        }

        if (!$rows) {
            throw new \RuntimeException('Excel tablosunda okunacak satır bulunamadı.');
        }

        return $rows;
    }

    public function readHtmlTableRowsWithRegex(string $html): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rowMatches);
        $rows = [];

        foreach ($rowMatches[1] as $rowHtml) {
            preg_match_all('/<t[hd]\b[^>]*>(.*?)<\/t[hd]>/is', $rowHtml, $cellMatches);
            $row = array_map(function ($cellHtml) {
                return $this->normalizeSpreadsheetText(html_entity_decode(strip_tags($cellHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }, $cellMatches[1]);

            if ($this->spreadsheetRowHasContent($row)) {
                $rows[] = $row;
            }
        }

        if (!$rows) {
            throw new \RuntimeException('Excel tablosunda okunacak satır bulunamadı.');
        }

        return $rows;
    }

    public function readXlsxRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class) || !class_exists(\DOMDocument::class)) {
            throw new \RuntimeException('Excel çalışma kitabını okumak için ZipArchive ve DOM eklentileri gerekli.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Excel çalışma kitabı açılamadı.');
        }

        try {
            $sharedStrings = $this->readXlsxSharedStrings($zip);
            $sheetPath = $this->resolveFirstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false && $sheetPath !== 'xl/worksheets/sheet1.xml') {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            }

            if ($sheetXml === false) {
                throw new \RuntimeException('Excel çalışma kitabında ilk sayfa bulunamadı.');
            }

            $dom = $this->loadXmlDocument($sheetXml);
            $rows = [];

            foreach ($dom->getElementsByTagName('row') as $rowNode) {
                $cells = [];

                foreach ($rowNode->childNodes as $cellNode) {
                    if ($cellNode->nodeType !== XML_ELEMENT_NODE || strtolower($cellNode->localName) !== 'c') {
                        continue;
                    }

                    $cellRef = $cellNode->getAttribute('r');
                    $columnIndex = $cellRef ? $this->excelColumnIndexFromCellReference($cellRef) : count($cells) + 1;
                    $cells[$columnIndex - 1] = $this->readXlsxCellValue($cellNode, $sharedStrings);
                }

                if ($cells) {
                    ksort($cells);
                    $maxIndex = max(array_keys($cells));
                    $row = [];
                    for ($i = 0; $i <= $maxIndex; $i++) {
                        $row[] = $cells[$i] ?? '';
                    }
                    $rows[] = $row;
                }
            }

            if (!$rows) {
                throw new \RuntimeException('Excel çalışma kitabında okunacak satır bulunamadı.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    public function readXlsxSharedStrings($zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $dom = $this->loadXmlDocument($xml);
        $strings = [];

        foreach ($dom->getElementsByTagName('si') as $item) {
            $strings[] = $this->collectXlsxText($item);
        }

        return $strings;
    }

    public function resolveFirstWorksheetPath($zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $dom = $this->loadXmlDocument($workbookXml);
        $sheet = $dom->getElementsByTagName('sheet')->item(0);
        if (!$sheet) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationshipId = $sheet->getAttribute('r:id')
            ?: $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');

        if (!$relationshipId) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationships = $this->readXlsxRelationships($zip, 'xl/_rels/workbook.xml.rels');
        if (!isset($relationships[$relationshipId])) {
            return 'xl/worksheets/sheet1.xml';
        }

        return $this->normalizeZipTarget('xl', $relationships[$relationshipId]);
    }

    public function readXlsxRelationships($zip, string $path): array
    {
        $xml = $zip->getFromName($path);
        if ($xml === false) {
            return [];
        }

        $dom = $this->loadXmlDocument($xml);
        $relationships = [];

        foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
            $id = $relationship->getAttribute('Id');
            $target = $relationship->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $relationships[$id] = $target;
            }
        }

        return $relationships;
    }

    public function readXlsxCellValue($cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            return $this->normalizeSpreadsheetText($this->collectXlsxText($cell));
        }

        $value = $this->firstXlsxChildText($cell, 'v');

        if ($type === 's') {
            return $this->normalizeSpreadsheetText($sharedStrings[intval($value)] ?? '');
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $this->normalizeSpreadsheetText($value);
    }

    public function collectXlsxText($node): string
    {
        $parts = [];
        foreach ($node->getElementsByTagName('t') as $textNode) {
            $parts[] = $textNode->textContent;
        }

        return implode('', $parts);
    }

    public function firstXlsxChildText($node, string $tagName): string
    {
        $child = $node->getElementsByTagName($tagName)->item(0);
        return $child ? $child->textContent : '';
    }

    public function loadXmlDocument(string $xml)
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Excel çalışma kitabı XML içeriği okunamadı.');
        }

        return $dom;
    }

    public function spreadsheetRowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null) {
                continue;
            }

            if (is_string($cell)) {
                if (trim(str_replace("\xc2\xa0", ' ', $cell)) !== '') {
                    return true;
                }
                continue;
            }

            return true;
        }

        return false;
    }

    public function normalizeSpreadsheetText(mixed $value): string
    {
        $text = str_replace("\xc2\xa0", ' ', (string) ($value ?? ''));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function buildXlsxWorksheetXml(array $rows, array $columnWidths, string $tableRef): string
    {
        $columnCount = count($rows[0]);
        $columnsXml = '';
        for ($i = 1; $i <= $columnCount; $i++) {
            $width = $columnWidths[$i - 1] ?? 18;
            $columnsXml .= '<col min="' . $i . '" max="' . $i . '" width="' . number_format((float) $width, 2, '.', '') . '" customWidth="1"/>';
        }

        $sheetDataXml = '';
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $rowAttributes = ' r="' . $rowNumber . '" spans="1:' . $columnCount . '"';
            if ($rowIndex === 0) {
                $rowAttributes .= ' ht="22" customHeight="1"';
            }

            $cellsXml = '';
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->excelColumnName($columnIndex + 1) . $rowNumber;
                $style = $rowIndex === 0 ? 1 : ((is_int($value) || is_float($value)) ? 2 : 3);
                $cellsXml .= $this->buildXlsxCellXml($cellRef, $value, $style);
            }

            $sheetDataXml .= '<row' . $rowAttributes . '>' . $cellsXml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="' . $tableRef . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols>' . $columnsXml . '</cols>'
            . '<sheetData>' . $sheetDataXml . '</sheetData>'
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '<tableParts count="1"><tablePart r:id="rId1"/></tableParts>'
            . '</worksheet>';
    }

    public function buildXlsxCellXml(string $cellRef, mixed $value, int $style): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $value . '</v></c>';
        }

        return '<c r="' . $cellRef . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $this->xmlEscape((string) $value) . '</t></is></c>';
    }

    public function buildXlsxTableXml(array $headers, string $tableRef): string
    {
        $seen = [];
        $columnsXml = '';

        foreach ($headers as $index => $header) {
            $name = trim((string) $header);
            if ($name === '') {
                $name = 'Sütun ' . ($index + 1);
            }

            $baseName = $name;
            $suffix = 2;
            while (isset($seen[$name])) {
                $name = $baseName . ' ' . $suffix;
                $suffix++;
            }
            $seen[$name] = true;

            $columnsXml .= '<tableColumn id="' . ($index + 1) . '" name="' . $this->xmlEscape($name) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="Stoklar" displayName="Stoklar" ref="' . $tableRef . '" totalsRowShown="0">'
            . '<autoFilter ref="' . $tableRef . '"/>'
            . '<tableColumns count="' . count($headers) . '">' . $columnsXml . '</tableColumns>'
            . '<tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/>'
            . '</table>';
    }

    public function buildXlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/tables/table1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/>'
            . '</Types>';
    }

    public function buildXlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    public function buildXlsxWorkbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<fileVersion appName="xl" lastEdited="7" lowestEdited="7" rupBuild="23426"/>'
            . '<workbookPr defaultThemeVersion="164011"/>'
            . '<sheets><sheet name="' . $this->xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    public function buildXlsxWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    public function buildXlsxWorksheetRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/>'
            . '</Relationships>';
    }

    public function buildXlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2F8F83"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E2E0"/></left><right style="thin"><color rgb="FFD9E2E0"/></right><top style="thin"><color rgb="FFD9E2E0"/></top><bottom style="thin"><color rgb="FFD9E2E0"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/>'
            . '<tableStyles count="1" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }

    public function buildXlsxCoreXml(): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>zemuretim</dc:creator>'
            . '<cp:lastModifiedBy>zemuretim</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $timestamp . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    public function buildXlsxAppXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Microsoft Excel</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $this->xmlEscape($sheetName) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company></Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    public function safeExcelSheetName(string $sheetName): string
    {
        $sheetName = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', trim($sheetName)) ?? 'Sayfa1';
        $sheetName = $sheetName === '' ? 'Sayfa1' : $sheetName;

        return function_exists('mb_substr') ? mb_substr($sheetName, 0, 31) : substr($sheetName, 0, 31);
    }

    public function xmlEscape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    public function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name ?: 'A';
    }

    public function excelColumnIndexFromCellReference(string $cellReference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return 1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(1, $index);
    }

    public function normalizeZipTarget(string $baseDir, string $target): string
    {
        $path = str_starts_with($target, '/')
            ? ltrim($target, '/')
            : trim($baseDir, '/') . '/' . $target;

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    public function detectCsvDelimiter(string $line): string
    {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
        $delimiters = [',' => 0, ';' => 0, "\t" => 0];

        foreach ($delimiters as $delimiter => $_) {
            $delimiters[$delimiter] = count(str_getcsv($line, $delimiter));
        }

        arsort($delimiters);
        $detected = array_key_first($delimiters);

        return $detected ?: ',';
    }

    public function findCsvHeaderIndex(array $header, array $aliases): int|false
    {
        $normalized = array_map(fn ($h) => $this->normalizeCsvHeader((string) $h), $header);

        foreach ($aliases as $alias) {
            $needle = $this->normalizeCsvHeader($alias);
            $idx = array_search($needle, $normalized, true);
            if ($idx !== false) {
                return $idx;
            }
        }

        return false;
    }

    public function normalizeCsvHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? trim($value);
        $value = strtr($value, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u',
            'Ş' => 's', 'ş' => 's',
            'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    public function parseCsvInteger(mixed $value): int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 0;
        }

        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        if (is_numeric($raw)) {
            return (int) round((float) $raw);
        }

        $digits = preg_replace('/[^\d-]+/', '', $raw);
        return $digits === '' ? 0 : (int) $digits;
    }
}
