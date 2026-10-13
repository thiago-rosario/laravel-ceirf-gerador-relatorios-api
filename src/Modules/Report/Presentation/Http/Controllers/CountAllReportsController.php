<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\Interfaces\Usecase\SearchReportsUsecaseInterface;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class CountAllReportsController extends Controller
{
    public function __construct(
        private readonly SearchReportsUsecaseInterface $usecase
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $result = $this->usecase->__invoke(new SearchReportsInputDTO(actorId: $user->uuid, countOnly: true));
            $response = new ResponseJsend(['count' => $result->total]);

            return $response->toJsonResponse();
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
