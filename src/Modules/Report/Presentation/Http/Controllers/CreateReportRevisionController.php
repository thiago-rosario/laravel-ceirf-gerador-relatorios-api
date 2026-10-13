<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\CreateReportRevisionInputDTO;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Interfaces\Usecase\CreateReportRevisionUsecaseInterface;
use src\Modules\Report\Domain\Exception\InvalidReportRevisionException;
use src\Modules\Report\Domain\Exception\ReportNotGeneratedException;
use src\Modules\Report\Presentation\Http\Requests\WriteReportIdRequest;
use src\Modules\Report\Presentation\Resources\ReportResource;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class CreateReportRevisionController extends Controller
{
    public function __construct(private readonly CreateReportRevisionUsecaseInterface $usecase) {}

    public function __invoke(WriteReportIdRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $data = $request->validated();
            $result = $this->usecase->__invoke(new CreateReportRevisionInputDTO(id: $data['id'], createdBy: $user->uuid));

            return (new ResponseJsend(ReportResource::forEditorial($result->report)->resolve($request)))->toJsonResponse(201);
        } catch (ReportNotFoundException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(404);
        } catch (ReportNotGeneratedException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(409);
        } catch (InvalidReportRevisionException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(422);
        } catch (UniqueConstraintViolationException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'A revisão do relatório já existe.',
                code: 409,
            );

            return $response->toJsonResponse(409);
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
