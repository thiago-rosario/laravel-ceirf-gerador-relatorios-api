<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\DTO\MunicipalityDataDTO;
use src\Modules\Report\Application\Interfaces\Usecase\ListReportMunicipalitiesUsecaseInterface;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class ListReportMunicipalitiesController extends Controller
{
    public function __construct(private readonly ListReportMunicipalitiesUsecaseInterface $usecase) {}

    public function __invoke(): JsonResponse
    {
        try {
            return (new ResponseJsend(['municipalities' => array_map(
                fn (MunicipalityDataDTO $municipality): array => ['id' => $municipality->id, 'name' => $municipality->name, 'state_code' => $municipality->stateCode],
                $this->usecase->__invoke(),
            )]))->toJsonResponse();
        } catch (Throwable $e) {
            report($e);

            return (new ResponseJsend(status: ResponseJsend::STATUS_ERROR, message: 'An unexpected error occurred', code: 500))->toJsonResponse(500);
        }
    }
}
