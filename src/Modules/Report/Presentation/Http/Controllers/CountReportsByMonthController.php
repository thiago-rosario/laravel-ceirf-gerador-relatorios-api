<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\Interfaces\Usecase\SearchReportsUsecaseInterface;
use src\Modules\Report\Presentation\Http\Requests\CountReportsByMonthRequest;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class CountReportsByMonthController extends Controller
{
    public function __construct(
        private readonly SearchReportsUsecaseInterface $usecase
    ) {}

    public function __invoke(CountReportsByMonthRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            /** @var User $user */
            $user = $request->user();
            $result = $this->usecase->__invoke(new SearchReportsInputDTO(actorId: $user->uuid, month: $data['month'], countOnly: true));

            return (new ResponseJsend([
                'month' => $data['month'],
                'counts' => $result->total === 0 ? [] : [['month' => $data['month'], 'count' => $result->total]],
            ]))->toJsonResponse();
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
