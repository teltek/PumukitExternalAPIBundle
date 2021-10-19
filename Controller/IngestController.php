<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\EncoderBundle\Services\ProfileService;
use Pumukit\ExternalAPIBundle\Services\APIService;
use Pumukit\SchemaBundle\Document\Role;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/ingest", methods="POST")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class IngestController extends AbstractController
{
    private $documentManager;
    private $APIService;
    private $profileService;
    private $predefinedHeaders = [
        'Content-Type' => 'text/xml',
    ];

    public function __construct(
        DocumentManager $documentManager,
        APIService $APIService,
        ProfileService $profileService
    ) {
        $this->documentManager = $documentManager;
        $this->APIService = $APIService;
        $this->profileService = $profileService;
    }

    /**
     * @Route("/createMediaPackage")
     */
    public function createMediaPackageAction(Request $request): ?Response
    {
        try {
            $requestParameters = [];
            $customParameters = [
                'series' => false,
                'seriesTitle' => null,
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
            $response = $this->APIService->createMediaPackage($requestParameters, $this->getUser());

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
            $basicRequestParameters = $this->getBasicRequestParameters($request);
            $customParameters = [
                'language' => 'en',
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $basicRequestParameters, $customParameters);
            $requestParameters['overriding'] = $request->request->get('overriding');

            $response = $this->APIService->addAttachment($requestParameters);

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
            $requestParameters = $this->getBasicRequestParameters($request);

            $customParameters = [
                'profile' => $this->profileService->getDefaultMasterProfile(),
                'priority' => 2,
                'language' => 'en',
                'description' => '',
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
            $response = $this->APIService->addTrack($requestParameters);

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
            $requestParameters = $this->getBasicRequestParameters($request);
            $response = $this->APIService->addCatalog($requestParameters);

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
            $requestParameters = $this->getBasicRequestParameters($request);
            $customParameters = [
                'series' => false,
                'seriesTitle' => null,
            ];
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
            $response = $this->APIService->addDCCatalog($requestParameters, $this->getUser());

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

        $customParameters = [
            'series' => false,
            'accessRights' => false,
            'title' => '',
            'description' => '',
            'profile' => $this->profileService->getDefaultMasterProfile(),
            'priority' => 2,
            'language' => 'en',
        ];

        try {
            $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);

            $requestParameters = $this->getPeopleFromRoles($request, $requestParameters);

            $response = $this->APIService->addMediaPackage($requestParameters, $this->getUser());

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
        $roles = [];
        foreach ($this->documentManager->getRepository(Role::class)->findAll() as $role) {
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
