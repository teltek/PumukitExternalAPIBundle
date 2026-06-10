<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use Pumukit\SchemaBundle\Document\MediaType\Track;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Direct media download by track id, used by the external transcription system.
 *
 * Replaces the old "/trackfile/{id}" link, which in PuMuKIT 5.1 requires the
 * "play" permission and a player token, and therefore blocks the download of
 * non-public videos. Here the only requirement is the Ingest API credentials:
 * any track can be downloaded regardless of the publication status of its
 * multimedia object. The caller already knows the track id (it picks it from
 * the mmobj listing), so no track selection is done here.
 *
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class DownloadController extends AbstractController
{
    private $documentManager;
    private $logger;

    public function __construct(DocumentManager $documentManager, LoggerInterface $logger)
    {
        $this->documentManager = $documentManager;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/ingest/getDownloadTrack/{trackId}", name="pumukit_external_api_download_track", methods="GET", requirements={"trackId"="[0-9a-fA-F]{24}"})
     */
    public function downloadAction(string $trackId): Response
    {
        $found = $this->findTrackById($trackId);
        if (null === $found) {
            return new Response('Track not found', Response::HTTP_NOT_FOUND);
        }
        [$multimediaObject, $track] = $found;

        $storage = $track->storage();

        if (!$storage->isLocalStorageSystem()) {
            try {
                $externalUrl = $storage->url()->url();
            } catch (\Throwable) {
                $externalUrl = null;
            }

            if (null !== $externalUrl && '' !== $externalUrl) {
                return new RedirectResponse($externalUrl);
            }
        }

        try {
            $filePath = $storage->path()->path();
        } catch (\Throwable) {
            $filePath = null;
        }

        if (null === $filePath || !is_file($filePath)) {
            $this->logger->error(sprintf(
                '[external-api][download] file not found on disk for track %s of mmobj %s',
                $trackId,
                $multimediaObject->getId()
            ));

            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        BinaryFileResponse::trustXSendfileTypeHeader();
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }

    /**
     * @return array{0: MultimediaObject, 1: Track}|null
     */
    private function findTrackById(string $trackId): ?array
    {
        try {
            $objectId = new ObjectId($trackId);
        } catch (\Throwable) {
            return null;
        }

        $multimediaObject = $this->documentManager->createQueryBuilder(MultimediaObject::class)
            ->field('tracks._id')->equals($objectId)
            ->getQuery()->getSingleResult()
        ;

        if (!$multimediaObject instanceof MultimediaObject) {
            return null;
        }

        $track = $multimediaObject->getMediaById($trackId);
        if (!$track instanceof Track) {
            return null;
        }

        return [$multimediaObject, $track];
    }
}
