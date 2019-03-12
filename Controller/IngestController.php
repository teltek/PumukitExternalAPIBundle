<?php

namespace Pumukit\ExternalAPIBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Route("/api")
 * @Security("is_granted('ROLE_ACCESS_INGEST_API')")
 */
class IngestController extends Controller
{
    private $dm = null;

    /**
     * @Route("/createMediaPackage", methods="POST")
     */
    public function indexAction(Request $request)
    {
        $xml = new \SimpleXMLElement('<mediapackage><media/><metadata/><attachments/><publications/></mediapackage>');
        $dm = $this->get('doctrine_mongodb')->getManager();
        $factoryService = $this->get('pumukitschema.factory');
        $series = $factoryService->createSeries($this->getUser());
        $mmobj = $factoryService->createMultimediaObject($series, true, $this->getUser());
        $xml->addAttribute('id', $mmobj->getId(), null);
        $xml->addAttribute('start', $mmobj->getPublicDate()->setTimezone(new \DateTimeZone('Z'))->format('Y-m-d\TH:i:s\Z'), null);

        return new Response($xml->asXML(), 200, array('Content-Type' => 'text/xml'));
    }
}
