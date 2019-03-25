<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Pumukit\SchemaBundle\Document\Material;
use Pumukit\SchemaBundle\Document\MultimediaObject;

/**
 * @Route("/api/ingest")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class IngestController extends Controller
{
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

    /**
     * @Route("/createMediaPackage", methods="POST")
     */
    public function createMediaPackageAction(Request $request)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();
        $factoryService = $this->get('pumukitschema.factory');
        $series = $factoryService->createSeries($this->getUser());
        $multimediaObject = $factoryService->createMultimediaObject($series, true, $this->getUser());
        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), 200, array('Content-Type' => 'text/xml'));
    }

    /**
     * @Route("/addAttachment", methods="POST")
     */
    public function addAttachmentAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }
        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No attachment file', 400);
        }

        try {
            $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        if (!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string) $mediapackage['id'].'" cannot be found on the database', 404);
        }

        $materialMetadata = array(
            'mime_type' => $flavor,
        );
        $materialService = $this->get('pumukitschema.material');
        $multimediaObject = $materialService->addMaterialFile($multimediaObject, $request->files->get('BODY'), $materialMetadata);
        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), 200, array('Content-Type' => 'text/xml'));
    }

    /**
     * @Route("/addTrack", methods="POST")
     */
    public function addTrackAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }
        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No track file uploaded', 400);
        }

        try {
            $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }
        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        if (!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string) $mediapackage['id'].'" cannot be found on the database', 404);
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
            return new Response('Upload failed. The file is not a valid video or audio file.', 500);
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXml(), 200, array('Content-Type' => 'text/xml'));
    }

    /**
     * @Route("/addCatalog", methods="POST")
     */
    public function addCatalogAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }

        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No catalog file uploaded', 400);
        }

        return new Response('OK', 200);
    }

    /**
     * @Route("/addDCCatalog", methods="POST")
     */
    public function addDCCatalogAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if (!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }

        $flavor = $request->request->get('flavor');
        if (!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        } elseif (strpos($flavor, 'dublincore/') !== 0) {
            return new Response("Only 'dublincore' catalogs 'flavor' parameter", 400);
        }

        if (!$request->files->has('BODY')) {
            return new Response('No catalog file uploaded', 400);
        }

        $catalog = $request->files->get('BODY');
        //libxml_use_internal_errors(true);
        try {
            $catalog = simplexml_load_file($catalog, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }

        try {
            $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), 500);
        }
        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        if (!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string) $mediapackage['id'].'" cannot be found on the database', 404);
        }

        $namespacesMetadata = $catalog->getNamespaces(true);
        $catalogDcterms = $catalog->children($namespacesMetadata['dcterms']);
        if (strpos($flavor, 'dublincore/series') === 0) {
            $series = $dm->getRepository('PumukitSchemaBundle:Series')->findOneBy(['_id' => (string) $catalogDcterms->identifier]);
            if (!$series) {
                $factory = $this->get('pumukitschema.factory');
                $series = $factory->createSeries($this->getUser());
            }
            $multimediaObject->setSeries($series);
            $dm->persist($multimediaObject);
            $dm->flush();
        } elseif (strpos($flavor, 'dublincore/episode') === 0) {
            $newTitle = (string) $catalogDcterms->title;
            foreach($multimediaObject->getI18nTitle() as $language => $title){
                $multimediaObject->setTitle($newTitle, $language);
            }
            $dm->persist($multimediaObject);
            $dm->flush();
        }

        $mediaPackage = $this->generateXML($multimediaObject);

        return new Response($mediaPackage->asXML(), 200, array('Content-Type' => 'text/xml'));
    }
}
