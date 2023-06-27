<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\ExternalAPIBundle\Services\APIDeleteService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/mmobjs")
 *
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class APIUpdateController extends AbstractController
{
    private $APIDeleteService;

    private $predefinedHeaders = [
        'Content-Type' => 'text/xml',
    ];

    public function __construct(APIDeleteService $APIDeleteService)
    {
        $this->APIDeleteService = $APIDeleteService;
    }

    /**
     * @Route("/{id}/tags/{tagId}", methods="DELETE")
     * @Route("/{id}/tags/cod/{tagCod}", methods="DELETE")
     */
    public function removeDataAction(Request $request, string $id, string $tagId = null, string $tagCod = null): ?Response
    {
        try {
            $this->APIDeleteService->removeTagFromMultimediaObject($id, $tagId, $tagCod);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            $code = $exception->getCode();
            if ($exception->getCode() < 100) {
                $code = 500;
                $message = $exception->__toString();
            }

            return new Response($message, $code, $this->predefinedHeaders);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
