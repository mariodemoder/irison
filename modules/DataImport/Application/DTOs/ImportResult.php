<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\DTOs;

/**
 * Resultado agregado de una importación: contadores y detalle por fila.
 * Se construye de forma fluida dentro de cada importador.
 */
final class ImportResult
{
    public string $entity = '';

    public int $total = 0;

    public int $created = 0;

    public int $skipped = 0;

    /** @var list<array{row:int, message:string}> */
    public array $errors = [];

    /** @var list<array{row:int, message:string}> */
    public array $warnings = [];

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function countRow(): self
    {
        $this->total++;

        return $this;
    }

    public function created(): self
    {
        $this->created++;

        return $this;
    }

    public function skipped(): self
    {
        $this->skipped++;

        return $this;
    }

    public function error(int $row, string $message): self
    {
        $this->errors[] = ['row' => $row, 'message' => $message];

        return $this;
    }

    public function warning(int $row, string $message): self
    {
        $this->warnings[] = ['row' => $row, 'message' => $message];

        return $this;
    }

    /**
     * @return array{entity:string, total:int, created:int, skipped:int, errors:list<array{row:int,message:string}>, warnings:list<array{row:int,message:string}>}
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'total' => $this->total,
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}