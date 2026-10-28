<?php

declare(strict_types=1);

use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportAnswersUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportChecklistUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportForcesUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportInfrastructureUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;
use src\Modules\Report\Application\Catalog\Usecase\ListReportAnswersUsecase;
use src\Modules\Report\Application\Catalog\Usecase\ListReportChecklistUsecase;
use src\Modules\Report\Application\Catalog\Usecase\ListReportForcesUsecase;
use src\Modules\Report\Application\Catalog\Usecase\ListReportInfrastructureUsecase;
use src\Modules\Report\Application\Catalog\Usecase\ListReportTypologiesUsecase;
use src\Modules\Report\Application\Interfaces\Adapter\ReportGeneratorAdapterInterface;
use src\Modules\Report\Application\Interfaces\Adapter\ReportQueryResultAdapterInterface;
use src\Modules\Report\Application\Interfaces\Mapper\LegacyReportQueryMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportChecklistMapperInterface;
use src\Modules\Report\Application\Interfaces\Mapper\ReportPersistenceMapperInterface;
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
use src\Modules\Report\Application\Usecase\UpdateReportUsecase;
use src\Modules\Report\Domain\Repository\ReportRepositoryInterface;
use src\Modules\Report\Infra\Adapter\ReportGeneratorAdapter;
use src\Modules\Report\Infra\Adapter\ReportQueryResultAdapter;
use src\Modules\Report\Infra\Catalog\Repository\ReportCatalogRepository;
use src\Modules\Report\Infra\Mapper\LegacyReportQueryMapper;
use src\Modules\Report\Infra\Mapper\ReportChecklistMapper;
use src\Modules\Report\Infra\Mapper\ReportPersistenceMapper;
use src\Modules\Report\Infra\Repositories\ReportEloquentRepository;
use src\Modules\Report\Infra\Service\ReportPersistenceService;

test('resolves report contracts with their complete dependency graphs', function (string $contract, string $implementation): void {
    $resolved = $this->app->make($contract);

    expect($resolved)->toBeInstanceOf($implementation);
})->with([
    'catalog repository' => [ReportCatalogRepositoryInterface::class, ReportCatalogRepository::class],
    'force catalog' => [ListReportForcesUsecaseInterface::class, ListReportForcesUsecase::class],
    'typology catalog' => [ListReportTypologiesUsecaseInterface::class, ListReportTypologiesUsecase::class],
    'answer catalog' => [ListReportAnswersUsecaseInterface::class, ListReportAnswersUsecase::class],
    'infrastructure catalog' => [ListReportInfrastructureUsecaseInterface::class, ListReportInfrastructureUsecase::class],
    'checklist catalog' => [ListReportChecklistUsecaseInterface::class, ListReportChecklistUsecase::class],
    'repository' => [ReportRepositoryInterface::class, ReportEloquentRepository::class],
    'data mapper service' => [ReportDataMapperServiceInterface::class, ReportDataMapperService::class],
    'finder service' => [ReportFinderServiceInterface::class, ReportFinderService::class],
    'persistence service' => [ReportPersistenceServiceInterface::class, ReportPersistenceService::class],
    'checklist mapper' => [ReportChecklistMapperInterface::class, ReportChecklistMapper::class],
    'persistence mapper' => [ReportPersistenceMapperInterface::class, ReportPersistenceMapper::class],
    'legacy mapper' => [LegacyReportQueryMapperInterface::class, LegacyReportQueryMapper::class],
    'query adapter' => [ReportQueryResultAdapterInterface::class, ReportQueryResultAdapter::class],
    'generator adapter' => [ReportGeneratorAdapterInterface::class, ReportGeneratorAdapter::class],
    'creation' => [CreateReportUsecaseInterface::class, CreateReportUsecase::class],
    'update' => [UpdateReportUsecaseInterface::class, UpdateReportUsecase::class],
    'revision' => [CreateReportRevisionUsecaseInterface::class, CreateReportRevisionUsecase::class],
    'generation' => [GenerateReportUsecaseInterface::class, GenerateReportUsecase::class],
    'find by identifier' => [FindByIdReportUsecaseInterface::class, FindByIdReportUsecase::class],
    'latest revision' => [FindLatestReportUsecaseInterface::class, FindLatestReportUsecase::class],
    'revision history' => [FindReportHistoryUsecaseInterface::class, FindReportHistoryUsecase::class],
    'reports by user' => [FindReportsByUserIdUsecaseInterface::class, FindReportsByUserIdUsecase::class],
    'reports by coordinate' => [FindReportsByCoordinateIdUsecaseInterface::class, FindReportsByCoordinateIdUsecase::class],
    'reports by municipality' => [FindReportsByMunicipalityIdUsecaseInterface::class, FindReportsByMunicipalityIdUsecase::class],
    'reports by user and coordinate' => [FindReportsByUserIdAndCoordinateIdUsecaseInterface::class, FindReportsByUserIdAndCoordinateIdUsecase::class],
    'total count' => [CountAllReportsUsecaseInterface::class, CountAllReportsUsecase::class],
    'monthly counts' => [CountReportsByMonthUsecaseInterface::class, CountReportsByMonthUsecase::class],
    'dashboard' => [GetDashboardReportsUsecaseInterface::class, GetDashboardReportsUsecase::class],
]);
