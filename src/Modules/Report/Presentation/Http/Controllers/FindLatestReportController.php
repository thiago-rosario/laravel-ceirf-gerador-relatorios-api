<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\FindLatestReportInputDTO;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindLatestReportUsecaseInterface;
use src\Modules\Report\Presentation\Http\Requests\ReportIdRequest;
use src\Modules\Report\Presentation\Resources\ReportResource;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class FindLatestReportController extends Controller
{
    public function __construct(private readonly FindLatestReportUsecaseInterface $usecase, private readonly ReportAccessServiceInterface $access) {}

    public function __invoke(ReportIdRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->usecase->__invoke(new FindLatestReportInputDTO(id: $data['id']));
            /** @var User $user */
            $user = $request->user();
            if (! $this->access->canRead($user->uuid, $result->report->id)) {
                return (new ResponseJsend(status: ResponseJsend::STATUS_ERROR, message: 'Acesso não autorizado.', code: 403))->toJsonResponse(403);
            }

            return (new ResponseJsend(ReportResource::forReader($result->report, $user->uuid, $this->access)->resolve($request)))->toJsonResponse();
        } catch (ReportNotFoundException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(404);
        } catch (Throwable $e) {
            report($e);

            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'An unexpected error occurred',
                code: 500,
            );

            return $response->toJsonResponse(500);
        }
    }
}
