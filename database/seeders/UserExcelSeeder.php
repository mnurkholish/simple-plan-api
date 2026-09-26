<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Spatie\Permission\Models\Role;
use ZipArchive;

class UserExcelSeeder extends Seeder
{
    /**
     * Aliases are only used when the Excel value does not already match a master unit.
     *
     * @var array<string, string>
     */
    private const UNIT_ALIASES = [
        'informasi dan teknologi' => 'it',
        'kebesihan' => 'kebersihan',
    ];

    /** @var array<string, string> */
    private const ROLE_ALIASES = [
        'superadmin' => 'super-admin',
        'user unit' => 'user',
        'petugas tik' => 'petugas-tik',
        'management' => 'management',
        'koordinator sapras' => 'koordinator-sarpras',
        'petugas sapras' => 'petugas-sarpras',
    ];

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'Nama',
        'Jabatan',
        'Unit',
        'Tipe Pegawai',
        'NIP',
        'No.HP',
        'Role',
        'Password',
    ];

    public function __construct(private readonly ?string $sourcePath = null) {}

    public function run(): void
    {
        $path = $this->sourcePath ?? storage_path('app/seed/users.xlsx');

        if (! is_file($path)) {
            $this->command?->warn("User Excel seeder dilewati: file tidak ditemukan di {$path}.");

            return;
        }

        $rows = $this->readRows($path);
        $units = $this->unitsByNormalizedName();
        $roles = $this->rolesByNormalizedName();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            if ($this->isEmptyRow($row['values'])) {
                continue;
            }

            $values = array_map($this->cleanCell(...), $row['values']);
            $validator = Validator::make($values, [
                'Nama' => ['required', 'string', 'max:255'],
                'Jabatan' => ['required', 'string', 'max:255'],
                'Unit' => ['required', 'string'],
                'NIP' => ['required', 'string', 'max:255'],
                'No\.HP' => ['required', 'string', 'max:20'],
                'Role' => ['required', 'string'],
                'Password' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                $this->skipRow(
                    $result,
                    $row['number'],
                    'kolom tidak valid: '.implode(', ', $validator->errors()->keys()),
                );

                continue;
            }

            $unit = $this->resolveUnit($values['Unit'], $units);
            $role = $this->resolveRole($values['Role'], $roles);

            if (! $unit || ! $role) {
                $missing = collect([
                    $unit ? null : "Unit '{$values['Unit']}'",
                    $role ? null : "Role '{$values['Role']}'",
                ])->filter()->implode(' dan ');

                $this->skipRow($result, $row['number'], "{$missing} tidak ditemukan di data master");

                continue;
            }

            try {
                $operation = DB::transaction(function () use ($values, $unit, $role): string {
                    $user = User::query()->where('nip', $values['NIP'])->first();
                    $email = $values['NIP'].'@simpleplan.local';
                    $conflictingUser = User::query()
                        ->where(function ($query) use ($email, $values): void {
                            $query
                                ->where('email', $email)
                                ->orWhere('no_hp', $values['No.HP']);
                        })
                        ->when($user, fn ($query) => $query->where('id', '!=', $user->getKey()))
                        ->exists();

                    if ($conflictingUser) {
                        return 'conflict';
                    }

                    $operation = $user ? 'updated' : 'created';
                    $user ??= new User;
                    $attributes = [
                        'name' => $values['Nama'],
                        'jabatan' => $values['Jabatan'],
                        'nip' => $values['NIP'],
                        'no_hp' => $values['No.HP'],
                        'email' => $email,
                        'unit_id' => $unit->getKey(),
                    ];

                    if (! $user->exists) {
                        $attributes['status'] = 'active';
                        $attributes['status_user'] = 'Aktif';
                    }

                    if (! $user->exists || ! Hash::check($values['Password'], $user->password)) {
                        $attributes['password'] = Hash::make($values['Password']);
                    }

                    $user->fill($attributes)->save();
                    $user->syncRoles([$role]);

                    return $operation;
                });
            } catch (QueryException) {
                $this->skipRow($result, $row['number'], 'gagal karena konflik database');

                continue;
            }

            if ($operation === 'conflict') {
                $this->skipRow($result, $row['number'], 'email atau No.HP sudah digunakan user lain');

                continue;
            }

            $result[$operation]++;
        }

        $this->command?->info(sprintf(
            'Import user Excel selesai: %d dibuat, %d diperbarui, %d dilewati.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));
    }

    /**
     * @return array<string, Unit>
     */
    private function unitsByNormalizedName(): array
    {
        return Unit::query()
            ->orderBy('id')
            ->get()
            ->reduce(function (array $units, Unit $unit): array {
                $units[$this->normalizeLabel($unit->unit_name)] ??= $unit;

                return $units;
            }, []);
    }

    /**
     * @return array<string, Role>
     */
    private function rolesByNormalizedName(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->get()
            ->reduce(function (array $roles, Role $role): array {
                $roles[$this->normalizeLabel($role->name)] ??= $role;

                return $roles;
            }, []);
    }

    /**
     * @param  array<string, Unit>  $units
     */
    private function resolveUnit(string $value, array $units): ?Unit
    {
        $name = $this->normalizeLabel($value);

        return $units[$name] ?? $units[self::UNIT_ALIASES[$name] ?? ''] ?? null;
    }

    /**
     * @param  array<string, Role>  $roles
     */
    private function resolveRole(string $value, array $roles): ?Role
    {
        $name = $this->normalizeLabel($value);

        return $roles[$name] ?? $roles[self::ROLE_ALIASES[$name] ?? ''] ?? null;
    }

    private function normalizeLabel(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function cleanCell(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (string $value): bool => trim($value) === '');
    }

    /**
     * @param  array{created: int, updated: int, skipped: int}  $result
     */
    private function skipRow(array &$result, int $rowNumber, string $reason): void
    {
        $result['skipped']++;
        $this->command?->warn("Baris {$rowNumber} dilewati: {$reason}.");
    }

    /**
     * @return list<array{number: int, values: array<string, string>}>
     */
    private function readRows(string $path): array
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException("File Excel tidak dapat dibuka: {$path}");
        }

        try {
            $sharedStrings = $this->readSharedStrings($archive);
            $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');

            if ($worksheetXml === false) {
                throw new RuntimeException('Worksheet pertama tidak ditemukan di file Excel user.');
            }

            $worksheet = $this->loadXml($worksheetXml, 'worksheet');
            $rawRows = [];

            foreach ($worksheet->xpath('//*[local-name()="row"]') ?: [] as $row) {
                $values = [];

                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    if (! preg_match('/^[A-Z]+/i', (string) $cell['r'], $matches)) {
                        continue;
                    }

                    $values[strtoupper($matches[0])] = $this->cellValue($cell, $sharedStrings);
                }

                $rawRows[] = [
                    'number' => (int) $row['r'],
                    'values' => $values,
                ];
            }
        } finally {
            $archive->close();
        }

        if ($rawRows === []) {
            throw new RuntimeException('File Excel user tidak memiliki baris data.');
        }

        $headerRow = array_shift($rawRows);
        $headers = array_map($this->cleanCell(...), $headerRow['values']);
        $missingHeaders = array_diff(self::REQUIRED_HEADERS, $headers);

        if ($missingHeaders !== []) {
            throw new RuntimeException(
                'Kolom Excel user tidak lengkap: '.implode(', ', $missingHeaders),
            );
        }

        return array_map(function (array $row) use ($headers): array {
            $values = [];

            foreach ($headers as $column => $header) {
                $values[$header] = $row['values'][$column] ?? '';
            }

            return [
                'number' => $row['number'],
                'values' => $values,
            ];
        }, $rawRows);
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];

        foreach ($this->loadXml($xml, 'shared strings')->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $sharedStrings[] = $this->textNodes($item);
        }

        return $sharedStrings;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return $this->textNodes($cell);
        }

        $nodes = $cell->xpath('./*[local-name()="v"]') ?: [];
        $value = isset($nodes[0]) ? (string) $nodes[0] : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function textNodes(SimpleXMLElement $element): string
    {
        return collect($element->xpath('.//*[local-name()="t"]') ?: [])
            ->map(fn (SimpleXMLElement $node): string => (string) $node)
            ->implode('');
    }

    private function loadXml(string $xml, string $section): SimpleXMLElement
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        if ($element === false) {
            throw new RuntimeException("XML {$section} pada file Excel user tidak valid.");
        }

        return $element;
    }
}
