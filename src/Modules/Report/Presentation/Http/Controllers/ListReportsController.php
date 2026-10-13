<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\DTO\SearchReportsInputDTO;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Application\Interfaces\Usecase\SearchReportsUsecaseInterface;
use src\Modules\Report\Presentation\Http\Requests\ListReportsRequest;
use src\Modules\Report\Presentation\Resources\ReportConsultationResource;
use src\Modules\Report\Presentation\Resources\ReportResource;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class ListReportsController extends Controller
{
    public function __construct(private readonly SearchReportsUsecaseInterface $usecase, private readonly ReportAccessServiceInterface $access) {}

    public function __invoke(ListReportsRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            /** @var User $user */
            $user = $request->user();
            $result = $this->usecase->__invoke(new SearchReportsInputDTO(
                actorId: $user->uuid,
                page: isset($data['page']) ? (int) $data['page'] : null,
                perPage: (int) ($data['per_page'] ?? 10),
                municipalityId: isset($data['municipality_id']) ? (int) $data['municipality_id'] : null,
                typology: $data['typology'] ?? null,
                userId: $data['user_id'] ?? null,
                coordinationId: isset($data['coordination_id']) ? (int) $data['coordination_id'] : null,
            ));
            $response = ['reports' => $result->pagination === null
                ? ReportResource::collectionForReader($result->reports, $user->uuid, $this->access, $request)
                : ReportConsultationResource::collection($result->reports)->resolve($request)];
            if ($result->pagination !== null) {
                $response['pagination'] = $result->pagination;
            }

            return (new ResponseJsend($response))->toJsonResponse();
        } catch (Throwable $e) {
            report($e);

            return (new ResponseJsend(status: ResponseJsend::STATUS_ERROR, message: 'An unexpected error occurred', code: 500))->toJsonResponse(500);
        }
    }
}
