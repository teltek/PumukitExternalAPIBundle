<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/ingest")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class IngestController extends Controller
{
    /**
     * @Route("/createMediaPackage", methods="POST")
     */
    public function createMediaPackageAction(Request $request)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();
        $factoryService = $this->get('pumukitschema.factory');
        if ($seriesId = $request->request->get('series')) {
            $series = $dm->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                return new Response('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $factoryService->createSeries($this->getUser());
        }
        $multimediaObject = $factoryService->createMultimediaObject($series, true, $this->getUser());
        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

    /**
     * @Route("/addAttachment", methods="POST")
     */
    public function addAttachmentAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            $multimediaObjectId = $request->request->get('id');

            return new Response("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }
        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No attachment file', Response::HTTP_BAD_REQUEST);
        }

        try {
            $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediapackage['id']]);
        if (!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string) $mediapackage['id'].'" cannot be found on the database', Response::HTTP_NOT_FOUND);
        }

        $materialMetadata = [
            'mime_type' => $flavor,
        ];
        $materialService = $this->get('pumukitschema.material');
        $multimediaObject = $materialService->addMaterialFile($multimediaObject, $request->files->get('BODY'), $materialMetadata);
        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

    /**
     * @Route("/addTrack", methods="POST")
     */
    public function addTrackAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }
        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No track file uploaded', Response::HTTP_BAD_REQUEST);
        }

        try {
            $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediapackage['id']]);
        if (!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string) $mediapackage['id'].'" cannot be found on the database', Response::HTTP_NOT_FOUND);
        }

        $profile = $request->get('profile', 'master_copy');
        $priority = $request->get('priority', 2);
        $language = $request->get('language', 'en');
        $description = $request->get('description', '');
        $jobService = $this->get('pumukitencoder.job');
        // Use master_copy by default, maybe later add an optional parameter to endpoint to add tracks
        try {
            $multimediaObject = $jobService->createTrackFromLocalHardDrive($multimediaObject, $request->files->get('BODY'), $profile, $priority, $language, $description);
        } catch (\Exception $e) {
            return new Response('Upload failed. The file is not a valid video or audio file.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXml(), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

    /**
     * @Route("/addCatalog", methods="POST")
     */
    public function addCatalogAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }

        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No catalog file uploaded', Response::HTTP_BAD_REQUEST);
        }
        $catalog = $request->files->get('BODY');

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediapackage);

        if ('pumukit/episode' === $flavor) {

            var_dump($catalog->getMimeType());

            if (in_array($catalog->getMimeType(), array('application/xml', 'text/xml'))) {

                try {
                    $catalog = simplexml_load_file($catalog, 'SimpleXMLElement', LIBXML_NOCDATA);
                } catch (\Exception $e) {
                    return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }

            } else {

                $catalog = json_decode(file_get_contents($catalog), true);
                if (JSON_ERROR_NONE !== json_last_error()) {
                    return new Response(json_last_error_msg(), Response::HTTP_INTERNAL_SERVER_ERROR);
                }

            }

            $this->processPumukitEpisode($catalog, $mediapackage);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * @Route("/addDCCatalog", methods="POST")
     */
    public function addDCCatalogAction(Request $request)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", Response::HTTP_BAD_REQUEST);
        }

        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }
        if (0 !== strpos($flavor, 'dublincore/')) {
            return new Response("Only 'dublincore' catalogs 'flavor' parameter", Response::HTTP_BAD_REQUEST);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No catalog file uploaded', Response::HTTP_BAD_REQUEST);
        }

        $catalog = $request->files->get('BODY');
        //libxml_use_internal_errors(true);
        try {
            $catalog = simplexml_load_file($catalog, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $multimediaObject = $this->getMultimediaObjectFromMediapackageXML($mediapackage);

        $namespacesMetadata = $catalog->getNamespaces(true);
        $catalogDcterms = $catalog->children($namespacesMetadata['dcterms']);
        if (0 === strpos($flavor, 'dublincore/series')) {
            $series = $dm->getRepository(Series::class)->findOneBy(['_id' => (string) $catalogDcterms->identifier]);
            if (!$series) {
                $factory = $this->get('pumukitschema.factory');
                $series = $factory->createSeries($this->getUser());
            }
            $multimediaObject->setSeries($series);
            $dm->persist($multimediaObject);
            $dm->flush();
        } elseif (0 === strpos($flavor, 'dublincore/episode')) {
            if ($newTitle = (string) $catalogDcterms->title) {
                foreach ($multimediaObject->getI18nTitle() as $language => $title) {
                    $multimediaObject->setTitle($newTitle, $language);
                }
            }
            if ($newDescription = (string) $catalogDcterms->description) {
                foreach ($multimediaObject->getI18nDescription() as $language => $description) {
                    $multimediaObject->setDescription($newDescription, $language);
                }
            }
            if ($newCopyright = (string) $catalogDcterms->accessRights) {
                $multimediaObject->setCopyright($newCopyright);
            }
            if ($newLicense = (string) $catalogDcterms->license) {
                $multimediaObject->setLicense($newLicense);
            }
            if ($newRecordDate = (string) $catalogDcterms->created) {
                $multimediaObject->setRecordDate(new \DateTime($newRecordDate));
            }
            $personService = $this->get('pumukitschema.person');
            foreach ($dm->getRepository(Role::class)->findAll() as $role) {
                $roleCod = $role->getCod();

                foreach ($catalogDcterms->{$roleCod} as $personName) {
                    $newPerson = $dm->getRepository(Person::class)->findOneBy(['name' => (string) $personName]);
                    if (!$newPerson) {
                        $newPerson = new Person();
                        $newPerson->setName((string) $personName);
                    }
                    $multimediaObject = $personService->createRelationPerson($newPerson, $role, $multimediaObject);
                }
            }

            $dm->persist($multimediaObject);
            $dm->flush();
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

    /**
     * @Route("/addMediaPackage", methods="POST")
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

        //createMediaPackage logic
        $dm = $this->get('doctrine_mongodb')->getManager();
        $factoryService = $this->get('pumukitschema.factory');
        if ($seriesId = $request->request->get('series')) {
            $series = $dm->getRepository(Series::class)->findOneBy(['_id' => $seriesId]);
            if (!$series) {
                return new Response('The series with "id" "'.$seriesId.'" cannot be found on the database', Response::HTTP_NOT_FOUND);
            }
        } else {
            $series = $factoryService->createSeries($this->getUser());
        }
        $multimediaObject = $factoryService->createMultimediaObject($series, true, $this->getUser());

        //Add catalogDC logic (kinda)
        if ($copyright = $request->request->get('accessRights')) {
            $multimediaObject->setCopyright($copyright);
        }

        $personService = $this->get('pumukitschema.person');
        foreach ($dm->getRepository(Role::class)->findAll() as $role) {
            $roleCod = $role->getCod();
            $peopleNames = $request->request->get($roleCod);
            if (!$peopleNames) {
                continue;
            }
            if (!is_array($peopleNames)) {
                $peopleNames = [$peopleNames];
            }
            foreach ($peopleNames as $personName) {
                $newPerson = $dm->getRepository(Person::class)->findOneBy(['name' => (string) $personName]);
                if (!$newPerson) {
                    $newPerson = new Person();
                    $newPerson->setName((string) $personName);
                }
                $multimediaObject = $personService->createRelationPerson($newPerson, $role, $multimediaObject);
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
        $flavors = $request->request->get('flavor');
        $tracks = $request->files->get('BODY');
        if ($flavors && $tracks) {
            // Use master_copy by default, maybe later add an optional parameter to endpoint to add tracks
            $profile = $request->get('profile', 'master_copy');
            $priority = $request->get('priority', 2);
            $language = $request->get('language', 'en');
            $description = '';
            $jobService = $this->get('pumukitencoder.job');
            if (!is_array($tracks)) {
                $tracks = [$tracks];
            }
            foreach ($tracks as $track) {
                try {
                    $multimediaObject = $jobService->createTrackFromLocalHardDrive($multimediaObject, $track, $profile, $priority, $language, $description);
                } catch (\Exception $e) {
                    return new Response('Upload failed. The file is not a valid video or audio file.', Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
        }

        $dm->persist($multimediaObject);
        $dm->flush();
        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), Response::HTTP_OK);
    }


    protected function generateXML(MultimediaObject $multimediaObject)
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

    private function getMultimediaObjectFromMediapackageXML($mediaPackage)
    {
        try {
            $mediapackage = simplexml_load_string($mediaPackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediapackage['id']]);
        if (!$multimediaObject) {
            $msg = sprintf('The multimedia object with "id" "%s" cannot be found on the database', (string) $mediapackage['id']);
            throw $this->createNotFoundException($msg);
        }

    }

    private function processPumukitEpisode($catalog, $mediapackage)
    {
        //TODO
    }
}
