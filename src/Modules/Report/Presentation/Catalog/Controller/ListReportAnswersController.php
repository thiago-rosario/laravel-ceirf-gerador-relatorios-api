<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportAnswersUsecaseInterface;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class ListReportAnswersController extends Controller
{
    public function __construct(private readonly ListReportAnswersUsecaseInterface $usecase) {}

    public function __invoke(): JsonResponse
    {
        try {
            return (new ResponseJsend(['answers' => $this->usecase->__invoke()]))->toJsonResponse();
        } catch (Throwable $e) {
            report($e);

            return (new ResponseJsend(status: ResponseJsend::STATUS_ERROR, message: 'An unexpected error occurred', code: 500))->toJsonResponse(500);
        }
    }
}
