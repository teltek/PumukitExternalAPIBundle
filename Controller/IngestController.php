<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/ingest")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 *
 * Class IngestController
 */
class IngestController extends Controller
{
    /**
     * @Route("/createMediaPackage", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function createMediaPackageAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->createMediaPackage($request, $this->getUser());
    }

    /**
     * @Route("/addAttachment", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addAttachmentAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addAttachment($request);
    }

    /**
     * @Route("/addTrack", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     * @throws \Exception
     */
    public function addTrackAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addTrack($request);
    }

    /**
     * @Route("/addCatalog", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addCatalogAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addCatalog($request);
    }

    /**
     * @Route("/addDCCatalog", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addDCCatalogAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addDCCatalog($request, $this->getUser());
    }

    /**
     * @Route("/addMediaPackage", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     * @throws \Exception
     */
    public function addMediaPackageAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addMediaPackage($request, $this->getUser());
    }
}
