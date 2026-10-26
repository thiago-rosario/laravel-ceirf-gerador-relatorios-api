<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use src\Modules\Attachment\Application\Exception\ReportMediaUnavailableException;
use src\Modules\Attachment\Application\Interfaces\Adapter\GetMediaReportDataAdapterInterface;
use src\Modules\Attachment\Application\Interfaces\Usecase\GetMediaReportUsecaseInterface;
use src\Modules\Attachment\Presentation\Http\Requests\GetMediaReportRequest;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Exception\ReportAccessDeniedException;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Shared\Helper\ResponseJsend;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GetMediaReportController extends Controller
{
    public function __construct(
        private readonly GetMediaReportUsecaseInterface $usecase,
        private readonly GetMediaReportDataAdapterInterface $adapter,
    ) {}

    public function __invoke(GetMediaReportRequest $request): JsonResponse|StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $input = $this->adapter->fromArray([...$request->validated(), 'requested_by' => $user->uuid]);
            $file = $this->usecase->__invoke($input);

            return response()->streamDownload(function () use ($file): void {
                try {
                    fpassthru($file->stream);
                } finally {
                    if (is_resource($file->stream)) {
                        fclose($file->stream);
                    }
                }
            }, $file->fileName, [
                'Content-Type' => $file->mimeType,
                'Content-Length' => (string) $file->sizeBytes,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], $input->disposition);
        } catch (ReportNotFoundException $exception) {
            return $this->error($exception, 404);
        } catch (ReportAccessDeniedException $exception) {
            return $this->error($exception, 403);
        } catch (ReportMediaUnavailableException $exception) {
            if ($exception->getCode() >= 500) {
                report($exception);
            }

            return $this->error($exception, $exception->getCode());
        } catch (Throwable $exception) {
            report($exception);

            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'Não foi possível obter o arquivo no armazenamento. Tente novamente.',
                code: 500,
            ))->toJsonResponse(500);
        }
    }

    private function error(Throwable $exception, int $httpStatus): JsonResponse
    {
        return (new ResponseJsend(
            status: ResponseJsend::STATUS_ERROR,
            message: $exception->getMessage(),
            code: $exception->getCode(),
        ))->toJsonResponse($httpStatus);
    }
}
