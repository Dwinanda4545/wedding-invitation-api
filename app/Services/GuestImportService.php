<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class GuestImportService
{
    public const HEADERS = ['name', 'phone_number', 'guest_type', 'relation'];

    /** @var list<array{name: string, phone_number: string, guest_type: string, relation: string}> */
    public const SAMPLE_ROWS = [
        [
            'name' => 'Budi Santoso',
            'phone_number' => '081234567890',
            'guest_type' => 'VIP',
            'relation' => 'Keluarga Mempelai Pria',
        ],
        [
            'name' => 'Siti Aminah',
            'phone_number' => '6281234567890',
            'guest_type' => 'Regular',
            'relation' => 'Teman Kerja',
        ],
    ];

    /**
     * @return array{created: int, skipped_empty_rows: int, warnings: list<string>}
     */
    public function import(Event $event, UploadedFile $file, GuestQrCodeService $qr): array
    {
        $rows = $this->parseRows($file);

        $created = 0;
        $skipped = 0;
        $warnings = [];

        $relationsByLabel = $event->guestRelations()
            ->get()
            ->keyBy(fn ($r) => mb_strtolower(trim($r->label)));

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped++;

                continue;
            }

            $phone = trim((string) ($row['phone_number'] ?? ''));
            $typeRaw = strtoupper(trim((string) ($row['guest_type'] ?? '')));
            $guestType = $typeRaw === 'VIP' ? 'VIP' : 'Regular';

            $relationId = null;
            $relationLabel = trim((string) ($row['relation'] ?? ''));
            if ($relationLabel !== '') {
                $key = mb_strtolower($relationLabel);
                $matched = $relationsByLabel->get($key);
                if ($matched) {
                    $relationId = $matched->id;
                } else {
                    $warnings[] = 'Baris '.($index + 2).': relasi "'.$relationLabel.'" tidak ditemukan, dikosongkan.';
                }
            }

            $guest = $event->guests()->create([
                'name' => $name,
                'phone_number' => $phone !== '' ? $phone : null,
                'guest_type' => $guestType,
                'guest_relation_id' => $relationId,
            ]);

            $relative = $qr->generateAndStore($guest, $guest->secret_token);
            $guest->forceFill(['qr_code_path' => $relative])->save();

            $created++;
        }

        return [
            'created' => $created,
            'skipped_empty_rows' => $skipped,
            'warnings' => $warnings,
        ];
    }

    public function downloadTemplate(string $format = 'xlsx'): StreamedResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new RuntimeException('Unsupported template format');
        }

        $filename = "guest-import-template.{$format}";

        if ($format === 'xlsx') {
            $path = resource_path('templates/guest-import-template.xlsx');
            if (! is_readable($path)) {
                throw new RuntimeException('Template file is missing on server');
            }

            return response()->streamDownload(function () use ($path) {
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    return;
                }
                fpassthru($handle);
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        // CSV without PhpSpreadsheet (shared hosting safe)
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);
            foreach (self::SAMPLE_ROWS as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['phone_number'],
                    $row['guest_type'],
                    $row['relation'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<array{name: string, phone_number: string, guest_type: string, relation: string}>
     */
    protected function parseRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($file->getRealPath());
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseXlsx($file->getRealPath());
        }

        $mime = $file->getMimeType() ?? '';
        if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel')) {
            return $this->parseXlsx($file->getRealPath());
        }

        return $this->parseCsv($file->getRealPath());
    }

    /**
     * @return list<array{name: string, phone_number: string, guest_type: string, relation: string}>
     */
    protected function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read CSV file');
        }

        $headerLine = fgetcsv($handle);
        if ($headerLine === false) {
            fclose($handle);

            throw new RuntimeException('CSV is empty');
        }

        if (isset($headerLine[0])) {
            $headerLine[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerLine[0]);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $headerLine);
        $indexes = $this->resolveColumnIndexes($header);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $this->mapIndexedRow($row, $indexes);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Read .xlsx with PHP ZipArchive so shared hosting does not need PhpSpreadsheet.
     *
     * @return list<array{name: string, phone_number: string, guest_type: string, relation: string}>
     */
    protected function parseXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Server tidak bisa membaca XLSX. Simpan file sebagai CSV lalu import ulang.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak bisa dibaca. Gunakan .xlsx (bukan .xls) atau simpan sebagai CSV.');
        }

        $shared = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false || $sheetXml === '') {
            throw new RuntimeException('Spreadsheet is empty');
        }

        $data = $this->readXlsxRows($sheetXml, $shared);
        if ($data === []) {
            throw new RuntimeException('Spreadsheet is empty');
        }

        $headerLine = array_shift($data);
        $header = array_map(
            fn ($h) => strtolower(trim((string) ($h ?? ''))),
            $headerLine ?? []
        );
        $indexes = $this->resolveColumnIndexes($header);

        $rows = [];
        foreach ($data as $row) {
            $rows[] = $this->mapIndexedRow($row, $indexes);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    protected function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }

        $doc = $this->loadXlsxXml($xml);
        $strings = [];
        foreach ($doc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    protected function readXlsxRows(string $sheetXml, array $shared): array
    {
        $doc = $this->loadXlsxXml($sheetXml);
        $rows = [];

        foreach ($doc->getElementsByTagName('row') as $rowNode) {
            $cells = [];
            foreach ($rowNode->getElementsByTagName('c') as $cell) {
                if (! $cell->parentNode->isSameNode($rowNode)) {
                    continue;
                }
                $ref = (string) $cell->getAttribute('r');
                $index = $this->xlsxColumnIndex($ref);
                $cells[$index] = $this->xlsxCellValue($cell, $shared);
            }
            if ($cells === []) {
                $rows[] = [];

                continue;
            }
            $max = max(array_keys($cells));
            $line = array_fill(0, $max + 1, '');
            foreach ($cells as $index => $value) {
                $line[$index] = $value;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $shared
     */
    protected function xlsxCellValue(\DOMElement $cell, array $shared): string
    {
        $type = (string) $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($cell->getElementsByTagName('t') as $node) {
                $text .= $node->textContent;
            }

            return $text;
        }

        $value = '';
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'v') {
                $value = $child->textContent;
                break;
            }
        }

        if ($type === 's') {
            return $shared[(int) $value] ?? '';
        }

        return $value;
    }

    protected function xlsxColumnIndex(string $cellRef): int
    {
        if (preg_match('/^([A-Z]+)/i', $cellRef, $match) !== 1) {
            return 0;
        }

        $index = 0;
        foreach (str_split(strtoupper($match[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    protected function loadXlsxXml(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array{name: int, phone_number: int|false, guest_type: int|false, relation: int|false}  $indexes
     * @return array{name: string, phone_number: string, guest_type: string, relation: string}
     */
    protected function mapIndexedRow(array $row, array $indexes): array
    {
        return [
            'name' => (string) ($row[$indexes['name']] ?? ''),
            'phone_number' => $indexes['phone_number'] !== false
                ? (string) ($row[$indexes['phone_number']] ?? '')
                : '',
            'guest_type' => $indexes['guest_type'] !== false
                ? (string) ($row[$indexes['guest_type']] ?? '')
                : '',
            'relation' => $indexes['relation'] !== false
                ? (string) ($row[$indexes['relation']] ?? '')
                : '',
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{name: int, phone_number: int|false, guest_type: int|false, relation: int|false}
     */
    protected function resolveColumnIndexes(array $header): array
    {
        $idxName = array_search('name', $header, true);
        if ($idxName === false) {
            throw new RuntimeException('File must include a name column');
        }

        return [
            'name' => $idxName,
            'phone_number' => array_search('phone_number', $header, true),
            'guest_type' => array_search('guest_type', $header, true),
            'relation' => array_search('relation', $header, true),
        ];
    }
}
