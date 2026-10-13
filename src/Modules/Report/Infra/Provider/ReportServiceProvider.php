<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Provider;

use Illuminate\Support\ServiceProvider;
use src\Modules\Report\Application\Interfaces\Adapter\ReportGeneratorAdapterInterface;
use src\Modules\Report\Application\Interfaces\Adapter\ReportInputAdapterInterface;
use src\Modules\Report\Application\Interfaces\Adapter\ReportQueryResultAdapterInterface;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportChecklistMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
use src\Modules\Report\Application\Interfaces\Repository\ReportConsultationRepositoryInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportDataMapperServiceInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportFinderServiceInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportPersistenceServiceInterface;
use src\Modules\Report\Application\Interfaces\Usecase\CountAllReportsUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\CountReportsByMonthUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\CreateReportRevisionUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\CreateReportUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindByIdReportUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindLatestReportUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindReportHistoryUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindReportsByCoordinateIdUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindReportsByMunicipalityIdUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindReportsByUserIdAndCoordinateIdUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\FindReportsByUserIdUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\GenerateReportUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\GetDashboardReportsUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\ListReportMunicipalitiesUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\SearchReportsUsecaseInterface;
use src\Modules\Report\Application\Interfaces\Usecase\UpdateReportUsecaseInterface;
use src\Modules\Report\Application\Service\ReportDataMapperService;
use src\Modules\Report\Application\Service\ReportFinderService;
use src\Modules\Report\Application\Usecase\CountAllReportsUsecase;
use src\Modules\Report\Application\Usecase\CountReportsByMonthUsecase;
use src\Modules\Report\Application\Usecase\CreateReportRevisionUsecase;
use src\Modules\Report\Application\Usecase\CreateReportUsecase;
use src\Modules\Report\Application\Usecase\FindByIdReportUsecase;
use src\Modules\Report\Application\Usecase\FindLatestReportUsecase;
use src\Modules\Report\Application\Usecase\FindReportHistoryUsecase;
use src\Modules\Report\Application\Usecase\FindReportsByCoordinateIdUsecase;
use src\Modules\Report\Application\Usecase\FindReportsByMunicipalityIdUsecase;
use src\Modules\Report\Application\Usecase\FindReportsByUserIdAndCoordinateIdUsecase;
use src\Modules\Report\Application\Usecase\FindReportsByUserIdUsecase;
use src\Modules\Report\Application\Usecase\GenerateReportUsecase;
use src\Modules\Report\Application\Usecase\GetDashboardReportsUsecase;
use src\Modules\Report\Application\Usecase\ListReportMunicipalitiesUsecase;
use src\Modules\Report\Application\Usecase\SearchReportsUsecase;
use src\Modules\Report\Application\Usecase\UpdateReportUsecase;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Infra\Adapter\ReportGeneratorAdapter;
use src\Modules\Report\Infra\Adapter\ReportInputAdapter;
use src\Modules\Report\Infra\Adapter\ReportQueryResultAdapter;
use src\Modules\Report\Infra\Mapper\LegacyReportQueryMapper;
use src\Modules\Report\Infra\Mapper\ReportChecklistMapper;
use src\Modules\Report\Infra\Mapper\ReportPersistenceMapper;
use src\Modules\Report\Infra\Repositories\Queries\ReportConsultationEloquentRepository;
use src\Modules\Report\Infra\Repositories\ReportEloquentRepository;
use src\Modules\Report\Infra\Service\ReportAccessService;
use src\Modules\Report\Infra\Service\ReportPersistenceService;

class ReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../Presentation/Views', 'report');
    }

    public function register(): void
    {
        $this->app->bind(ReportAccessServiceInterface::class, ReportAccessService::class);
        $this->app->bind(ReportConsultationRepositoryInterface::class, ReportConsultationEloquentRepository::class);
        $this->app->bind(SearchReportsUsecaseInterface::class, SearchReportsUsecase::class);
        $this->app->bind(ListReportMunicipalitiesUsecaseInterface::class, ListReportMunicipalitiesUsecase::class);
        $this->app->bind(ReportInputAdapterInterface::class, ReportInputAdapter::class);
        $this->app->bind(ReportRepositoryInterface::class, ReportEloquentRepository::class);

        $this->app->bind(ReportDataMapperServiceInterface::class, ReportDataMapperService::class);
        $this->app->bind(ReportFinderServiceInterface::class, ReportFinderService::class);
        $this->app->bind(ReportPersistenceServiceInterface::class, ReportPersistenceService::class);

        $this->app->bind(ReportChecklistMapperInterface::class, ReportChecklistMapper::class);
        $this->app->bind(ReportPersistenceMapperInterface::class, ReportPersistenceMapper::class);
        $this->app->bind(LegacyReportQueryMapperInterface::class, LegacyReportQueryMapper::class);
        $this->app->bind(ReportQueryResultAdapterInterface::class, ReportQueryResultAdapter::class);
        $this->app->bind(ReportGeneratorAdapterInterface::class, ReportGeneratorAdapter::class);

        $this->app->bind(CreateReportUsecaseInterface::class, CreateReportUsecase::class);
        $this->app->bind(UpdateReportUsecaseInterface::class, UpdateReportUsecase::class);
        $this->app->bind(CreateReportRevisionUsecaseInterface::class, CreateReportRevisionUsecase::class);
        $this->app->bind(GenerateReportUsecaseInterface::class, GenerateReportUsecase::class);
        $this->app->bind(FindByIdReportUsecaseInterface::class, FindByIdReportUsecase::class);
        $this->app->bind(FindLatestReportUsecaseInterface::class, FindLatestReportUsecase::class);
        $this->app->bind(FindReportHistoryUsecaseInterface::class, FindReportHistoryUsecase::class);
        $this->app->bind(FindReportsByUserIdUsecaseInterface::class, FindReportsByUserIdUsecase::class);
        $this->app->bind(FindReportsByCoordinateIdUsecaseInterface::class, FindReportsByCoordinateIdUsecase::class);
        $this->app->bind(FindReportsByMunicipalityIdUsecaseInterface::class, FindReportsByMunicipalityIdUsecase::class);
        $this->app->bind(FindReportsByUserIdAndCoordinateIdUsecaseInterface::class, FindReportsByUserIdAndCoordinateIdUsecase::class);
        $this->app->bind(CountAllReportsUsecaseInterface::class, CountAllReportsUsecase::class);
        $this->app->bind(CountReportsByMonthUsecaseInterface::class, CountReportsByMonthUsecase::class);
        $this->app->bind(GetDashboardReportsUsecaseInterface::class, GetDashboardReportsUsecase::class);
    }
}
