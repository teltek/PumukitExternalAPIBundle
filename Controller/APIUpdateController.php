<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/delete/")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class APIUpdateController extends Controller
{
    private $predefinedHeaders = [
        'Content-Type' => 'text/xml',
    ];

    /**
     * @Route("tag", methods="DELETE")
     */
    public function removeDataAction(Request $request): ?Response
    {
        try {
            $allowedTagToRemove = $this->container->getParameter('pumukit_external_api.allowed_removed_tag');
            $apiDeleteService = $this->get('pumukit_external_api.api_delete_service');

            $basicRequestParameters = $this->getBasicRequestParameters($request);
            $customParameters = [
                'series' => false,
            ];

            $requestParameters = $this->getCustomParameterFromRequest($request, $basicRequestParameters, $customParameters);
            $response = $apiDeleteService->removeTagFromMediaPackage($requestParameters, $this->getUser(), $allowedTagToRemove);

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    /**
     * NOTE: Order of parameters its very important on service to assign the correct variable using list.
     */
    private function getCustomParameterFromRequest(Request $request, array $requestParameters, array $customRequestParameters): array
    {
        foreach ($customRequestParameters as $key => $defaultValue) {
            $requestParameters[$key] = $request->request->get($key, $defaultValue);
        }

        return $requestParameters;
    }

    private function generateResponse($response, $status, array $headers): Response
    {
        return new Response($response, $status, $headers);
    }

    private function getBasicRequestParameters(Request $request): array
    {
        return $this->validatePostData($request->request->get('mediaPackage'));
    }

    private function validatePostData($mediaPackage): array
    {
        if (!$mediaPackage) {
            throw new \Exception("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }

        return [
            'mediaPackage' => $mediaPackage,
        ];
    }
}
