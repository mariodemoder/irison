<?php

namespace App\Exports;

use ZipArchive;

class XlsxWriter
{
    private array $sheets = [];

    public function addSheet(string $name): void
    {
        $this->sheets[] = [
            'name' => $name,
            'handle' => fopen('php://temp', 'r+'),
            'rowCount' => 0,
        ];
    }

    public function writeRow(array $values, ?array $types = null): void
    {
        $sheet = &$this->sheets[array_key_last($this->sheets)];
        $xml = '<row>';
        foreach ($values as $i => $value) {
            $type = $types[$i] ?? null;
            if ($type === 'n' || is_numeric($value) && !str_starts_with((string)$value, '0')) {
                $xml .= '<c><v>' . $value . '</v></c>';
            } else {
                $escaped = htmlspecialchars((string)($value ?? ''), ENT_XML1, 'UTF-8');
                $xml .= '<c t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }
        }
        $xml .= "</row>\n";
        fwrite($sheet['handle'], $xml);
        $sheet['rowCount']++;
    }

    public function writeHeaderRow(array $headers): void
    {
        $escapedHeaders = array_map(fn($h) => '<c t="inlineStr"><is><t>' . htmlspecialchars((string)$h, ENT_XML1, 'UTF-8') . '</t></is></c>', $headers);
        $xml = '<row>' . implode('', $escapedHeaders) . "</row>\n";
        $sheet = &$this->sheets[array_key_last($this->sheets)];
        fwrite($sheet['handle'], $xml);
        $sheet['rowCount']++;
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes());
        $zip->addFromString('_rels/.rels', $this->buildRels());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        foreach ($this->sheets as $i => $sheet) {
            rewind($sheet['handle']);
            $body = stream_get_contents($sheet['handle']);
            fclose($sheet['handle']);
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetData>' . $body . '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
        }

        $zip->close();
    }

    private function buildContentTypes(): string
    {
        $override = '';
        foreach ($this->sheets as $i => $sheet) {
            $override .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1)
                . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $override
            . '</Types>';
    }

    private function buildRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="' . htmlspecialchars($sheet['name'], ENT_XML1, 'UTF-8')
                . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>';
    }

    private function buildWorkbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $rels .= '<Relationship Id="rId' . ($i + 1)
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId99" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels . '</Relationships>';
    }

    private function buildStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }
}
