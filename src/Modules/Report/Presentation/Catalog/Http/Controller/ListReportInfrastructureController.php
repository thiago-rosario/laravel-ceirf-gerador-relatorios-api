<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Http\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportInfrastructureUsecaseInterface;
use src\Modules\Report\Infra\Catalog\Adapter\ListReportInfrastructureDataAdapter;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportInfrastructureException;
use src\Modules\Report\Presentation\Catalog\Http\Request\ListReportInfrastructureRequest;
use src\Modules\Shared\Helper\ResponseJsend;

class ListReportInfrastructureController extends Controller
{
    public function __construct(
        private readonly ListReportInfrastructureUsecaseInterface $usecase,
        private readonly ListReportInfrastructureDataAdapter $adapter,
    ) {}

    public function __invoke(ListReportInfrastructureRequest $request): JsonResponse
    {
        try {
            $input = $this->adapter->fromArray($request->validated());

            $result = $this->usecase->__invoke($input);

            $response = new ResponseJsend($this->adapter->toArray($result));

            return response()
                ->json($response->toArray(), 200);
        } catch (ListReportInfrastructureException $e) {
            $response = new ResponseJsend(
                status: 'error',
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return response()
                ->json($response->toArray(), 400);
        } catch (\Throwable $e) {
            report($e);

            $response = new ResponseJsend(
                status: 'error',
                message: 'An unexpected error occurred',
                code: 500,
            );

            return response()
                ->json($response->toArray(), 500);
        }
    }
}
