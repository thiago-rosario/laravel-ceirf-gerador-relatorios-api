<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use src\Modules\Attachment\Application\Interfaces\Adapter\UploadReportMediaDataAdapterInterface;
use src\Modules\Attachment\Application\Interfaces\Usecase\UploadReportMediaUsecaseInterface;
use src\Modules\Attachment\Presentation\Http\Requests\UploadReportMediaRequest;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Exception\ReportNotFoundException;
use src\Modules\Report\Domain\Exception\DuplicateReportFileException;
use src\Modules\Report\Domain\Exception\InvalidReportFileReferenceException;
use src\Modules\Report\Domain\Exception\InvalidReportImageFormatException;
use src\Modules\Report\Domain\Exception\ReportAlreadyGeneratedException;
use src\Modules\Report\Domain\Exception\ReportImageTooLargeException;
use src\Modules\Report\Domain\Exception\TooManyReportFiguresException;
use src\Modules\Shared\Helper\ResponseJsend;
use Throwable;

class UploadReportMediaController extends Controller
{
    public function __construct(
        private readonly UploadReportMediaUsecaseInterface $usecase,
        private readonly UploadReportMediaDataAdapterInterface $adapter,
    ) {}

    public function __invoke(UploadReportMediaRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $input = $this->adapter->fromArray([...$request->validated(), 'uploaded_by' => $user->id]);

            $result = $this->usecase->__invoke($input);

            $response = new ResponseJsend($this->adapter->toArray($result));

            return $response->toJsonResponse(201);

        } catch (ReportNotFoundException $e) {
            return $this->error($e, 404);

        } catch (ReportAlreadyGeneratedException $e) {
            return $this->error($e, 409);

        } catch (DuplicateReportFileException|TooManyReportFiguresException|InvalidReportFileReferenceException|ReportImageTooLargeException|InvalidReportImageFormatException $e) {
            return $this->error($e, 422);

        } catch (UniqueConstraintViolationException $e) {
            return $this->error(new DuplicateReportFileException(previous: $e), 422);

        } catch (Throwable $e) {
            report($e);

            return (new ResponseJsend(
                status: ResponseJsend::STATUS_ERROR,
                message: 'An unexpected error occurred',
                code: 500,
            ))->toJsonResponse(500);
        }
    }

    private function error(Throwable $e, int $httpStatus): JsonResponse
    {
        return (new ResponseJsend(
            status: ResponseJsend::STATUS_ERROR,
            message: $e->getMessage(),
            code: $e->getCode(),
        ))->toJsonResponse($httpStatus);
    }
}
