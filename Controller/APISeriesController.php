<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\ExternalAPIBundle\Services\APISeriesService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/series")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class APISeriesController extends AbstractController
{
    private $APISeriesService;

    public function __construct(APISeriesService $APISeriesService)
    {
        $this->APISeriesService = $APISeriesService;
    }

    /**
     * @Route("", methods="POST")
     * @Route("/", methods="POST")
     */
    public function createAction(Request $request): ?Response
    {
        $title = $request->request->get('title');
        $series = $this->APISeriesService->create($this->getUser(), $title);

        return new Response($series->getId());
    }
}
