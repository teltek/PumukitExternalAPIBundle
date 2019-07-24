<?php

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\EncoderBundle\Services\JobService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\MaterialService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class APIService.
 */
class APIService
{
    const PUMUKIT_EPISODE = 'pumukit/episode';
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

    /**
     * @var TagService
     */
    private $tagService;

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

    private $mappingDataToDateTime = [
        'record_date' => 'setRecordDate',
        'public_date' => 'setPublicDate',
    ];

    private $mappingPumukitDataExceptions = [
        'tags',
        'role',
        'people',
    ];

    /**
     * APIService constructor.
     *
     * @param DocumentManager $documentManager
     * @param FactoryService  $factoryService
     * @param MaterialService $materialService
     * @param JobService      $jobService
     * @param PersonService   $personService
     * @param TagService      $tagService
     */
    public function __construct(DocumentManager $documentManager, FactoryService $factoryService, MaterialService $materialService, JobService $jobService, PersonService $personService, TagService $tagService)
    {
        $this->documentManager = $documentManager;
        $this->factoryService = $factoryService;
        $this->materialService = $materialService;
        $this->jobService = $jobService;
        $this->personService = $personService;
        $this->tagService = $tagService;
    }

    /**
     * @param array     $requestParameters
     * @param null|User $user
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function createMediaPackage(array $requestParameters, User $user = null)
    {
        if ($seriesId = $requestParameters['series']) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                throw new \Exception('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $this->factoryService->createSeries($user);
        }

        $multimediaObject = $this->factoryService->createMultimediaObject($series, true, $user);

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    /**
     * @param array $requestParameters
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function addAttachment(array $requestParameters)
    {
        [$mediaPackage, $flavor, $body] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        $materialMetadata = ['mime_type' => $flavor];

        $multimediaObject = $this->materialService->addMaterialFile($multimediaObject, $body, $materialMetadata);

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    /**
     * @param array $requestParameters
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function addTrack(array $requestParameters)
    {
        [$mediaPackage, $flavor, $body, $profile, $priority, $language, $description] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        // Use master_copy by default, maybe later add an optional parameter to endpoint to add tracks
        $multimediaObject = $this->jobService->createTrackFromLocalHardDrive($multimediaObject, $body, $profile, $priority, $language, $description);

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    /**
     * @param array $requestParameters
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function addCatalog(array $requestParameters)
    {
        [$mediaPackage, $flavor, $body] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        if (self::PUMUKIT_EPISODE === $flavor) {
            if (in_array($body->getMimeType(), ['application/xml', 'text/xml'])) {
                try {
                    $body = simplexml_load_file($body, 'SimpleXMLElement', LIBXML_NOCDATA);
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            } else {
                $body = json_decode(file_get_contents($body), true);
                if (JSON_ERROR_NONE !== json_last_error()) {
                    throw new \Exception(json_last_error_msg(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            $this->processPumukitEpisode($multimediaObject, $body);
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    /**
     * @param array     $requestParameters
     * @param null|User $user
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function addDCCatalog(array $requestParameters, User $user = null)
    {
        [$mediaPackage, $flavor, $body] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        if (0 !== strpos($flavor, 'dublincore/')) {
            throw new \Exception("Only 'dublincore' catalogs 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        $body = simplexml_load_file($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        $namespacesMetadata = $body->getNamespaces(true);
        $bodyDcterms = $body->children($namespacesMetadata['dcterms']);
        if (0 === strpos($flavor, 'dublincore/series')) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => (string) $bodyDcterms->identifier]);
            if (!$series) {
                $series = $this->factoryService->createSeries($user);
            }
            $multimediaObject->setSeries($series);
            $this->documentManager->persist($multimediaObject);
            $this->documentManager->flush();
        } elseif (0 === strpos($flavor, 'dublincore/episode')) {
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
                foreach ($bodyDcterms->{$roleCod} as $personName) {
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

        return $mediaPackage->asXML();
    }

    /**
     * @param array     $requestParameters
     * @param null|User $user
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function addMediaPackage(array $requestParameters, User $user = null)
    {
        [$flavor, $body, $seriesId, $accessRights, $title, $description, $profile, $priority, $language, $roles] = array_values($requestParameters);

        if ($seriesId) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                throw new \Exception('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $this->factoryService->createSeries($user);
        }

        $multimediaObject = $this->factoryService->createMultimediaObject($series, true, $user);

        if ($accessRights) {
            $multimediaObject->setCopyright($accessRights);
        }

        foreach ($this->documentManager->getRepository(Role::class)->findAll() as $role) {
            if (!isset($roles[$role->getCod()])) {
                continue;
            }

            $peopleNames = $roles[$role->getCod()];
            if (!is_array($peopleNames)) {
                $peopleNames = [$peopleNames];
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

        if ($title) {
            foreach ($multimediaObject->getI18nTitle() as $language => $value) {
                $multimediaObject->setTitle($title, $language);
            }
        }

        if ($description) {
            foreach ($multimediaObject->getI18nDescription() as $language => $value) {
                $multimediaObject->setDescription($description, $language);
            }
        }

        if ($flavor && $body) {
            $description = [''];
            if (!is_array($body)) {
                $body = [$body];
            }
            foreach ($body as $track) {
                $multimediaObject = $this->jobService->createTrackFromLocalHardDrive($multimediaObject, $track, $profile, $priority, $language, $description);
            }
        }

        $this->documentManager->persist($multimediaObject);
        $this->documentManager->flush();

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    /**
     * @param MultimediaObject $multimediaObject
     *
     * @return \SimpleXMLElement
     */
    private function generateXML(MultimediaObject $multimediaObject)
    {
        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $xml->addAttribute('id', $multimediaObject->getId());
        $xml->addAttribute('start', $multimediaObject->getPublicDate()->setTimezone(new \DateTimeZone('Z'))->format('Y-m-d\TH:i:s\Z'));

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
     * @param string $mediaPackage
     *
     * @throws \Exception
     *
     * @return MultimediaObject
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
     *
     * @throws \Exception
     */
    private function processPumukitEpisode(MultimediaObject $multimediaObject, array $body)
    {
        foreach ($body as $key => $value) {
            if (array_key_exists($key, $this->mappingPumukitData)) {
                $method = $this->mappingPumukitData[$key];

                if (!is_array($value)) {
                    if(in_array($key, $this->mappingDataToDateTime)) {
                        $value = new \DateTime($value);
                    }
                    $multimediaObject->{$method}($value);
                } else {
                    foreach ($body[$key] as $lang => $data) {
                        $multimediaObject->{$method}($data, $lang);
                    }
                }
            } elseif (in_array($key, $this->mappingPumukitDataExceptions)) {
                $this->processPumukitDataExceptions($multimediaObject, $key, $value);
            }
        }

        $this->documentManager->flush();
    }

    /**
     * @param MultimediaObject $multimediaObject
     * @param string           $key
     * @param array            $value
     *
     * @throws \Exception
     */
    private function processPumukitDataExceptions(MultimediaObject $multimediaObject, $key, $value)
    {
        switch ($key) {
            case 'tags':
                $this->processPumukitTags($multimediaObject, $value);

                break;
            case 'role':
            case 'people':
                $this->processPumukitRole($multimediaObject, $value);

                break;
            default:
        }
    }

    /**
     * @param MultimediaObject $multimediaObject
     * @param array            $value
     *
     * @throws \Exception
     */
    private function processPumukitTags(MultimediaObject $multimediaObject, array $value)
    {
        foreach ($value as $tagCod) {
            $tag = $this->documentManager->getRepository(Tag::class)->findOneBy([
                'cod' => $tagCod,
            ]);

            if ($tag) {
                $this->tagService->addTagByCodToMultimediaObject($multimediaObject, $tag->getCod(), false);
            }
        }
    }

    /**
     * @param MultimediaObject $multimediaObject
     * @param array            $value
     */
    private function processPumukitRole(MultimediaObject $multimediaObject, array $value)
    {
        foreach ($value as $key => $personEmails) {
            $person = null;
            $role = $this->documentManager->getRepository(Role::class)->findOneBy([
                'cod' => $key,
            ]);

            foreach ($personEmails as $data) {
                if (is_array($data)) {
                    $person = $this->documentManager->getRepository(Person::class)->findOneBy(
                        [
                            'email' => $data['email'],
                        ]
                    );

                    if (!$person) {
                        $person = new Person();
                        $person->setEmail($data['email']);
                        $person->setName($data['name']);
                        $this->documentManager->persist($person);
                        $this->documentManager->flush();
                    }
                }

                if ($role && $person) {
                    $this->personService->createRelationPerson($person, $role, $multimediaObject);
                }
            }
        }
    }
}
