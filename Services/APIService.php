<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\CoreBundle\Services\ImportMappingDataService;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\MaterialService;
use Pumukit\SchemaBundle\Services\MultimediaObjectEventDispatcherService;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class APIService extends APICommonService
{
    public const PUMUKIT_EPISODE = 'pumukit/episode';

    private $materialService;
    private $jobCreator;
    private $personService;
    private $importMappingDataService;

    public function __construct(
        DocumentManager $documentManager,
        FactoryService $factoryService,
        MaterialService $materialService,
        JobCreator $jobCreator,
        PersonService $personService,
        ImportMappingDataService $importMappingDataService,
        MultimediaObjectEventDispatcherService $multimediaObjectEventDispatcherService,
        array $pumukitLocales
    ) {
        parent::__construct($documentManager, $factoryService, $multimediaObjectEventDispatcherService, $pumukitLocales);
        $this->documentManager = $documentManager;
        $this->factoryService = $factoryService;
        $this->materialService = $materialService;
        $this->jobCreator = $jobCreator;
        $this->personService = $personService;
        $this->importMappingDataService = $importMappingDataService;
    }

    public function createMediaPackage(array $requestParameters, ?User $user = null)
    {
        if ($seriesId = $requestParameters['series']) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                throw new \Exception('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $this->createSeriesForMediaPackage($user, $requestParameters);
        }

        $multimediaObject = $this->factoryService->createMultimediaObject($series, true, $user);

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    public function addAttachment(array $requestParameters)
    {
        [$mediaPackage, $flavor, $body, $language] = array_values($requestParameters);
        $overriding = $requestParameters['overriding'] ?? null;

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        $materialMetadata = [
            'mime_type' => $flavor,
            'language' => $language,
        ];

        $multimediaObject = $this->processMaterialFile($multimediaObject, $body, $materialMetadata, $overriding);

        $this->multimediaObjectDispatchUpdate($multimediaObject);

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    public function addTrack(array $requestParameters)
    {
        [$mediaPackage, $flavor, $body, $profile, $priority, $language, $description] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        $multimediaObject = $this->jobCreator->fromUploadedFile($multimediaObject, $body, $this->jobOptions($profile, $priority, $language, $description));

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    public function addCatalog(array $requestParameters)
    {
        [$mediaPackage, $flavor, $body] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        if (self::PUMUKIT_EPISODE === $flavor) {
            $content = file_get_contents($body->getPathname());
            if (in_array($body->getMimeType(), ['application/xml', 'text/xml'])) {
                $body = $this->loadXml($content, 'The catalog is not a valid XML document');
            } else {
                $body = json_decode($content, true);
                if (JSON_ERROR_NONE !== json_last_error()) {
                    throw new \Exception(json_last_error_msg(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            $this->importMappingDataService->insertMappingData($multimediaObject, $body);
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    public function addDCCatalog(array $requestParameters, ?User $user = null)
    {
        [$mediaPackage, $flavor, $body] = array_values($requestParameters);

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediaPackage);

        if (0 !== strpos($flavor, 'dublincore/')) {
            throw new \Exception("Only 'dublincore' catalogs 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        $body = $this->loadXml(file_get_contents($body instanceof UploadedFile ? $body->getPathname() : $body), 'The catalog is not a valid XML document');

        $namespacesMetadata = $body->getNamespaces(true);
        $bodyDcterms = $body->children($namespacesMetadata['dcterms']);
        if (str_starts_with($flavor, 'dublincore/series')) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => (string) $bodyDcterms->identifier]);
            if (!$series) {
                $series = $this->createSeriesForMediaPackage($user, $requestParameters);
            }
            $multimediaObject->setSeries($series);
            $this->documentManager->persist($multimediaObject);
            $this->documentManager->flush();
        } elseif (str_starts_with($flavor, 'dublincore/episode')) {
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
            } else {
                $multimediaObject->setRecordDate(new \DateTime());
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

    public function addMediaPackage(array $requestParameters, ?User $user = null)
    {
        [$flavor, $body, $seriesId, $accessRights, $title, $description, $profile, $priority, $language, $roles] = array_values($requestParameters);

        if ($seriesId) {
            $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                throw new \Exception('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $this->createSeriesForMediaPackage($user, $requestParameters);
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
            foreach (array_keys($multimediaObject->getI18nTitle()) as $locale) {
                $multimediaObject->setTitle($title, $locale);
            }
        }

        if ($description) {
            foreach (array_keys($multimediaObject->getI18nDescription()) as $locale) {
                $multimediaObject->setDescription($description, $locale);
            }
        }

        if ($flavor && $body) {
            if (!is_array($body)) {
                $body = [$body];
            }
            foreach ($body as $track) {
                $multimediaObject = $this->jobCreator->fromUploadedFile($multimediaObject, $track, $this->jobOptions($profile, $priority, $language, $description));
            }
        }

        $this->documentManager->persist($multimediaObject);
        $this->documentManager->flush();

        $mediaPackage = $this->generateXML($multimediaObject);

        return $mediaPackage->asXML();
    }

    protected function processMaterialFile(MultimediaObject $multimediaObject, $body, $materialMetadata, ?string $overriding = null): MultimediaObject
    {
        if (!$overriding) {
            return $this->materialService->addMaterialFile($multimediaObject, $body, $materialMetadata);
        }

        $material = $multimediaObject->getMaterialById($overriding);
        if (!$material) {
            throw new \Exception(sprintf('Material with id "%s" not found', $overriding), Response::HTTP_NOT_FOUND);
        }

        return $this->materialService->updateMaterialFile($multimediaObject, $body, $material, $materialMetadata);
    }

    private function jobOptions(string $profile, int|string $priority, string $language, string $description): JobOptions
    {
        return new JobOptions($profile, (int) $priority, $language, '' === $description ? [] : $this->processSeriesTitle($description));
    }
}
