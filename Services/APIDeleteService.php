<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\MultimediaObjectEventDispatcherService;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Component\HttpFoundation\Response;

class APIDeleteService extends APICommonService
{
    private $tagService;
    private $allowedTagToDelete;

    public function __construct(
        DocumentManager $documentManager,
        FactoryService $factoryService,
        array $pumukitLocales,
        TagService $tagService,
        MultimediaObjectEventDispatcherService $multimediaObjectEventDispatcherService,
        string $allowedTagToDelete
    ) {
        parent::__construct($documentManager, $factoryService, $multimediaObjectEventDispatcherService, $pumukitLocales);
        $this->documentManager = $documentManager;
        $this->tagService = $tagService;
        $this->allowedTagToDelete = $allowedTagToDelete;
    }

    public function removeTagFromMultimediaObject(string $id, string $tagId = null, string $tagCod = null): void
    {
        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            '_id' => $id,
        ]);

        if (!$multimediaObject || !$multimediaObject instanceof MultimediaObject) {
            $msg = sprintf('The multimedia object with "id" "%s" cannot be found on the database', $id);

            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        $criteria = [];

        if ($tagId) {
            $criteria['_id'] = $tagId;
        }

        if ($tagCod) {
            $criteria['cod'] = $tagCod;
        }

        $tag = $this->documentManager->getRepository(Tag::class)->findOneBy($criteria);

        if (!$tag instanceof Tag) {
            $msg = sprintf('The tag with criteria %s cannot be found on the database', json_encode($criteria, JSON_THROW_ON_ERROR));

            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        if (!$multimediaObject->containsTagWithCod($tag->getCod())) {
            $msg = sprintf('The multimedia object %s does not contain the tag %s', $multimediaObject->getId(), $tag->getCod());

            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        if (!$this->canTagBeRemoved($tag)) {
            $msg = sprintf('You are not allowed to remove the tag %s', $tag->getCod());

            throw new \Exception($msg, Response::HTTP_FORBIDDEN);
        }

        $this->tagService->removeTagFromMultimediaObject($multimediaObject, $tag->getId(), false);
        $this->documentManager->flush();
    }

    private function canTagBeRemoved(Tag $tag): bool
    {
        $tags = $this->getTagsAllowedToDelete();

        return in_array($tag->getCod(), $tags);
    }

    private function getTagsAllowedToDelete(): array
    {
        return [$this->allowedTagToDelete];
    }
}
