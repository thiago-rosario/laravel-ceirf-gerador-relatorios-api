<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Application\Interfaces\Service;

use src\Modules\Report\Domain\Entity\ReportEntity;

interface ReportMediaRegistryServiceInterface
{
    public function markLinked(ReportEntity $report): void;
}
