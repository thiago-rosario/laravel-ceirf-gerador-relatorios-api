<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Exception\ReportAccessDeniedException;
use src\Modules\Report\Application\Interfaces\Adapter\ReportInputAdapterInterface;
use src\Modules\Report\Application\Interfaces\Usecase\CreateReportUsecaseInterface;
use src\Modules\Report\Domain\Exception\DuplicateReportFileException;
use src\Modules\Report\Domain\Exception\FutureInspectionDateException;
use src\Modules\Report\Domain\Exception\IncompleteReportException;
use src\Modules\Report\Domain\Exception\InvalidMunicipalityException;
use src\Modules\Report\Domain\Exception\InvalidReportAttachmentException;
use src\Modules\Report\Domain\Exception\InvalidReportCollaboratorsException;
use src\Modules\Report\Domain\Exception\InvalidReportConclusionException;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\Exception\InvalidReportImageCaptionException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\InvalidReportImageOrderException;
use src\Modules\Report\Domain\Exception\InvalidReportRevisionException;
use src\Modules\Report\Domain\Exception\InvalidReportTypologyException;
use src\Modules\Report\Domain\Exception\InvalidSeiNumberException;
use src\Modules\Report\Domain\Exception\ReportAttachmentDescriptionRequiredException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\Exception\TooManyReportFiguresException;
use src\Modules\Report\Presentation\Http\Requests\CreateReportRequest;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class CreateReportController extends Controller
{
    public function __construct(
        private readonly CreateReportUsecaseInterface $usecase,
        private readonly ReportInputAdapterInterface $adapter,
    ) {}

    public function __invoke(CreateReportRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $result = $this->usecase->__invoke($this->adapter->create($request->validated(), $user->uuid));

            return (new ResponseJsend([
                'id' => $result->id,
                'status' => $result->status,
                'revision_number' => $result->revisionNumber,
                'created_at' => $result->createdAt->format(DATE_ATOM),
            ]))->toJsonResponse(201);
        } catch (InvalidMunicipalityException|InvalidSeiNumberException|InvalidReportTypologyException|FutureInspectionDateException|InvalidReportCollaboratorsException|InvalidReportImageOrderException|InvalidReportImageFormatException|InvalidReportImageCaptionException|ReportImageTooLargeException|TooManyReportFiguresException|DuplicateReportFileException|InvalidReportFileReferenceException|InvalidReportAttachmentException|IncompleteReportException|ReportAttachmentDescriptionRequiredException|InvalidReportConclusionException|InvalidReportRevisionException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(422);
        } catch (ReportAccessDeniedException $e) {
            return (new ResponseJsend(status: ResponseJsend::STATUS_ERROR, message: $e->getMessage(), code: 403))->toJsonResponse(403);
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
