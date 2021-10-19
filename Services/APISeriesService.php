<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\MultimediaObjectEventDispatcherService;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Component\HttpFoundation\Response;

class APISeriesService extends APICommonService
{
    public function __construct(
        DocumentManager $documentManager,
        FactoryService $factoryService,
        array $pumukitLocales,
        TagService $tagService,
        MultimediaObjectEventDispatcherService $multimediaObjectEventDispatcherService
    ) {
        parent::__construct($documentManager, $factoryService, $multimediaObjectEventDispatcherService, $pumukitLocales);
        $this->documentManager = $documentManager;
    }

    public function create($user = null, $title = null): Series
    {
        if (is_string($title)) {
            $title = $this->processSeriesTitle($title);
        }

        if ($title && !is_array($title)) {
            throw new \Exception('Error, the title is not a string or an array.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->factoryService->createSeries($user, $title);
    }
}
