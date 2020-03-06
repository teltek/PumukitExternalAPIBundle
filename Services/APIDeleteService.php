<?php

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Component\HttpFoundation\Response;

class APIDeleteService extends APICommonService
{
    /** @var TagService */
    private $tagService;
    private $allowedTagToDelete;

    public function __construct(
        DocumentManager $documentManager,
        FactoryService $factoryService,
        array $pumukitLocales,
        TagService $tagService,
        string $allowedTagToDelete
    ) {
        parent::__construct($documentManager, $factoryService, $pumukitLocales);
        $this->documentManager = $documentManager;
        $this->tagService = $tagService;
        $this->allowedTagToDelete = $allowedTagToDelete;
    }

    public function removeTagFromMediaPackage(array $requestParameters)
    {
        [$mediaPackage] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        if (!$multimediaObject->containsTagWithCod($this->allowedTagToDelete)) {
            $msg = sprintf('The multimedia object with "id" "%s" cannot have "%s" on the database', (string) $mediaPackage['id'], $this->allowedTagToDelete);

            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        $tag = $this->getTagAllowedToDelete();

        $this->tagService->removeTagFromMultimediaObject($multimediaObject, $tag->getId(), true);

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    private function getTagAllowedToDelete(): Tag
    {
        $tag = $this->documentManager->getRepository(Tag::class)->findOneBy(['cod' => $this->allowedTagToDelete]);
        if (!$tag) {
            $msg = sprintf('The tag with "code" "%s" cannot be found on the database', (string) $this->allowedTagToDelete);

            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        return $tag;
    }
}
