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
 *
 * Class IngestController
 */
class IngestController extends Controller
{
    private $predefinedHeaders = [
        'Content-Type' => 'text/xml',
    ];

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
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = [];
            $customParameters = [
                'series' => false,
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
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addAttachmentAction(Request $request)
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');

            $requestParameters = $this->getBasicRequestParameters($request);

            $response = $apiService->addAttachment($requestParameters);

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
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
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = $this->getBasicRequestParameters($request);

            $customParameters = [
                'profile' => 'master-copy',
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
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addCatalogAction(Request $request)
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
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function addDCCatalogAction(Request $request)
    {
        try {
            $apiService = $this->get('pumukit_external_api.api_service');
            $requestParameters = $this->getBasicRequestParameters($request);
            $response = $apiService->addDCCatalog($requestParameters, $this->getUser());

            return $this->generateResponse($response, Response::HTTP_OK, $this->predefinedHeaders);
        } catch (\Exception $exception) {
            return $this->generateResponse($exception->getMessage(), $exception->getCode(), $this->predefinedHeaders);
        }
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
            'profile' => 'master-copy',
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

    /**
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return array
     */
    private function getBasicRequestParameters(Request $request)
    {
        return $this->validatePostData($request->request->get('mediaPackage'), $request->request->get('flavor'), $request->files->get('BODY'));
    }

    /**
     * NOTE: Order of parameters its very important on service to assign the correct variable using list.
     *
     * @param Request $request
     * @param array   $requestParameters
     * @param array   $customRequestParameters
     *
     * @return array
     */
    private function getCustomParameterFromRequest(Request $request, array $requestParameters, array $customRequestParameters)
    {
        foreach ($customRequestParameters as $key => $defaultValue) {
            $requestParameters[$key] = $request->request->get($key, $defaultValue);
        }

        return $requestParameters;
    }

    /**
     * @param Request $request
     * @param array   $requestParameters
     *
     * @return array
     */
    private function getPeopleFromRoles(Request $request, array $requestParameters)
    {
        $documentManager = $this->get('doctrine_mongodb.odm.document_manager');
        $roles = [];
        foreach ($documentManager->getRepository(Role::class)->findAll() as $role) {
            $roles[$role->getCod()] = $request->request->get($role->getCod());
        }

        $requestParameters['roles'] = $roles;

        return $requestParameters;
    }

    /**
     * @param string $mediaPackage
     * @param string $flavor
     * @param string $body
     *
     * @throws \Exception
     *
     * @return array
     */
    private function validatePostData($mediaPackage, $flavor, $body)
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

    /**
     * @param string $response
     * @param int    $status
     * @param array  $headers
     *
     * @return Response
     */
    private function generateResponse($response, $status, array $headers)
    {
        return new Response($response, $status, $headers);
    }
}
