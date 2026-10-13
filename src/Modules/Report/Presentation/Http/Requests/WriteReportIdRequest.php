<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;

class WriteReportIdRequest extends ReportIdRequest
{
    public function authorize(ReportAccessServiceInterface $access): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->role !== UserRoleEnum::VIEWER
            && $access->canWrite($user->uuid, (string) $this->route('id'));
    }
}
