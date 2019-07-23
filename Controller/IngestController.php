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
        $requestParameters = [];
        $customParameters = [
            'series' => false,
        ];
        $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->createMediaPackage($requestParameters, $this->getUser());
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
        $requestParameters = $this->getBasicRequestParameters($request);
        if ($requestParameters instanceof Response) {
            return $requestParameters;
        }

        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addAttachment($requestParameters);
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
        $requestParameters = $this->getBasicRequestParameters($request);
        if ($requestParameters instanceof Response) {
            return $requestParameters;
        }

        $customParameters = [
            'profile' => 'master_copy',
            'priority' => 2,
            'language' => 'en',
            'description' => '',
        ];
        $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);
        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addTrack($requestParameters);
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
        $requestParameters = $this->getBasicRequestParameters($request);
        if ($requestParameters instanceof Response) {
            return $requestParameters;
        }

        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addCatalog($requestParameters);
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
        $requestParameters = $this->getBasicRequestParameters($request);
        if ($requestParameters instanceof Response) {
            return $requestParameters;
        }

        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addDCCatalog($requestParameters, $this->getUser());
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
            'profile' => 'master_copy',
            'priority' => 2,
            'language' => 'en',
        ];
        $requestParameters = $this->getCustomParameterFromRequest($request, $requestParameters, $customParameters);

        $requestParameters = $this->getPeopleFromRoles($request, $requestParameters);

        $apiService = $this->get('pumukit_external_api.api_service');

        return $apiService->addMediaPackage($requestParameters, $this->getUser());
    }

    /**
     * @param Request $request
     *
     * @return array|Response
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
     * @param mixed  $mediaPackage
     * @param string $flavor
     * @param mixed  $body
     *
     * @return array|Response
     */
    private function validatePostData($mediaPackage, $flavor, $body)
    {
        if (!$mediaPackage) {
            return new Response("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$flavor) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$body) {
            return new Response('No attachment file', Response::HTTP_BAD_REQUEST);
        }

        return [
            'mediaPackage' => $mediaPackage,
            'flavor' => $flavor,
            'body' => $body,
        ];
    }
}
