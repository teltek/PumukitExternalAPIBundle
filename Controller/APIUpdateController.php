<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/mmobjs")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class APIUpdateController extends Controller
{
    private $predefinedHeaders = [
        'Content-Type' => 'text/xml',
    ];

    /**
     * @Route("/{id}/tags/{tagId}", methods="DELETE")
     * @Route("/{id}/tags/cod/{tagCod}", methods="DELETE")
     */
    public function removeDataAction(Request $request, string $id, string $tagId = null, string $tagCod = null): ?Response
    {
        $apiDeleteService = $this->get('pumukit_external_api.api_delete_service');

        try {
            $apiDeleteService->removeTagFromMultimediaObject($id, $tagId, $tagCod);
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
