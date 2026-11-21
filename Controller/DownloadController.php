<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\BasePlayerBundle\Services\TrackDeliveryService;
use Pumukit\SchemaBundle\Document\MediaType\Track;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Direct media download by track id.
 *
 * The "/trackfile/{id}" link requires the "play" permission and a player token,
 * and therefore blocks the download of non-public videos. Here the only
 * requirement is the Ingest API credentials:
 * any track can be downloaded regardless of the publication status of its
 * multimedia object. The caller already knows the track id (it picks it from
 * the mmobj listing), so no track selection is done here.
 */
#[IsGranted('ROLE_ACCESS_INGEST_API')]
class DownloadController extends AbstractController
{
    private $documentManager;
    private $trackDeliveryService;

    public function __construct(DocumentManager $documentManager, TrackDeliveryService $trackDeliveryService)
    {
        $this->documentManager = $documentManager;
        $this->trackDeliveryService = $trackDeliveryService;
    }

    #[Route('/api/ingest/getDownloadTrack/{trackId}', name: 'pumukit_external_api_download_track', methods: ['GET'], requirements: ['trackId' => '[0-9a-fA-F]{24}'])]
    public function downloadAction(string $trackId): Response
    {
        $found = $this->findTrackById($trackId);
        if (null === $found) {
            return new Response('Track not found', Response::HTTP_NOT_FOUND);
        }
        [, $track] = $found;

        return $this->trackDeliveryService->response($track, true);
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
