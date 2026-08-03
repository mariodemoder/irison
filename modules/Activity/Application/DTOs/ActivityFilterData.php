<?php

declare(strict_types=1);

namespace Modules\Activity\Application\DTOs;

class ActivityFilterData
{
    public function __construct(
        public readonly string $q,
        public readonly ?string $event,
        public readonly ?int $user_id,
        public readonly ?string $entity,
        public readonly ?string $from_date,
        public readonly ?string $to_date,
        public readonly int $per_page,
    ) {}

    public static function fromRequest(array $input): self
    {
        return new self(
            q: trim((string) ($input['q'] ?? '')),
            event: isset($input['event']) && trim((string) $input['event']) !== '' ? trim((string) $input['event']) : null,
            user_id: isset($input['user_id']) && is_numeric($input['user_id']) ? (int) $input['user_id'] : null,
            entity: isset($input['entity']) && trim((string) $input['entity']) !== '' ? trim((string) $input['entity']) : null,
            from_date: isset($input['from_date']) ? (string) $input['from_date'] : null,
            to_date: isset($input['to_date']) ? (string) $input['to_date'] : null,
            per_page: isset($input['per_page']) && is_numeric($input['per_page']) ? min(max((int) $input['per_page'], 1), 100) : 25,
        );
    }
}
