<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\SchemaBundle\Document\Role;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/ingest", methods="POST")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class IngestController extends Controller
{
    private $predefinedHeaders = [
        'Content-Type' => 'text/xml',
    ];

    /**
     * @Route("/createMediaPackage")
     */
    public function createMediaPackageAction(Request $request): ?Response
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = [];
            $customParameters = [
                'series' => false,
                'seriesTitle' => null,
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
            $response = $apiService->createMediaPackage($requestParameters, $this->getUser());

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    /**
     * @Route("/addAttachment")
     */
    public function addAttachmentAction(Request $request): ?Response
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');

            $basicRequestParameters = $this->getBasicRequestParameters($request);
            $customParameters = [
                'language' => 'en',
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $basicRequestParameters, $customParameters);
            $requestParameters['overriding'] = $request->request->get('overriding');

            $response = $apiService->addAttachment($requestParameters);

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    /**
     * @Route("/addTrack")
     */
    public function addTrackAction(Request $request): ?Response
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = $this->getBasicRequestParameters($request);

            $profileService = $this->get('pumukitencoder.profile');
            $customParameters = [
                'profile' => $profileService->getDefaultMasterProfile(),
                'priority' => 2,
                'language' => 'en',
                'description' => '',
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
            $response = $apiService->addTrack($requestParameters);

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    /**
     * @Route("/addCatalog")
     */
    public function addCatalogAction(Request $request): ?Response
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = $this->getBasicRequestParameters($request);
            $response = $apiService->addCatalog($requestParameters);

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    /**
     * @Route("/addDCCatalog")
     */
    public function addDCCatalogAction(Request $request): ?Response
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = $this->getBasicRequestParameters($request);
            $customParameters = [
                'series' => false,
                'seriesTitle' => null,
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
            $response = $apiService->addDCCatalog($requestParameters, $this->getUser());

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    /**
     * @Route("/addMediaPackage")
     */
    public function addMediaPackageAction(Request $request): ?Response
    {
        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No track file uploaded', Response::HTTP_BAD_REQUEST);
        }

        $requestParameters = [
            'flavor' => $request->request->get('flavor'),
            'body' => $request->files->get('BODY'),
        ];

        $profileService = $this->get('pumukitencoder.profile');
        $customParameters = [
            'series' => false,
            'accessRights' => false,
            'title' => '',
            'description' => '',
            'profile' => $profileService->getDefaultMasterProfile(),
            'priority' => 2,
            'language' => 'en',
        ];

        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);

            $requestParameters = $this->getPeopleFromRoles($request, $requestParameters);

            $response = $apiService->addMediaPackage($requestParameters, $this->getUser());

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
    }

    private function getBasicRequestParameters(Request $request): array
    {
        return $this->validatePostData($request->request->get('mediaPackage'), $request->request->get('flavor'), $request->files->get('BODY'));
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

    private function getPeopleFromRoles(Request $request, array $requestParameters): array
    {
        $documentManager = $this->get('doctrine_mongodb.odm.document_manager');
        $roles = [];
        foreach ($documentManager->getRepository(Role::class)->findAll() as $role) {
            $roleCode = $role->getCod();
            $roles[$roleCode] = $request->request->get($roleCode);
        }

        $requestParameters['roles'] = $roles;

        return $requestParameters;
    }

    private function validatePostData($mediaPackage, $flavor, $body): array
    {
        if (!$mediaPackage) {
            throw new \Exception("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$flavor) {
            throw new \Exception("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$body) {
            throw new \Exception('No attachment file', Response::HTTP_BAD_REQUEST);
        }

        return [
            'mediaPackage' => $mediaPackage,
            'flavor' => $flavor,
            'body' => $body,
        ];
    }

    private function generateResponse($response, $status, array $headers): Response
    {
        return new Response($response, $status, $headers);
    }
}
