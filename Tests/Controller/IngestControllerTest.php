<?php

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\EncoderBundle\Services\JobService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 * @coversNothing
 */
class IngestControllerTest extends WebTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    /**
     * @var JobService
     */
    private $jobService;

    /**
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function setUp()
    {
        $options = ['environment' => 'test'];
        static::bootKernel($options);
        $this->jobService = static::$kernel->getContainer()->get('pumukitencoder.job');
        $this->dm = static::$kernel->getContainer()
            ->get('doctrine_mongodb')->getManager();
        $this->dm->getDocumentCollection(MultimediaObject::class)
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Person')
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Role')
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitEncoderBundle:Job')
            ->remove([])
        ;
    }

    /**
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function tearDown()
    {
        $this->dm->getDocumentCollection(MultimediaObject::class)
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Series')
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Person')
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitSchemaBundle:Role')
            ->remove([])
        ;
        $this->dm->getDocumentCollection('PumukitEncoderBundle:Job')
            ->remove([])
        ;
        $this->dm->close();
        $this->jobService = null;
        gc_collect_cycles();
        parent::tearDown();
    }

    /**
     * @throws \Exception
     */
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
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediaPackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals($createdAt, new \DateTime($mediaPackage['start']));
        $this->assertNotEmpty($mediaPackage->media);
        $this->assertNotEmpty($mediaPackage->metadata);
        $this->assertNotEmpty($mediaPackage->attachments);
        $this->assertNotEmpty($mediaPackage->publications);
        $this->assertEmpty((string) $mediaPackage->media);
        $this->assertEmpty((string) $mediaPackage->metadata);
        $this->assertEmpty((string) $mediaPackage->attachments);
        $this->assertEmpty((string) $mediaPackage->publications);

        $series = $multimediaObject->getSeries();
        $client->request('POST', '/api/ingest/createMediaPackage', ['series' => $series->getId()]);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediaPackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals($series->getId(), $multimediaObject->getSeries()->getId());

        $client->request('POST', '/api/ingest/createMediaPackage', ['series' => 'fake id']);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testAddAttachment()
    {
        // Get valid mediapackage
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        // Fails if no post parameters
        $client->request('POST', '/api/ingest/addAttachment');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
        ];
        // Still fails if no flavor set
        $client->request('POST', '/api/ingest/addAttachment', $postParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // We check success case and that attachment has been added
        // File generation
        $postParams['flavor'] = 'srt';
        $subtitleFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addAttachment', $postParams, ['BODY' => $subtitleFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediaPackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals(count($multimediaObject->getMaterials()), 1);
        $this->assertEquals($multimediaObject->getMaterials()[0]->getMimeType(), $postParams['flavor']);
        $this->assertEquals((string) $mediaPackage->attachments[0]->attachment->mimetype, $postParams['flavor']);
        $this->assertNotEmpty($mediaPackage->media);
        $this->assertNotEmpty($mediaPackage->metadata);
        $this->assertNotEmpty($mediaPackage->attachments);
        $this->assertNotEmpty($mediaPackage->publications);

        // Request with invalid mediapackage
        $mediaPackage['id'] = 'invalid-id';
        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
            'flavor' => 'attachment/srt',
        ];
        $subtitleFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addAttachment', $postParams, ['BODY' => $subtitleFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testAddTrack()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addTrack');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test with valid params (still should fail)
        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
            'flavor' => 'presentation/source',
        ];
        $client->request('POST', '/api/ingest/addTrack', $postParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test sending bad media track file
        $badFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addTrack', $postParams, ['BODY' => $badFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $client->getResponse()->getStatusCode());

        // Test sending correct media track file
        $trackFile = $this->generateTrackFile();
        $client->request('POST', '/api/ingest/addTrack', $postParams, ['BODY' => $trackFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediaPackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $jobs = $this->jobService->getNotFinishedJobsByMultimediaObjectId($multimediaObject->getId());
        $this->assertEquals(1, count($jobs));
    }

    public function testAddCatalog()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        //$mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addCatalog');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function testAddDCCatalog()
    {
        // Set up
        $client = $this->createAuthorizedClient();
        $client->request('POST', '/api/ingest/createMediaPackage');
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

        // Test without params
        $client->request('POST', '/api/ingest/addDCCatalog');
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test with valid params but wrong values (still should fail)
        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
            'flavor' => 'random/catalog',
        ];
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        // Test with valid params but wrong XML file
        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
            'flavor' => 'dublincore/series',
        ];
        $badFile = $this->generateSubtitleFile();
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, ['BODY' => $badFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(500, $client->getResponse()->getStatusCode());

        // Add series catalog (creates new series);
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediaPackage['id']]);
        $originalSeries = $multimediaObject->getSeries();
        $seriesFile = $this->getUploadFile('series.xml');
        $seriesCatalog = simplexml_load_file($seriesFile, 'SimpleXMLElement', LIBXML_NOCDATA);

        $namespacesMetadata = $seriesCatalog->getNamespaces(true);
        $seriesCatalogDcterms = $seriesCatalog->children($namespacesMetadata['dcterms']);

        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, ['BODY' => $seriesFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->dm->refresh($multimediaObject);
        $newSeries = $multimediaObject->getSeries();
        $this->assertInstanceOf(Series::class, $newSeries);
        $this->assertNotEquals($originalSeries->getId(), $newSeries->getId());
        $this->assertEquals(2, $this->dm->getRepository('PumukitSchemaBundle:Series')->count());

        // Test reassigning to an existing series
        $seriesCatalogDcterms->identifier = $originalSeries->getId();
        file_put_contents($seriesFile, $seriesCatalog->asXml());
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, ['BODY' => $seriesFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->dm->refresh($multimediaObject);
        $newSeries = $multimediaObject->getSeries();
        $this->assertEquals($originalSeries->getId(), $newSeries->getId());
        $this->assertEquals(2, $this->dm->getRepository('PumukitSchemaBundle:Series')->count());

        // Test assign episode.xml to change title
        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
            'flavor' => 'dublincore/episode',
        ];
        $episodeFile = $this->getUploadFile('episode.xml');
        $newPerson = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'John doe']);
        $this->assertFalse($newPerson instanceof Person);
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, ['BODY' => $episodeFile], ['CONTENT_TYPE' => 'multipart/form-data']);
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
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, ['BODY' => $episodeFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(3, $this->dm->getRepository('PumukitSchemaBundle:Person')->createQueryBuilder()->count()->getQuery()->execute());
        $this->dm->refresh($multimediaObject);
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'John doe']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, [$contributorRole]));

        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery johnson']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, [$contributorRole, $publisherRole]));

        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery son']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, [$publisherRole]));

        //Sanity check to make sure we're not duplicating people.
        $client->request('POST', '/api/ingest/addDCCatalog', $postParams, ['BODY' => $episodeFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(3, $this->dm->getRepository('PumukitSchemaBundle:Person')->createQueryBuilder()->count()->getQuery()->execute());
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

        $postParams = [
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
            'publisher' => ['Avery the third', 'Avery the fourth'],
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
        ];
        $trackFile = $this->generateTrackFile();
        $client->request('POST', '/api/ingest/addMediaPackage', $postParams, ['BODY' => $trackFile], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $mediaPackage = simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->assertInstanceOf('SimpleXMLElement', $mediaPackage);
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['_id' => (string) $mediaPackage['id']]);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertEquals($postParams['accessRights'], $multimediaObject->getCopyright());
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery the third']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, [$contributorRole, $publisherRole]));
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Avery the fourth']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, [$publisherRole]));
        $person = $this->dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(['name' => 'Doe, John']);
        $this->assertInstanceOf(Person::class, $person);
        $this->assertTrue($multimediaObject->containsPersonWithAllRoles($person, [$creatorRole]));
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
        $this->assertEquals(1, count($jobs));

        $trackFiles = [
            $this->generateTrackFile(),
            $this->generateTrackFile(),
        ];

        $this->assertEquals($series->getId(), $multimediaObject->getSeries()->getId());

        $client->request('POST', '/api/ingest/addMediaPackage', $postParams, ['BODY' => $trackFiles], ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->dm->refresh($multimediaObject);
    }

    /**
     * @return \Symfony\Bundle\FrameworkBundle\Client
     */
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

    /**
     * @return UploadedFile
     */
    protected function generateSubtitleFile()
    {
        $localFile = 'subtitles.srt';
        $fp = fopen($localFile, 'wb');
        fwrite($fp, "1\n00:02:17,440 --> 00:02:20,375\n Senator, we're making\nour final approach into Coruscant.");
        $subtitleFile = new UploadedFile($localFile, $localFile);
        fclose($fp);

        return $subtitleFile;
    }

    /**
     * @return UploadedFile
     */
    protected function generateTrackFile()
    {
        $filesDir = __DIR__.'/../../Resources/data/Tests/Controller/IngestControllerTest/';
        $localFile = 'presenter.mp4';
        $uploadFile = 'upload.mp4';
        copy($filesDir.$localFile, $uploadFile);

        return new UploadedFile($uploadFile, $localFile);
    }

    /**
     * @param $localFile
     *
     * @return UploadedFile
     */
    protected function getUploadFile($localFile)
    {
        $filesDir = __DIR__.'/../../Resources/data/Tests/Controller/IngestControllerTest/';
        $uploadFile = 'upload.xml';
        copy($filesDir.$localFile, $uploadFile);

        return new UploadedFile($uploadFile, $localFile);
    }
}
