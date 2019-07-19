<?php

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\EncoderBundle\Services\JobService;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\MaterialService;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class APIService
{
    /**
     * @var DocumentManager
     */
    private $documentManager;

    /**
     * @var FactoryService
     */
    private $factoryService;

    /**
     * @var MaterialService
     */
    private $materialService;

    /**
     * @var JobService
     */
    private $jobService;

    /**
     * @var PersonService
     */
    private $personService;

    private $mappingPumukitData = [
        'status' => 'setStatus',
        'record_date' => 'setRecordDate',
        'public_date' => 'setPublicDate',
        'title' => 'setTitle',
        'subtitle' => 'setSubtitle',
        'description' => 'setDescription',
        'line2' => 'setLine2',
        'copyright' => 'setCopyright',
        'license' => 'setLicense',
        'keywords' => 'setKeywords',
        'properties' => 'setProperty',
        'numview' => 'setNumView',
    ];

    const pumukitEpisode = 'pumukit/episode';

    public function __construct(DocumentManager $documentManager, FactoryService $factoryService, MaterialService $materialService, JobService $jobService, PersonService $personService)
    {
        $this->documentManager = $documentManager;
        $this->factoryService = $factoryService;
        $this->materialService = $materialService;
        $this->jobService = $jobService;
        $this->personService = $personService;
    }

    /**
     * @param $mediaPackage
     * @param $flavour
     * @param $body
     *
     * @return bool|Response
     */
    public function validatePostData($mediaPackage, $flavour, $body)
    {
        if (!$mediaPackage) {
            return new Response("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$flavour) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$body) {
            return new Response('No attachment file', Response::HTTP_BAD_REQUEST);
        }

        return true;
    }

    /**
     * @param MultimediaObject $multimediaObject
     *
     * @return \SimpleXMLElement
     */
    private function generateXML(MultimediaObject $multimediaObject)
    {
        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $xml->addAttribute('id', $multimediaObject->getId(), null);
        $xml->addAttribute('start', $multimediaObject->getPublicDate()->setTimezone(new \DateTimeZone('Z'))->format('Y-m-d\TH:i:s\Z'), null);

        foreach ($multimediaObject->getMaterials() as $material) {
            $attachment = $xml->attachments->addChild('attachment');
            $attachment->addAttribute('id', $material->getId());
            $attachment->addChild('mimetype', $material->getMimeType());
            $tags = $attachment->addChild('tags');
            foreach ($material->getTags() as $tag) {
                $tags->addChild('tag', $tag);
            }
            $attachment->addChild('url', '');
            $attachment->addChild('size', '');
        }

        return $xml;
    }

    /**
     * @param Request $request
     * @param User    $user
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function createMediaPackage(Request $request, User $user)
    {
        if ($seriesId = $request->request->get('series')) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                return new Response('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $this->factoryService->createSeries($user);
        }

        $multimediaObject = $this->factoryService->createMultimediaObject($series, true, $user);

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK, array('Content-Type' => 'text/xml'));
    }

    /**
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addAttachment(Request $request)
    {
        [$mediaPackage, $flavour, $body] = $this->getRequestParameters($request);

        $this->validatePostData($mediaPackage, $flavour, $body);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        $materialMetadata = ['mime_type' => $flavour];

        $multimediaObject = $this->materialService->addMaterialFile($multimediaObject, $request->files->get('BODY'), $materialMetadata);

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK, array('Content-Type' => 'text/xml'));
    }

    /**
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addTrack(Request $request)
    {
        [$mediaPackage, $flavour, $body] = $this->getRequestParameters($request);

        $this->validatePostData($mediaPackage, $flavour, $body);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        $profile = $request->get('profile', 'master_copy');
        $priority = $request->get('priority', 2);
        $language = $request->get('language', 'en');
        $description = $request->get('description', '');

        // Use master_copy by default, maybe later add an optional parameter to endpoint to add tracks
        try {
            $multimediaObject = $this->jobService->createTrackFromLocalHardDrive($multimediaObject, $body, $profile, $priority, $language, $description);
        } catch (\Exception $e) {
            return new Response('Upload failed. The file is not a valid video or audio file.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXml(), Response::HTTP_OK, array('Content-Type' => 'text/xml'));
    }

    /**
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addCatalog(Request $request)
    {
        [$mediaPackage, $flavour, $body] = $this->getRequestParameters($request);

        $this->validatePostData($mediaPackage, $flavour, $body);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        if (self::pumukitEpisode === $flavour) {
            if (in_array($body->getMimeType(), array('application/xml', 'text/xml'))) {
                try {
                    $body = simplexml_load_file($body, 'SimpleXMLElement', LIBXML_NOCDATA);
                } catch (\Exception $e) {
                    return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            } else {
                $body = json_decode(file_get_contents($body), true);
                if (JSON_ERROR_NONE !== json_last_error()) {
                    return new Response(json_last_error_msg(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            $this->processPumukitEpisode($multimediaObject, $body);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param User    $user
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addDCCatalog(Request $request, User $user)
    {
        [$mediaPackage, $flavour, $body] = $this->getRequestParameters($request);

        if (!$flavour) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        } elseif (0 !== strpos($flavour, 'dublincore/')) {
            return new Response("Only 'dublincore' catalogs 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$body) {
            return new Response('No catalog file uploaded', Response::HTTP_BAD_REQUEST);
        }

        try {
            $body = simplexml_load_file($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        $namespacesMetadata = $body->getNamespaces(true);
        $bodyDcterms = $body->children($namespacesMetadata['dcterms']);
        if (0 === strpos($flavour, 'dublincore/series')) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => (string) $bodyDcterms->identifier]);
            if (!$series) {
                $series = $this->factoryService->createSeries($user);
            }
            $multimediaObject->setSeries($series);
            $this->documentManager->persist($multimediaObject);
            $this->documentManager->flush();
        } elseif (0 === strpos($flavour, 'dublincore/episode')) {
            if ($newTitle = (string) $bodyDcterms->title) {
                foreach ($multimediaObject->getI18nTitle() as $language => $title) {
                    $multimediaObject->setTitle($newTitle, $language);
                }
            }
            if ($newDescription = (string) $bodyDcterms->description) {
                foreach ($multimediaObject->getI18nDescription() as $language => $description) {
                    $multimediaObject->setDescription($newDescription, $language);
                }
            }
            if ($newCopyright = (string) $bodyDcterms->accessRights) {
                $multimediaObject->setCopyright($newCopyright);
            }
            if ($newLicense = (string) $bodyDcterms->license) {
                $multimediaObject->setLicense($newLicense);
            }
            if ($newRecordDate = (string) $bodyDcterms->created) {
                $multimediaObject->setRecordDate(new \DateTime($newRecordDate));
            }

            foreach ($this->documentManager->getRepository(Role::class)->findAll() as $role) {
                $roleCod = $role->getCod();
                foreach ($bodyDcterms->$roleCod as $personName) {
                    $newPerson = $this->documentManager->getRepository(Person::class)->findOneBy(['name' => (string) $personName]);
                    if (!$newPerson) {
                        $newPerson = new Person();
                        $newPerson->setName((string) $personName);
                    }
                    $multimediaObject = $this->personService->createRelationPerson($newPerson, $role, $multimediaObject);
                }
            }

            $this->documentManager->persist($multimediaObject);
            $this->documentManager->flush();
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK, array('Content-Type' => 'text/xml'));
    }

    /**
     * @param Request $request
     * @param User    $user
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function addMediaPackage(Request $request, User $user)
    {
        $flavour = $request->request->get('flavor');
        if (!$flavour) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No track file uploaded', Response::HTTP_BAD_REQUEST);
        }

        if ($seriesId = $request->request->get('series')) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                return new Response('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $this->factoryService->createSeries($user);
        }
        $multimediaObject = $this->factoryService->createMultimediaObject($series, true, $user);

        //Add catalogDC logic (kinda)
        if ($copyright = $request->request->get('accessRights')) {
            $multimediaObject->setCopyright($copyright);
        }

        foreach ($this->documentManager->getRepository(Role::class)->findAll() as $role) {
            $roleCod = $role->getCod();
            $peopleNames = $request->request->get($roleCod);
            if (!$peopleNames) {
                continue;
            }
            if (!is_array($peopleNames)) {
                $peopleNames = array($peopleNames);
            }
            foreach ($peopleNames as $personName) {
                $newPerson = $this->documentManager->getRepository(Person::class)->findOneBy(['name' => (string) $personName]);
                if (!$newPerson) {
                    $newPerson = new Person();
                    $newPerson->setName((string) $personName);
                }
                $multimediaObject = $this->personService->createRelationPerson($newPerson, $role, $multimediaObject);
            }
        }

        if ($newTitle = $request->request->get('title')) {
            foreach ($multimediaObject->getI18nTitle() as $language => $title) {
                $multimediaObject->setTitle($newTitle, $language);
            }
        }

        if ($newDescription = $request->request->get('description')) {
            foreach ($multimediaObject->getI18nDescription() as $language => $description) {
                $multimediaObject->setDescription($newDescription, $language);
            }
        }

        // Add track
        $flavours = $request->request->get('flavor');
        $tracks = $request->files->get('BODY');
        if ($flavours && $tracks) {
            // Use master_copy by default, maybe later add an optional parameter to endpoint to add tracks
            $profile = $request->get('profile', 'master_copy');
            $priority = $request->get('priority', 2);
            $language = $request->get('language', 'en');
            $description = array('');
            if (!is_array($tracks)) {
                $tracks = array($tracks);
            }
            foreach ($tracks as $track) {
                try {
                    $multimediaObject = $this->jobService->createTrackFromLocalHardDrive($multimediaObject, $track, $profile, $priority, $language, $description);
                } catch (\Exception $e) {
                    return new Response('Upload failed. The file is not a valid video or audio file.', Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
        }

        $this->documentManager->persist($multimediaObject);
        $this->documentManager->flush();

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private function getRequestParameters(Request $request)
    {
        $body = $request->files->has('body') ? $request->files->get('body') : false;

        return [
            $request->request->get('mediaPackage'),
            $request->request->get('flavour'),
            $body,
        ];
    }

    /**
     * @param $mediaPackage
     *
     * @return object|null
     *
     * @throws \Exception
     */
    private function getMultimediaObjectFromMediaPackageXML($mediaPackage)
    {
        try {
            $mediaPackage = simplexml_load_string($mediaPackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            '_id' => (string) $mediaPackage['id'],
        ]);

        if (!$multimediaObject) {
            $msg = sprintf('The multimedia object with "id" "%s" cannot be found on the database', (string) $mediaPackage['id']);
            throw new \Exception($msg, Response::HTTP_NOT_FOUND);
        }

        return $multimediaObject;
    }

    /**
     * @param MultimediaObject $multimediaObject
     * @param array            $body
     */
    private function processPumukitEpisode(MultimediaObject $multimediaObject, array $body)
    {
        foreach ($body as $key => $value) {
            if (array_key_exists($key, $this->mappingPumukitData)) {
                $method = $this->mappingPumukitData[$key];

                if (!is_array($value)) {
                    $multimediaObject->$method($value);
                } else {
                    foreach ($body[$key] as $lang => $data) {
                        $multimediaObject->$method($data, $lang);
                    }
                }
            }
        }

        $this->documentManager->flush();
    }
}
