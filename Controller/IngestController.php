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
}
