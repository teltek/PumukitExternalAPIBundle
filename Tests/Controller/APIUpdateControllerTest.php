<?php

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @internal
 * @coversNothing
 */
class APIUpdateControllerTest extends PumukitTestCase
{
    private const ENDPOINT_REMOVE_TAG = '/api/delete/tag';
    private const ENDPOINT_CREATE_MEDIA_PACKAGE = '/api/ingest/createMediaPackage';
    private const DEFAULT_ALLOWED_REMOVED_TAG = 'CUSTOM_TAG';

    /** @var DocumentManager */
    private $documentManager;
    /** @var TagService */
    private $tagService;
    private $allowedRemovedTag;

    public function setUp(): void
    {
        $options = ['environment' => 'test'];
        static::bootKernel($options);
        $this->documentManager = static::$kernel->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->tagService = static::$kernel->getContainer()->get('pumukitschema.tag');
        $this->allowedRemovedTag = static::$kernel->getContainer()->getParameter('pumukit_external_api.allowed_removed_tag');

        $this->documentManager->getDocumentCollection(MultimediaObject::class)->remove([]);
    }

    public function tearDown(): void
    {
        $this->documentManager = null;
        gc_collect_cycles();
        parent::tearDown();
    }

    public function testIsDefaultTagConfigured(): void
    {
        $this->assertEquals(self::DEFAULT_ALLOWED_REMOVED_TAG, $this->allowedRemovedTag);
    }

    public function testShouldBlockGETRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ENDPOINT_REMOVE_TAG);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    public function testShouldBlockPOSTRequest(): void
    {
        $client = static::createClient();

        $client->request('POST', self::ENDPOINT_REMOVE_TAG);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    public function testShouldBlockUnauthorizedDELETERequest(): void
    {
        $client = static::createClient();

        $client->request('DELETE', self::ENDPOINT_REMOVE_TAG);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testShouldAllowAuthorizedDELETERequest(): void
    {
        $mediaPackage = $this->createMediaPackage();
        $multimediaObject = $this->getMultimediaObjectFromMediaPackage($mediaPackage);
        $tag = $this->createCustomTag();
        $this->tagService->addTagToMultimediaObject($multimediaObject, $tag->getId(), true);

        $this->assertTrue($multimediaObject->containsTagWithCod(self::DEFAULT_ALLOWED_REMOVED_TAG));

        $postParams = [
            'mediaPackage' => $mediaPackage->asXML(),
        ];

        $client = $this->createAuthorizedClient();
        $client->request('DELETE', self::ENDPOINT_REMOVE_TAG, $postParams);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // NOTE: We need that because document manager have 2 instances: Test and API
        $this->documentManager->clear();
        $multimediaObject = $this->getMultimediaObjectFromMediaPackage($mediaPackage);

        $this->assertFalse($multimediaObject->containsTagWithCod(self::DEFAULT_ALLOWED_REMOVED_TAG));
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

    protected function createMediaPackage()
    {
        $client = $this->createAuthorizedClient();
        $client->request('POST', self::ENDPOINT_CREATE_MEDIA_PACKAGE);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        return simplexml_load_string($client->getResponse()->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
    }

    protected function getMultimediaObjectFromMediaPackage($mediaPackage)
    {
        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->findOneBy(
            ['_id' => (string) $mediaPackage['id']]
        );

        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);

        return $multimediaObject;
    }

    protected function createCustomTag(): Tag
    {
        $tag = new Tag();
        $tag->setCod('CUSTOM_TAG');
        $tag->setTitle('CUSTOM_TAG');
        $tag->setDisplay(false);
        $tag->setMetatag(true);

        $this->documentManager->persist($tag);
        $this->documentManager->flush();

        return $tag;
    }
}
