<?php

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Pumukit\SchemaBundle\Document\MultimediaObject;

class IngestControllerTest extends WebTestCase
{
    private $dm;

    public function setUp()
    {
        $options = array('environment' => 'test');
        static::bootKernel($options);
        $this->dm = static::$kernel->getContainer()
            ->get('doctrine_mongodb')->getManager();
        $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject')
            ->remove(array());
    }

    public function tearDown()
    {
        $this->dm->close();
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
}
