<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/series")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class APISeriesController extends Controller
{
    /**
     * @Route("", methods="POST")
     * @Route("/", methods="POST")
     */
    public function createAction(Request $request): ?Response
    {
        $apiSeriesService = $this->get('pumukit_external_api.api_series_service');
        $title = $request->request->get('title');
        $series = $apiSeriesService->create($this->getUser(), $title);

        return new Response($series->getId());
    }
}
