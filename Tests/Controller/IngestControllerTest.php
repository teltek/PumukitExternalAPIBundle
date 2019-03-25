<?php

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;

class IngestControllerTest extends WebTestCase
{
    private $dm;

    public function setUp()
    {
        $options = array('environment' => 'test');
        static::bootKernel($options);
        $this->jobService = static::$kernel->getContainer()->get('pumukitencoder.job');
        $this->dm = static::$kernel->getContainer()
            ->get('doctrine_mongodb')->getManager();
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove(array());
    }

    public function tearDown()
    {
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove(array());
        $this->dm->close();
        $this->jobService = null;
        gc_collect_cycles();
        parent::tearDown();
    }

    protected function createAuthorizedClient()
    {
        $client = static::createClient();
        $container = static::$kernel->getContainer();

        $token = new UsernamePasswordToken('tester', null, 'api', ['ROLE_ACCESS_INGEST_API', 'ROLE_ACCESS_API', 'ROLE_USER']);
        $session = $container->get('session');
        $session->set('_security_pumukit', serialize($token));
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        return $client;
    }

    public function testCreateMediaPackage()
    {
        $client = static::createClient();

        $client->request('GET', '/api/ingest/createMediaPackage');
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
        $client->request('POST', '/api/ingest/createMediaPackage');
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $createdAt = new \DateTime();
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $mmobj = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertTrue($mmobj instanceof MultimediaObject);
        $this->assertEquals($createdAt, new \DateTime($mediapackage['start']));
        $this->assertNotEmpty($mediapackage->media);
        $this->assertNotEmpty($mediapackage->metadata);
        $this->assertNotEmpty($mediapackage->attachments);
        $this->assertNotEmpty($mediapackage->publications);
        $this->assertEmpty((string) $mediapackage->media);
        $this->assertEmpty((string) $mediapackage->metadata);
        $this->assertEmpty((string) $mediapackage->attachments);
        $this->assertEmpty((string) $mediapackage->publications);
    }

    public function testAddAttachment()
    {
        // Get valid mediapackage
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        // Fails if no post parameters
        $client->request('POST', '/api/ingest/addAttachment');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
        );
        // Still fails if no flavor set
        $client->request('POST', '/api/ingest/addAttachment', $postParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // We check success case and that attachment has been added
        // File generation
        $postParams['flavor'] = 'srt';
        $subtitleFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addAttachment', $postParams, array('BODY' => $subtitleFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $mmobj = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertTrue($mmobj instanceof MultimediaObject);
        $this->assertEquals(count($mmobj->getMaterials()), 1);
        $this->assertEquals($mmobj->getMaterials()[0]->getMimeType(), $postParams['flavor']);
        $this->assertEquals((string) $mediapackage->attachments[0]->attachment->mimetype, $postParams['flavor']);
        $this->assertNotEmpty($mediapackage->media);
        $this->assertNotEmpty($mediapackage->metadata);
        $this->assertNotEmpty($mediapackage->attachments);
        $this->assertNotEmpty($mediapackage->publications);

        // Request with invalid mediapackage
        $mediapackage['id'] = 'invalid-id';
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'attachment/srt',
        );
        $subtitleFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addAttachment', $postParams, array('BODY' => $subtitleFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    protected function generateSubtitleFile()
    {
        $localFile = 'subtitles.srt';
        $fp = fopen($localFile, 'wb');
        fwrite($fp, "1\n00:02:17,440 --> 00:02:20,375\n Senator, we're making\nour final approach into Coruscant.");
        $subtitleFile = new UploadedFile($localFile, $localFile);
        fclose($fp);

        return $subtitleFile;
    }

    public function testAddTrack()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addTrack');
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // Test with valid params (still should fail)
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'presentation/source',
        );
        $client->request('POST', '/api/ingest/addTrack', $postParams);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // Test sending bad media track file
        $badFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addTrack', $postParams, array('BODY' => $badFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        // Test sending correct media track file
        $trackFile = $this->generateTrackFile();
        $client->request('POST', '/api/ingest/addTrack', $postParams, array('BODY' => $trackFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $mmobj = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertTrue($mmobj instanceof MultimediaObject);
        $jobs = $this->jobService->getNotFinishedJobsByMultimediaObjectId($mmobj->getId());
        $this->assertEquals(1, count($jobs)); // TODO: I don't want to wait for the job to finish executing to test the track metadata
    }

    protected function generateTrackFile()
    {
        // $finder = new Finder();
        // $finder->files()->in(__DIR__.'/../../Resources/data/Tests/Controller/IngestControllerTest/');
        $filesDir = __DIR__.'/../../Resources/data/Tests/Controller/IngestControllerTest/';
        $localFile = 'presenter.mp4';
        $uploadFile = 'upload.mp4';
        copy($filesDir.$localFile, $uploadFile);
        $trackFile = new UploadedFile($uploadFile, $localFile);

        return $trackFile;
    }

    public function testAddCatalog()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addCatalog');
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    public function testAddDCCatalog()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addDCCatalog');
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // Test with valid params but wrong values (still should fail)
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'random/catalog',
        );
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams);
        $this->assertEquals(400, $client->getResponse()->getStatusCode());

        // Test with valid params but wrong XML file
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'dublincore/series',
        );
        $badFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $badFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        // Add series catalog (creates new series);
        $mmobj = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $originalSeries = $mmobj->getSeries();
        $seriesFile = $this->getUploadFile('series.xml');
        $seriesCatalog = simplexml_load_file($seriesFile, 'SimpleXMLElement', LIBXML_NOCDATA);

        $namespacesMetadata = $seriesCatalog->getNamespaces(true);
        $seriesCatalogDcterms = $seriesCatalog->children($namespacesMetadata['dcterms']);

        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $seriesFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->dm->refresh($mmobj);
        $newSeries = $mmobj->getSeries();
        $this->assertTrue($newSeries instanceof Series);
        $this->assertNotEquals($originalSeries->getId(), $newSeries->getId());
        $this->assertEquals(2, $this->dm->getRepository('PumukitSchemaBundle:Series')->count());

        // Test reassigning to an existing series
        $seriesCatalogDcterms->identifier = $originalSeries->getId();
        file_put_contents($seriesFile, $seriesCatalog->asXml());
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $seriesFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->dm->refresh($mmobj);
        $newSeries = $mmobj->getSeries();
        $this->assertEquals($originalSeries->getId(), $newSeries->getId());
        $this->assertEquals(2, $this->dm->getRepository('PumukitSchemaBundle:Series')->count());

        // Test assign episode.xml to change title
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'dublincore/episode',
        );
        $episodeFile = $this->getUploadFile('episode.xml');
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $episodeFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->dm->refresh($mmobj);
        $this->assertEquals('Changed title', $mmobj->getTitle());
        foreach($mmobj->getI18nTitle() as $language => $title){
            $this->assertEquals('Changed title', $title);
        }
    }

    protected function getUploadFile($localFile)
    {
        $filesDir = __DIR__.'/../../Resources/data/Tests/Controller/IngestControllerTest/';
        $uploadFile = 'upload.xml';
        copy($filesDir.$localFile, $uploadFile);
        $seriesFile = new UploadedFile($uploadFile, $localFile);

        return $seriesFile;
    }
}
