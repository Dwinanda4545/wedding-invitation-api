# Guest Export XLSX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin can download an XLSX of all active event guests including invitation links and attendance columns.

**Architecture:** New `GuestExportService` builds a minimal OOXML `.xlsx` with ZipArchive (no PhpSpreadsheet). `GET /api/events/{event}/guests/export` streams it. Admin Guests page adds an Export button using the same blob-download pattern as the import template.

**Tech Stack:** Laravel 13 API, ZipArchive, PHPUnit, React/Vite admin SPA, axios blob download

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-20-guest-export-xlsx-design.md`
- Format: XLSX only
- Scope: all non–soft-deleted guests for the event
- Columns (exact header order): `name`, `phone_number`, `guest_type`, `relation`, `invitation_url`, `is_attended`, `scanned_at`
- `invitation_url` = `{rtrim(FRONTEND_URL,'/')}/invitation/{secret_token}`; empty if FRONTEND_URL unset
- `is_attended`: `Ya` / `Tidak`
- No PhpSpreadsheet; shared-hosting safe
- No DB migration
- Do not commit unless user asks

---

## File map

| File | Change |
|------|--------|
| `app/Services/GuestExportService.php` | Create: build + stream XLSX |
| `app/Http/Controllers/Api/GuestController.php` | Add `export()` |
| `routes/api.php` | Register GET export before `{guest}` |
| `tests/Feature/GuestExportTest.php` | Feature tests |
| `C:\laragon\www\wedding-invitation-web\src\pages\admin\GuestsPage.tsx` | Export button + download helper |

---

### Task 1: Feature test for export endpoint

**Files:**
- Create: `tests/Feature/GuestExportTest.php`

**Interfaces:**
- Consumes: existing `User::factory()->admin()`, `Event`, `Guest`, `GuestRelation`
- Produces: failing tests that expect `GET /api/events/{id}/guests/export`

- [x] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class GuestExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_guests_xlsx_with_invitation_links(): void
    {
        config(['app.frontend_url' => 'https://wedding.example.com']);

        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);
        $relation = GuestRelation::query()->create([
            'event_id' => $event->id,
            'label' => 'Teman Kerja',
            'sort_order' => 0,
        ]);

        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Budi Santoso',
            'phone_number' => '081234567890',
            'guest_type' => 'VIP',
            'guest_relation_id' => $relation->id,
            'is_attended' => true,
            'scanned_at' => now()->startOfMinute(),
        ]);

        Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Andi',
            'phone_number' => null,
            'guest_type' => 'Regular',
            'is_attended' => false,
        ])->delete(); // soft-deleted — must not appear

        $response = $this->actingAs($admin)
            ->get('/api/events/'.$event->id.'/guests/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            '.xlsx',
            (string) $response->headers->get('content-disposition')
        );

        $tmp = tempnam(sys_get_temp_dir(), 'gx');
        file_put_contents($tmp, $response->streamedContent());

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($shared);
        $this->assertStringContainsString('name', $shared);
        $this->assertStringContainsString('invitation_url', $shared);
        $this->assertStringContainsString('Budi Santoso', $shared);
        $this->assertStringContainsString('Teman Kerja', $shared);
        $this->assertStringContainsString(
            'https://wedding.example.com/invitation/'.$guest->secret_token,
            $shared
        );
        $this->assertStringContainsString('Ya', $shared);
        $this->assertStringNotContainsString('Andi', $shared);
    }

    public function test_export_empty_event_returns_header_only_xlsx(): void
    {
        config(['app.frontend_url' => 'https://wedding.example.com']);
        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Empty',
            'slug' => 'e-'.uniqid(),
        ]);

        $response = $this->actingAs($admin)
            ->get('/api/events/'.$event->id.'/guests/export');

        $response->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'gx');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($shared);
        $this->assertStringContainsString('name', $shared);
        $this->assertStringContainsString('is_attended', $shared);
    }

    public function test_guest_export_requires_auth(): void
    {
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);

        $this->getJson('/api/events/'.$event->id.'/guests/export')
            ->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GuestExportTest`

Expected: FAIL (route/controller/service missing — 404 or similar)

---

### Task 2: GuestExportService + controller + route

**Files:**
- Create: `app/Services/GuestExportService.php`
- Modify: `app/Http/Controllers/Api/GuestController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `Event` with `guests()` SoftDeletes scope; `config('app.frontend_url')`
- Produces: `GuestExportService::download(Event $event): StreamedResponse`
- Produces: `GuestController::export(Event $event, GuestExportService $exporter)`

- [ ] **Step 1: Create GuestExportService**

```php
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
```

- [ ] **Step 2: Add export method on GuestController**

Inside `GuestController`, add:

```php
public function export(Event $event, GuestExportService $exporter): StreamedResponse|\Illuminate\Http\JsonResponse
{
    try {
        return $exporter->download($event);
    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
}
```

Add imports:

```php
use App\Services\GuestExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
```

(`StreamedResponse` may already be imported for `importTemplate`.)

- [ ] **Step 3: Register route**

In `routes/api.php`, inside the admin guests group, **before** `{guest}` routes and near the other guest collection routes:

```php
Route::get('/events/{event}/guests/export', [GuestController::class, 'export']);
```

Place it with `import/template` / `bulk-delete` (collection-level routes).

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=GuestExportTest`

Expected: PASS

---

### Task 3: Frontend Export button

**Files:**
- Modify: `C:\laragon\www\wedding-invitation-web\src\pages\admin\GuestsPage.tsx`

**Interfaces:**
- Consumes: `GET /api/events/{eventId}/guests/export` blob
- Produces: toolbar button **Export XLSX**

- [ ] **Step 1: Add downloadExport helper**

Mirror `downloadImportTemplate`. Prefer Content-Disposition filename:

```tsx
async function downloadExport() {
  if (!Number.isFinite(eventId)) return
  setError(null)
  try {
    const res = await api.get<Blob>(
      `/api/events/${eventId}/guests/export`,
      {
        responseType: 'blob',
        headers: { Accept: '*/*' },
      },
    )
    const data = res.data
    if (data.type?.includes('application/json')) {
      const text = await data.text()
      const parsed = JSON.parse(text) as { message?: string }
      throw new Error(parsed.message || 'Gagal mengekspor tamu')
    }
    const disposition = String(res.headers['content-disposition'] ?? '')
    const match = /filename="?([^";]+)"?/i.exec(disposition)
    const filename = match?.[1] ?? 'guests-export.xlsx'
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
    setToast('Export XLSX diunduh.')
    window.setTimeout(() => setToast(null), 2500)
  } catch (e) {
    setError(
      e instanceof Error ? e.message : 'Gagal mengekspor data tamu.',
    )
  }
}
```

- [ ] **Step 2: Add toolbar button next to Download template**

```tsx
<button
  type="button"
  className="rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-800 shadow-sm hover:bg-stone-50"
  onClick={() => void downloadExport()}
>
  Export XLSX
</button>
```

- [ ] **Step 3: Build frontend zip**

Run from `C:\laragon\www\wedding-invitation-web`:

```powershell
npm run build
# then zip dist → frontend-dist-upload.zip
```

Record the new `index-*.js` filename for the user.

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| GET export endpoint | Task 2 |
| XLSX via ZipArchive | Task 2 |
| Columns + Ya/Tidak + invitation_url | Task 1–2 |
| Soft-deleted excluded | Task 1 |
| Empty event header-only | Task 1 |
| FRONTEND_URL empty → empty URL | covered by row mapping (optional extra assert if needed) |
| Export button + blob download | Task 3 |
| No migration / no PhpSpreadsheet | Global constraints |

## Self-review

- No placeholders.
- Header names match spec exactly.
- Route registered before `{guest}` to avoid capture.
- Commit steps omitted per user preference (commit only if asked).
