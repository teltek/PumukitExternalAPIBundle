<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\ExternalAPIBundle\Services\APIDeleteService;
use Pumukit\ExternalAPIBundle\Services\APIErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mmobjs')]
#[IsGranted('ROLE_ACCESS_INGEST_API')]
class APIUpdateController extends AbstractController
{
    private $APIDeleteService;
    private $errorResponse;

    public function __construct(APIDeleteService $APIDeleteService, APIErrorResponse $errorResponse)
    {
        $this->APIDeleteService = $APIDeleteService;
        $this->errorResponse = $errorResponse;
    }

    #[Route('/{id}/tags/{tagId}', methods: ['DELETE'])]
    #[Route('/{id}/tags/cod/{tagCod}', methods: ['DELETE'])]
    public function removeDataAction(Request $request, string $id, ?string $tagId = null, ?string $tagCod = null): ?Response
    {
        try {
            $this->APIDeleteService->removeTagFromMultimediaObject($id, $tagId, $tagCod);
        } catch (\Exception $exception) {
            return $this->errorResponse->fromException($exception);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
