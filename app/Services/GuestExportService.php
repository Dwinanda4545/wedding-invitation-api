<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Guest;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class GuestExportService
{
    public const HEADERS = [
        'name',
        'phone_number',
        'guest_type',
        'relation',
        'invitation_url',
        'is_attended',
        'scanned_at',
    ];

    public function download(Event $event): StreamedResponse
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Server tidak bisa membuat XLSX (ZipArchive tidak tersedia).');
        }

        $guests = $event->guests()
            ->with('relation')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $frontend = rtrim((string) config('app.frontend_url', ''), '/');
        $rows = [];
        foreach ($guests as $guest) {
            $rows[] = $this->rowFromGuest($guest, $frontend);
        }

        $binary = $this->buildXlsx(array_merge([self::HEADERS], $rows));
        $filename = 'guests-'.$event->id.'-'.now()->format('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function rowFromGuest(Guest $guest, string $frontend): array
    {
        $url = $frontend !== ''
            ? $frontend.'/invitation/'.$guest->secret_token
            : '';

        return [
            (string) $guest->name,
            (string) ($guest->phone_number ?? ''),
            (string) $guest->guest_type,
            (string) ($guest->relation?->label ?? ''),
            $url,
            $guest->is_attended ? 'Ya' : 'Tidak',
            $guest->scanned_at ? $guest->scanned_at->toDateTimeString() : '',
        ];
    }

    /**
     * @param  list<list<string>>  $matrix  first row = headers
     */
    protected function buildXlsx(array $matrix): string
    {
        $shared = [];
        $sharedIndex = [];
        $addShared = function (string $value) use (&$shared, &$sharedIndex): int {
            if (array_key_exists($value, $sharedIndex)) {
                return $sharedIndex[$value];
            }
            $idx = count($shared);
            $shared[] = $value;
            $sharedIndex[$value] = $idx;

            return $idx;
        };

        $sheetRows = [];
        foreach ($matrix as $r => $cols) {
            $cells = [];
            foreach ($cols as $c => $value) {
                $si = $addShared((string) $value);
                $ref = $this->cellRef($c, $r + 1);
                $cells[] = '<c r="'.$ref.'" t="s"><v>'.$si.'</v></c>';
            }
            $sheetRows[] = '<row r="'.($r + 1).'">'.implode('', $cells).'</row>';
        }

        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
            .count($shared).'" uniqueCount="'.count($shared).'">';
        foreach ($shared as $s) {
            $sharedXml .= '<si><t>'.$this->xmlEscape($s).'</t></si>';
        }
        $sharedXml .= '</sst>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Guests" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file XLSX.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->close();

        $binary = file_get_contents($tmp);
        unlink($tmp);
        if ($binary === false) {
            throw new RuntimeException('Gagal membaca file XLSX.');
        }

        return $binary;
    }

    protected function cellRef(int $colZeroBased, int $rowOneBased): string
    {
        $n = $colZeroBased;
        $letters = '';
        do {
            $letters = chr(65 + ($n % 26)).$letters;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letters.$rowOneBased;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
