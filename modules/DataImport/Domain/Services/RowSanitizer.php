<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Services;

use DateTime;

/**
 * Helpers de normalización/parseo de valores de celda CSV.
 */
final class RowSanitizer
{
    /**
     * Valor string recortado, o null si queda vacío. Opcionalmente limitado a $max caracteres.
     */
    public static function string(mixed $value, ?int $max = null): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return $max !== null ? mb_substr($value, 0, $max) : $value;
    }

    /**
     * NIF normalizado (mayúsculas, sin espacios ni guiones), o null si vacío.
     */
    public static function normalizeNif(mixed $value): ?string
    {
        $value = self::string($value);
        if ($value === null) {
            return null;
        }

        return strtoupper(str_replace(['-', ' '], '', $value));
    }

    /**
     * Email normalizado (trim + minúsculas), o null si vacío.
     */
    public static function normalizeEmail(mixed $value): ?string
    {
        $value = self::string($value);
        if ($value === null) {
            return null;
        }

        return strtolower($value);
    }

    /**
     * Entero extraído del valor, o null si no contiene dígitos.
     */
    public static function int(mixed $value): ?int
    {
        $digits = preg_replace('/[^0-9-]/', '', (string) ($value ?? '')) ?: '';
        $digits = trim($digits, '-');

        if ($digits === '') {
            return null;
        }

        return (int) $digits;
    }

    /**
     * Decimal español/inglés → float, o null si no es numérico.
     * Soporta "35,50", "1.234,56", "1234.56".
     */
    public static function float(mixed $value): ?float
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $value = str_replace(["\u{00A0}", ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            // 1.234,56 → puntos = separador de miles
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (str_contains($value, ',')) {
            // 1234,56 → coma decimal española
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            // 1.234.56 → puntos = separador de miles
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Fecha a formato Y-m-d, o null si vacía/no parseable.
     * Acepta DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD y otras vía strtotime.
     */
    public static function dateYmd(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Valida un color CSS (#RGB o #RRGGBB). Devuelve el color normalizado o null si es inválido.
     */
    public static function color(mixed $value): ?string
    {
        $value = self::string($value);
        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);
        if (preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}