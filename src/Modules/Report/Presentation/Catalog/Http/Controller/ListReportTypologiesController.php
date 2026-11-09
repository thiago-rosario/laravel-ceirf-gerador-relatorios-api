<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Http\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;
use src\Modules\Report\Infra\Catalog\Adapter\ListReportTypologiesDataAdapter;
use src\Modules\Report\Presentation\Catalog\Exception\ListReportTypologiesException;
use src\Modules\Report\Presentation\Catalog\Http\Request\ListReportTypologiesRequest;
use src\Modules\Shared\Helper\ResponseJsend;

class ListReportTypologiesController extends Controller
{
    public function __construct(
        private readonly ListReportTypologiesUsecaseInterface $usecase,
        private readonly ListReportTypologiesDataAdapter $adapter,
    ) {}

    public function __invoke(ListReportTypologiesRequest $request): JsonResponse
    {
        try {
            $input = $this->adapter->fromArray($request->validated());

            $result = $this->usecase->__invoke($input);

            $response = new ResponseJsend($this->adapter->toArray($result));

            return response()
                ->json($response->toArray(), 200);
        } catch (ListReportTypologiesException $e) {
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
