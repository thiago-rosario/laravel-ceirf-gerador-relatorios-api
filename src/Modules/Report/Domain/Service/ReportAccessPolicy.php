<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Service;

final class ReportAccessPolicy
{
    /** @param list<array{status: string|null, reviewed: bool}> $reviewDecisions */
    public function isConsultable(string $status, array $reviewDecisions): bool
    {
        if ($status !== 'GENERATED') {
            return false;
        }

        foreach ($reviewDecisions as $decision) {
            if (! $decision['reviewed'] || strtoupper(trim($decision['status'] ?? '')) !== 'APPROVED') {
                return false;
            }
        }

        return true;
    }

    public function canRead(string $role, bool $isAuthor, ?int $userCoordinationId, ?int $reportCoordinationId, bool $consultable): bool
    {
        return $role === 'SUPERUSER' || $consultable
            || ($role === 'REVIEWER' && $userCoordinationId !== null && $userCoordinationId === $reportCoordinationId)
            || ($role === 'OPERATOR' && $isAuthor);
    }

    public function canWrite(string $role, bool $isAuthor, ?int $userCoordinationId, ?int $reportCoordinationId): bool
    {
        if ($role === 'SUPERUSER') {
            return true;
        }

        return $userCoordinationId !== null && $userCoordinationId === $reportCoordinationId
            && ($role === 'REVIEWER' || ($role === 'OPERATOR' && $isAuthor));
    }

    public function canCreate(string $role, ?int $userCoordinationId, ?int $reportCoordinationId): bool
    {
        return $role === 'SUPERUSER'
            || (in_array($role, ['OPERATOR', 'REVIEWER'], true) && $userCoordinationId !== null && $userCoordinationId === $reportCoordinationId);
    }
}
