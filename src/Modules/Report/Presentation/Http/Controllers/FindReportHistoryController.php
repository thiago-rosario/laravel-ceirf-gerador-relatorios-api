<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\FindByIdReportInputDTO;
use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindByIdReportUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\SearchReportsUsecaseInterface;
use src\Modules\Report\Presentation\Http\Requests\ReportIdRequest;
use src\Modules\Report\Presentation\Resources\ReportResource;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class FindReportHistoryController extends Controller
{
    public function __construct(
        private readonly SearchReportsUsecaseInterface $usecase,
        private readonly FindByIdReportUsecaseInterface $finder,
        private readonly ReportAccessServiceInterface $access,
    ) {}

    public function __invoke(ReportIdRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $report = $this->finder->__invoke(new FindByIdReportInputDTO($data['id']))->report;
            /** @var User $user */
            $user = $request->user();
            $result = $this->usecase->__invoke(new SearchReportsInputDTO(actorId: $user->uuid, rootReportId: $report->rootReportId));

            return (new ResponseJsend(['reports' => ReportResource::collectionForReader($result->reports, $user->uuid, $this->access, $request)]))->toJsonResponse();
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
