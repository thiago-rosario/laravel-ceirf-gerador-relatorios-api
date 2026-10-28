<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportInfrastructureUsecaseInterface;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class ListReportInfrastructureController extends Controller
{
    public function __construct(private readonly ListReportInfrastructureUsecaseInterface $usecase) {}

    public function __invoke(): JsonResponse
    {
        try {
            return (new ResponseJsend(['infrastructure' => $this->usecase->__invoke()]))->toJsonResponse();
        } catch (Throwable $e) {
            report($e);

            return (new ResponseJsend(status: ResponseJsend::STATUS_ERROR, message: 'An unexpected error occurred', code: 500))->toJsonResponse(500);
        }
    }
}
