<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Report\Application\DTO\FindByIdReportInputDTO;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Application\Exception\ReportPdfUnavailableException;
use src\Modules\Report\Application\Interfaces\Usecase\GetReportPdfUsecaseInterface;
use src\Modules\Report\Presentation\Http\Requests\ReportPdfRequest;
use src\Modules\Shared\Helper\ResponseJsend;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DownloadReportPdfController extends Controller
{
    public function __construct(private readonly GetReportPdfUsecaseInterface $usecase) {}

    public function __invoke(ReportPdfRequest $request): JsonResponse|StreamedResponse
    {
        try {
            $data = $request->validated();
            $file = $this->usecase->__invoke(new FindByIdReportInputDTO($data['id']));

            return response()->streamDownload(function () use ($file): void {
                try {
                    fpassthru($file->stream);
                } finally {
                    if (is_resource($file->stream)) {
                        fclose($file->stream);
                    }
                }
            }, $file->fileName, [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) $file->sizeBytes,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], $data['disposition'] ?? 'attachment');
        } catch (ReportNotFoundException $exception) {
            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $exception->getMessage(),
                code: $exception->getCode(),
            ))->toJsonResponse(404);
        } catch (ReportPdfUnavailableException $exception) {
            if ($exception->getCode() >= 500) {
                report($exception);
            }

            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: $exception->getMessage(),
                code: $exception->getCode(),
            ))->toJsonResponse($exception->getCode());
        } catch (Throwable $exception) {
            report($exception);

            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'Não foi possível obter o PDF no armazenamento. Tente novamente.',
                code: 500,
            ))->toJsonResponse(500);
        }
    }
}
