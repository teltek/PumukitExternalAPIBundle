<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\MultimediaObjectEventDispatcherService;
use Symfony\Component\HttpFoundation\Response;

class APICommonService
{
    protected $documentManager;
    protected $factoryService;
    protected $pumukitLocales;
    private $multimediaObjectEventDispatcherService;

    public function __construct(
        DocumentManager $documentManager,
        FactoryService $factoryService,
        MultimediaObjectEventDispatcherService $multimediaObjectEventDispatcherService,
        array $pumukitLocales
    ) {
        $this->documentManager = $documentManager;
        $this->factoryService = $factoryService;
        $this->multimediaObjectEventDispatcherService = $multimediaObjectEventDispatcherService;
        $this->pumukitLocales = $pumukitLocales;
    }

    protected function generateXML(MultimediaObject $multimediaObject): \SimpleXMLElement
    {
        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $xml->addAttribute('id', $multimediaObject->getId());
        $publicDate = $multimediaObject->getPublicDate();
        $xml->addAttribute('start', $publicDate->setTimezone(new \DateTimeZone('Z'))->format('Y-m-d\TH:i:s\Z'));

        foreach ($multimediaObject->getMaterials() as $material) {
            $attachment = $xml->attachments->addChild('attachment');
            $attachment->addAttribute('id', $material->getId());
            $attachment->addChild('mimetype', $material->getMimeType());
            $tags = $attachment->addChild('tags');
            foreach ($material->getTags() as $tag) {
                $tags->addChild('tag', $tag);
            }
            $attachment->addChild('url', '');
            $attachment->addChild('size', '');
        }

        return $xml;
    }

    protected function getMultimediaObjectFromMediaPackageXML(string $mediaPackage): MultimediaObject
    {
        try {
            $mediaPackage = simplexml_load_string($mediaPackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            '_id' => (string) $mediaPackage['id'],
        ]);

        if (!$multimediaObject || !$multimediaObject instanceof MultimediaObject) {
            $msg = sprintf('The multimedia object with "id" "%s" cannot be found on the database', (string) $mediaPackage['id']);

            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        return $multimediaObject;
    }

    protected function createSeriesForMediaPackage($user, array $requestParameters): Series
    {
        if (!isset($requestParameters['seriesTitle'])) {
            return $this->factoryService->createSeries($user);
        }

        $seriesTitle = $this->processSeriesTitle($requestParameters['seriesTitle']);

        return $this->factoryService->createSeries($user, $seriesTitle);
    }

    protected function processSeriesTitle(string $requestTitle): array
    {
        $seriesTitle = [];
        foreach ($this->pumukitLocales as $locale) {
            $seriesTitle[$locale] = $requestTitle;
        }

        return $seriesTitle;
    }

    protected function multimediaObjectDispatchUpdate(MultimediaObject $multimediaObject): void
    {
        $this->multimediaObjectEventDispatcherService->dispatchUpdate($multimediaObject);
    }
}
