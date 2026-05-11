<?php

namespace App\Services\Counters;

use App\Models\MySaasCounter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MySaasCounterService
{
    public const TABLE_TYPES = ['billing_payments'];

    private const TABLE_TYPE_ALIASES = [
        'billing_payments' => 'billing_payments'
    ];

    private const DEFAULT_PREFIXES = [
        'billing_payments' => 'FR',
    ];

    public function nextFormatted(string $tableType): string
    {
        $tableType = $this->normalizeTableType($tableType);

        return DB::transaction(function () use ($tableType) {
            $counter = MySaasCounter::query()
                ->whereIn('table_type', $this->candidateTableTypes($tableType))
                ->orderByRaw("CASE WHEN table_type = ? THEN 0 ELSE 1 END", [$tableType])
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                $counter = MySaasCounter::create([
                    'table_type' => $tableType,
                    'prefix' => $this->defaultPrefixFor($tableType),
                    'last_number' => 0,
                ]);
            } elseif ((string) $counter->table_type !== $tableType) {
                $counter->table_type = $tableType;
            }

            $counter->last_number = ((int) $counter->last_number) + 1;
            $counter->save();

            return $this->formatCounter((string) $counter->prefix, (int) $counter->last_number);
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
        $value = self::TABLE_TYPE_ALIASES[$value] ?? $value;

        if (!in_array($value, self::TABLE_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de contador SaaS no válido: ' . $tableType);
        }

        return $value;
    }

    private function candidateTableTypes(string $tableType): array
    {
        return array_values(array_unique([
            $tableType,
            ...array_keys(array_filter(self::TABLE_TYPE_ALIASES, fn (string $mappedType): bool => $mappedType === $tableType)),
        ]));
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