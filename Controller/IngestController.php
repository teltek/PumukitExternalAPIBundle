<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Pumukit\SchemaBundle\Document\Material;

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
        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $dm = $this->get('doctrine_mongodb')->getManager();
        $factoryService = $this->get('pumukitschema.factory');
        $series = $factoryService->createSeries($this->getUser());
        $multimediaObject = $factoryService->createMultimediaObject($series, true, $this->getUser());
        $xml->addAttribute('id', $multimediaObject->getId(), null);
        $xml->addAttribute('start', $multimediaObject->getPublicDate()->setTimezone(new \DateTimeZone('Z'))->format('Y-m-d\TH:i:s\Z'), null);

        return new Response($xml->asXML(), 200, array('Content-Type' => 'text/xml'));
    }

    /**
     * @Route("/addAttachment", methods="POST")
     */
    public function addAttachmentAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if(!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }
        $flavor = $request->request->get('flavor');
        if(!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        }

        $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        if(!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string)$mediapackage['id'].'" cannot be found on the database', 404);
        }

        $materialMetadata = array(
            'mime_type' => $flavor,
        );
        $materialService = $this->get('pumukitschema.material');
        if(!$request->files->get('data')){
            return new Response('No attachment file', 400);
        }
        $multimediaObject = $materialService->addMaterialFile($multimediaObject, $request->files->get('data'), $materialMetadata);
        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $xml->addAttribute('id', $multimediaObject->getId(), null);
        foreach($multimediaObject->getMaterials() as $material){
            $attachment = $xml->attachments->addChild('attachment');
            $attachment->addAttribute('id', $material->getId());
            $attachment->addChild('mimetype', $material->getMimeType());
            $tags = $attachment->addChild('tags');
            foreach($material->getTags() as $tag){
                $tags->addChild('tag', $tag);
            }
            $attachment->addChild('url','');
            $attachment->addChild('size', '');
        }
        return new Response($xml->asXML(), 200, array('Content-Type' => 'text/xml'));
    }

    /**
     * @Route("/addTrack", methods="POST")
     */
    public function addTrackAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if(!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }
        $flavor = $request->request->get('flavor');
        if(!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        }

        $mediapackage = simplexml_load_string($mediapackage, 'SimpleXMLElement', LIBXML_NOCDATA);
        $dm = $this->get('doctrine_mongodb')->getManager();
        $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        if(!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string)$mediapackage['id'].'" cannot be found on the database', 404);
        }

        $jobService = $this->get('pumukitencoder.job');
        if (!$request->files->has('data')) {
            return new Response("No track file uploaded", 400);
        }

        $profile = $request->get('profile', 'master_copy');
        $priority = $request->get('priority', 2);
        $language = $request->get('language', 'en');
        $description = $request->get('description', '');
        // Use master_copy by default, maybe later add an optional parameter to endpoint to add tracks
        try {
            $multimediaObject = $jobService->createTrackFromLocalHardDrive($multimediaObject, $request->files->get('data'), $profile, $priority, $language, $description);
        } catch (\Exception $e) {
            return new Response('Upload failed. The file is not a valid video or audio file.', 500);
        }

        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $xml->addAttribute('id', $multimediaObject->getId(), null);
        return new Response($xml->asXml(), 200);
    }

    /**
     * @Route("/addCatalog", methods="POST")
     */
    public function addCatalogAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if(!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }

        $flavor = $request->request->get('flavor');
        if(!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        }

        if (!$request->files->has('data')) {
            return new Response("No catalog file uploaded", 400);
        }

        return new Response('OK', 200);
    }

    /**
     * @Route("/addDCCatalog", methods="POST")
     */
    public function addDCCatalogAction(Request $request)
    {
        $mediapackage = $request->request->get('mediaPackage');
        if(!$mediapackage) {
            return new Response("No 'mediaPackage' parameter", 400);
        }

        $flavor = $request->request->get('flavor');
        if(!$flavor) {
            return new Response("No 'flavor' parameter", 400);
        } else if(strpos($flavor, 'dublincore/') !== 0){
            return new Response("Only 'dublincore' catalogs 'flavor' parameter", 400);
        }

        if (!$request->files->has('data')) {
            return new Response("No catalog file uploaded", 400);
        }

        $catalog = $request->files->get('data');
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
        if(!$multimediaObject) {
            return new Response('The multimedia object with "id" "'.(string)$mediapackage['id'].'" cannot be found on the database', 404);
        }

        $namespacesMetadata = $catalog->getNamespaces(true);
        $catalogDcterms = $catalog->children($namespacesMetadata['dcterms']);
        if(strpos($flavor, 'dublincore/series') === 0){
            $series = $dm->getRepository('PumukitSchemaBundle:Series')->findOneBy(['_id' => (string) $catalogDcterms->identifier]);
            if(!$series){
                $factory = $this->get('pumukitschema.factory');
                $series = $factory->createSeries($this->getUser());
            }
            $multimediaObject->setSeries($series);
            $dm->persist($multimediaObject);
            $dm->flush();
        } else if(strpos($flavor, 'dublincore/episode') === 0){
            $newTitle = (string) $catalogDcterms->title;
            $multimediaObject->setTitle($newTitle);
            $dm->persist($multimediaObject);
            $dm->flush();
        }

        return new Response('OK', 200);
    }
}
