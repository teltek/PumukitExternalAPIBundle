<?php

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;

class IngestControllerTest extends WebTestCase
{
    private $dm;
    private $jobService;

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
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Person')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Role')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitEncoderBundle:Job')
            ->remove(array());
    }

    public function tearDown()
    {
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Person')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Role')
            ->remove(array());
        $this->dm->getDocumentCollection('PumukitEncoderBundle:Job')
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
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals($createdAt, new \DateTime($mediapackage['start']));
        $this->assertNotEmpty($mediapackage->media);
        $this->assertNotEmpty($mediapackage->metadata);
        $this->assertNotEmpty($mediapackage->attachments);
        $this->assertNotEmpty($mediapackage->publications);
        $this->assertEmpty((string) $mediapackage->media);
        $this->assertEmpty((string) $mediapackage->metadata);
        $this->assertEmpty((string) $mediapackage->attachments);
        $this->assertEmpty((string) $mediapackage->publications);

        $series = $multimediaObject->getSeries();
        $client->request('POST', '/api/ingest/createMediaPackage', array('series' => $series->getId()));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals($series->getId(), $multimediaObject->getSeries()->getId());

        $client->request('POST', '/api/ingest/createMediaPackage', array('series' => 'fake id'));
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
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
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals(count($multimediaObject->getMaterials()), 1);
        $this->assertEquals($multimediaObject->getMaterials()[0]->getMimeType(), $postParams['flavor']);
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
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test with valid params (still should fail)
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'presentation/source',
        );
        $client->request('POST', '/api/ingest/addTrack', $postParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test sending bad media track file
        $badFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addTrack', $postParams, array('BODY' => $badFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $client->getResponse()->getStatusCode());

        // Test sending correct media track file
        $trackFile = $this->generateTrackFile('presenter.mp4');
        $client->request('POST', '/api/ingest/addTrack', $postParams, array('BODY' => $trackFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $jobs = $this->jobService->getNotFinishedJobsByMultimediaObjectId($multimediaObject->getId());
        $this->assertEquals(1, count($jobs)); // TODO: I don't want to wait for the job to finish executing to test the track metadata
    }

    protected function generateTrackFile($localFile)
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
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function testAddDCCatalog()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addDCCatalog');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test with valid params but wrong values (still should fail)
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'random/catalog',
        );
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test with valid params but wrong XML file
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'dublincore/series',
        );
        $badFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $badFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        // Add series catalog (creates new series);
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $originalSeries = $multimediaObject->getSeries();
        $seriesFile = $this->getUploadFile('series.xml');
        $seriesCatalog = simplexml_load_file($seriesFile, 'SimpleXMLElement', LIBXML_NOCDATA);

        $namespacesMetadata = $seriesCatalog->getNamespaces(true);
        $seriesCatalogDcterms = $seriesCatalog->children($namespacesMetadata['dcterms']);

        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $seriesFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->dm->refresh($multimediaObject);
        $newSeries = $multimediaObject->getSeries();
        $this->assertInstanceOf(Series::class, $newSeries);
        $this->assertNotEquals($originalSeries->getId(), $newSeries->getId());
        $this->assertEquals(2, $this->dm->getRepository('PumukitSchemaBundle:Series')->count());

        // Test reassigning to an existing series
        $seriesCatalogDcterms->identifier = $originalSeries->getId();
        file_put_contents($seriesFile, $seriesCatalog->asXml());
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $seriesFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->dm->refresh($multimediaObject);
        $newSeries = $multimediaObject->getSeries();
        $this->assertEquals($originalSeries->getId(), $newSeries->getId());
        $this->assertEquals(2, $this->dm->getRepository('PumukitSchemaBundle:Series')->count());

        // Test assign episode.xml to change title
        $postParams = array(
            'mediaPackage' => $mediapackage->asXML(),
            'flavor' => 'dublincore/episode',
        );
        $episodeFile = $this->getUploadFile('episode.xml');
        $newPerson = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'John doe']);
        $this->assertFalse($newPerson instanceof Person);
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $episodeFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->dm->refresh($multimediaObject);
        $this->assertEquals('Changed title', $multimediaObject->getTitle());
        foreach ($multimediaObject->getI18nTitle() as $language => $title) {
            $this->assertEquals('Changed title', $title);
        }
        foreach ($multimediaObject->getI18nDescription() as $language => $description) {
            $this->assertEquals('Changed description', $description);
        }
        $this->assertEquals('@1999-2019', $multimediaObject->getCopyright());
        $this->assertEquals('All rights reserved', $multimediaObject->getLicense());
        $this->assertEquals(new \DateTime('2018-10-17T14:00:44Z'), $multimediaObject->getRecordDate());
        //There are no people because there are no roles to add them as.
        $this->assertEquals(0, $this->dm->getRepository('PumukitSchemaBundle:Person')->createQueryBuilder()->count()->getQuery()->execute());
        $publisherRole = new Role();
        $publisherRole->setCod('publisher');
        $contributorRole = new Role();
        $contributorRole->setCod('contributor');
        $this->dm->persist($publisherRole);
        $this->dm->persist($contributorRole);
        $this->dm->flush();

        // Test that now all people were added correctly
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $episodeFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(3, $this->dm->getRepository('PumukitSchemaBundle:Person')->createQueryBuilder()->count()->getQuery()->execute());
        $this->dm->refresh($multimediaObject);
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'John doe']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, array($contributorRole)));

        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery johnson']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, array($contributorRole, $publisherRole)));

        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery son']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, array($publisherRole)));

        //Sanity check to make sure we're not duplicating people.
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, array('BODY' => $episodeFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(3, $this->dm->getRepository('PumukitSchemaBundle:Person')->createQueryBuilder()->count()->getQuery()->execute());
    }

    protected function getUploadFile($localFile)
    {
        $filesDir = __DIR__.'/../../Resources/data/Tests/Controller/IngestControllerTest/';
        $uploadFile = 'upload.xml';
        copy($filesDir.$localFile, $uploadFile);
        $seriesFile = new UploadedFile($uploadFile, $localFile);

        return $seriesFile;
    }

    public function testAddMediaPackage()
    {
        // Set up
        $publisherRole = new Role();
        $publisherRole->setCod('publisher');
        $contributorRole = new Role();
        $contributorRole->setCod('contributor');
        $creatorRole = new Role();
        $creatorRole->setCod('creator');
        $notUsedRole = new Role();
        $notUsedRole->setCod('not_used_role');
        $series = new Series();
        $this->dm->persist($publisherRole);
        $this->dm->persist($contributorRole);
        $this->dm->persist($creatorRole);
        $this->dm->persist($notUsedRole);
        $this->dm->persist($series);
        $this->dm->flush();
        $client = $this->createAuthorizedClient();
        // Test without params
        $client->request('POST', '/api/ingest/addMediaPackage');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $postParams = array(
            // Required:
            'flavor' => 'presenter/source',
            // 'flavor' => 'presentation/source', // How to add TWO files simultaneously?
            // Optional:
            //'abstract' => 'Episode metadata value',
            'accessRights' => '@1999-2019',
            //'available' => 'Episode metadata value',
            'contributor' => 'Avery the third',
            //'coverage' => 'Episode metadata value',
            //'created' => 'Episode metadata value',
            'creator' => 'Doe, John',
            //'date' => 'Episode metadata value',
            'description' => 'Description test',
            //'extent' => 'Episode metadata value',
            //'format' => 'Episode metadata value',
            //'identifier' => 'Episode metadata value',
            //'isPartOf' => 'Episode metadata value',
            //'isReferencedBy' => 'Episode metadata value',
            //'isReplacedBy' => 'Episode metadata value',
            //'language' => 'Episode metadata value',
            'license' => 'All rights reserved',
            'publisher' => array('Avery the third', 'Avery the fourth'),
            // 'relation' => 'Episode metadata value',
            // 'replaces' => 'Episode metadata value',
            // 'rights' => 'Episode metadata value',
            // 'rightsHolder' => 'Episode metadata value',
            // 'source' => 'Episode metadata value',
            // 'spatial' => 'Episode metadata value',
            // 'subject' => 'Episode metadata value',
            // 'temporal' => 'Episode metadata value',
            'title' => 'AddMediaPackageTest',
            // 'type' => 'Episode metadata value',
            // 'episodeDCCatalogUri' => 'URL of episode DublinCore Catalog',
            // 'episodeDCCatalog' => 'Episode DublinCore Catalog',
            // 'seriesDCCatalogUri' => 'URL of series DublinCore Catalog',
            // 'seriesDCCatalog' => 'Series DublinCore Catalog',
            // 'mediaUri' => 'URL of a media track file ',
            //Extra:
            'series' => $series->getId(),
        );
        $trackFile = $this->generateTrackFile('presenter.mp4');
        $client->request('POST', '/api/ingest/addMediaPackage', $postParams, array('BODY' => $trackFile), array('CONTENT_TYPE' => 'multipart/form-data'));
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediapackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->assertInstanceOf('SimpleXMLElement', $mediapackage);
        $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(['_id' => (string) $mediapackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals($postParams['accessRights'], $multimediaObject->getCopyright());
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery the third']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, array($contributorRole, $publisherRole)));
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery the fourth']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, array($publisherRole)));
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Doe, John']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, array($creatorRole)));
        $this->assertEquals(3, count($multimediaObject->getPeople()));
        $this->assertEquals(0, count($multimediaObject->getPeopleByRole($notUsedRole)));

        foreach ($multimediaObject->getI18nDescription() as $language => $description) {
            $this->assertEquals($postParams['description'], $description);
        }
        foreach ($multimediaObject->getI18nTitle() as $language => $title) {
            $this->assertEquals($postParams['title'], $title);
        }

        //I can't check for tracks because the jobs haven't finished yet: $this->assertEquals(1, count($multimediaObject->getTracks()));
        $jobs = $this->jobService->getNotFinishedJobsByMultimediaObjectId($multimediaObject->getId());
        $this->assertEquals(1, count($jobs)); // TODO: I don't want to wait for the job to finish executing to test the track metadata

        $trackFiles = array(
            $this->generateTrackFile('presenter.mp4'),
            $this->generateTrackFile('presentation.mp4'),
        );

        $this->assertEquals($series->getId(), $multimediaObject->getSeries()->getId());

        $client->request('POST', '/api/ingest/addMediaPackage', $postParams, array('BODY' => $trackFiles), array('CONTENT_TYPE' => 'multipart/form-data'));
        //$jobs = $this->jobService->getNotFinishedJobsByMultimediaObjectId($multimediaObject->getId());
        $jobRepository = $this->dm->getRepository('PumukitEncoderBundle:Job');
        $this->dm->refresh($multimediaObject);
        $jobs = $jobRepository->findByMultimediaObjectId($multimediaObject->getId());
        //$this->assertEquals(2, count($jobs)); // TODO: I don't want to wait for the job to finish executing to test the track metadata
    }
}
