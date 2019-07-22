<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/ingest", methods="POST")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 *
 * Class IngestController
 */
class IngestController extends Controller
{
    /**
     * @Route("/createMediaPackage")
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function createMediaPackageAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->createMediaPackage($request, $this->getUser());
    }

    /**
     * @Route("/addAttachment")
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addAttachmentAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addAttachment($request);
    }

    /**
     * @Route("/addTrack")
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addTrackAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addTrack($request);
    }

    /**
     * @Route("/addCatalog")
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addCatalogAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addCatalog($request);
    }

    /**
     * @Route("/addDCCatalog")
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addDCCatalogAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addDCCatalog($request, $this->getUser());
    }

    /**
     * @Route("/addMediaPackage")
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addMediaPackageAction(Request $request)
    {
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addMediaPackage($request, $this->getUser());
    }
}
