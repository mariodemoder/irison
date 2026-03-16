<?php

namespace App\Services\Counters;

use App\Models\Counter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CounterService
{
    public const TABLE_TYPES = ['documents', 'payout', 'bonuses', 'payments'];

    private const DEFAULT_PREFIXES = [
        'documents' => 'FR',
        'payout' => 'AB',
        'bonuses' => 'B0',
        'payments' => 'PA',
    ];

    public function nextFormatted(int $clinicId, string $tableType): string
    {
        $tableType = $this->normalizeTableType($tableType);

        return DB::transaction(function () use ($clinicId, $tableType) {
            $counter = Counter::query()
                ->where('clinic_id', $clinicId)
                ->where('table_type', $tableType)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                $counter = Counter::create([
                    'clinic_id' => $clinicId,
                    'table_type' => $tableType,
                    'prefix' => $this->defaultPrefixFor($tableType),
                    'last_number' => 0,
                ]);
            }

            $counter->last_number = ((int) $counter->last_number) + 1;
            $counter->save();

            return $this->formatCounter($counter->prefix, (int) $counter->last_number);
        }, 3);
    }

    public function getProfileCounters(int $clinicId): array
    {
        $existing = Counter::query()
            ->where('clinic_id', $clinicId)
            ->get()
            ->keyBy('table_type');

        $rows = [];

        foreach (self::TABLE_TYPES as $tableType) {
            $model = $existing->get($tableType);
            $prefix = $model ? (string) $model->prefix : $this->defaultPrefixFor($tableType);
            $lastNumber = $model ? (int) $model->last_number : 0;

            $rows[] = [
                'table_type' => $tableType,
                'prefix' => $prefix,
                'last_number' => $lastNumber,
                'next_preview' => $this->formatCounter($prefix, $lastNumber + 1),
            ];
        }

        return $rows;
    }

    public function upsertClinicCounters(int $clinicId, array $rows): void
    {
        DB::transaction(function () use ($clinicId, $rows) {
            foreach ($rows as $row) {
                $tableType = $this->normalizeTableType((string) ($row['table_type'] ?? ''));
                $prefix = $this->normalizePrefix((string) ($row['prefix'] ?? ''));
                $lastNumber = isset($row['last_number']) ? max((int) $row['last_number'], 0) : 0;

                Counter::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'table_type' => $tableType,
                    ],
                    [
                        'prefix' => $prefix,
                        'last_number' => $lastNumber,
                    ]
                );
            }
        }, 3);
    }

    public function formatCounter(string $prefix, int $number): string
    {
        return $this->normalizePrefix($prefix) . '-' . str_pad((string) max($number, 0), 6, '0', STR_PAD_LEFT);
    }

    public function defaultPrefixFor(string $tableType): string
    {
        return self::DEFAULT_PREFIXES[$tableType] ?? 'GEN';
    }

    private function normalizeTableType(string $tableType): string
    {
        $value = strtolower(trim($tableType));

        if (!in_array($value, self::TABLE_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de contador no válido: ' . $tableType);
        }

        return $value;
    }

    private function normalizePrefix(string $prefix): string
    {
        $value = strtoupper(trim($prefix));
        $value = preg_replace('/[^A-Z0-9]/', '', $value) ?? '';

        if ($value === '') {
            throw new InvalidArgumentException('El prefijo no puede estar vacío');
        }

        if (strlen($value) > 4) {
            throw new InvalidArgumentException('El prefijo no puede tener más de 4 caracteres');
        }

        return $value;
    }
}
