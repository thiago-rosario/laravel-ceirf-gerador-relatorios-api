<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\DTO\GenerateReportInputDTO;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Interfaces\Usecase\GenerateReportUsecaseInterface;
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
use src\Modules\Report\Domain\Exception\ReportAlreadyGeneratedException;
use src\Modules\Report\Domain\Exception\ReportAttachmentDescriptionRequiredException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\Exception\TooManyReportFiguresException;
use src\Modules\Report\Presentation\Http\Requests\WriteReportIdRequest;
use src\Modules\Report\Presentation\Resources\ReportResource;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class GenerateReportController extends Controller
{
    public function __construct(private readonly GenerateReportUsecaseInterface $usecase) {}

    public function __invoke(WriteReportIdRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->usecase->__invoke(new GenerateReportInputDTO(id: $data['id']));

            return (new ResponseJsend(ReportResource::forEditorial($result->report)->resolve($request)))->toJsonResponse();
        } catch (ReportNotFoundException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(404);
        } catch (ReportAlreadyGeneratedException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(409);
        } catch (InvalidMunicipalityException|InvalidSeiNumberException|InvalidReportTypologyException|FutureInspectionDateException|InvalidReportCollaboratorsException|InvalidReportImageOrderException|InvalidReportImageFormatException|InvalidReportImageCaptionException|ReportImageTooLargeException|TooManyReportFiguresException|DuplicateReportFileException|InvalidReportFileReferenceException|InvalidReportAttachmentException|IncompleteReportException|ReportAttachmentDescriptionRequiredException|InvalidReportConclusionException|InvalidReportRevisionException $e) {
            $response = new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $e->getMessage(),
                code: $e->getCode(),
            );

            return $response->toJsonResponse(422);
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
