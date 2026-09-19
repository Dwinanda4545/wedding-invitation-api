<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $this->assertSpreadsheetAvailable();

            return $this->parseSpreadsheet($file->getRealPath());
        }

        $mime = $file->getMimeType() ?? '';
        if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel')) {
            $this->assertSpreadsheetAvailable();

            return $this->parseSpreadsheet($file->getRealPath());
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
     * @return list<array{name: string, phone_number: string, guest_type: string, relation: string}>
     */
    protected function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if ($data === []) {
            throw new RuntimeException('Spreadsheet is empty');
        }

        $headerLine = array_shift($data);
        $header = array_map(
            fn ($h) => strtolower(trim((string) ($h ?? ''))),
            $headerLine
        );
        $indexes = $this->resolveColumnIndexes($header);

        $rows = [];
        foreach ($data as $row) {
            $rows[] = $this->mapIndexedRow($row, $indexes);
        }

        return $rows;
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

    protected function assertSpreadsheetAvailable(): void
    {
        if (! class_exists(Spreadsheet::class)) {
            throw new RuntimeException(
                'Import XLSX membutuhkan PhpSpreadsheet di server. Upload vendor (lihat deploy-rumahweb) atau gunakan file CSV.'
            );
        }
    }

    protected function buildTemplateSpreadsheet(): Spreadsheet
    {
        $this->assertSpreadsheetAvailable();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Guests');

        foreach (self::HEADERS as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        foreach (self::SAMPLE_ROWS as $rowIndex => $sample) {
            $sheet->setCellValue([1, $rowIndex + 2], $sample['name']);
            $sheet->setCellValue([2, $rowIndex + 2], $sample['phone_number']);
            $sheet->setCellValue([3, $rowIndex + 2], $sample['guest_type']);
            $sheet->setCellValue([4, $rowIndex + 2], $sample['relation']);
        }

        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
