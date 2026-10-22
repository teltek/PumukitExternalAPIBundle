<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
class APIUpdateControllerTest extends PumukitTestCase
{
    private const ENDPOINT_REMOVE_TAG = '/api/mmobjs/RANDOMID/tags/cod/CUSTOM_TAG';
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
        parent::setUp();
        $this->documentManager = static::$kernel->getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->tagService = static::$kernel->getContainer()->get('pumukitschema.tag');
        $this->allowedRemovedTag = static::$kernel->getContainer()->getParameter('pumukit_external_api.allowed_removed_tag');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->documentManager = null;
        gc_collect_cycles();
    }

    public function testIsDefaultTagConfigured(): void
    {
        $this->assertEquals(self::DEFAULT_ALLOWED_REMOVED_TAG, $this->allowedRemovedTag);
    }

    public function testShouldBlockGETRequest(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $route = str_replace('RANDOMID', new ObjectId(), self::ENDPOINT_REMOVE_TAG);
        $client->request('GET', $route);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    public function testShouldBlockPOSTRequest(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $route = str_replace('RANDOMID', new ObjectId(), self::ENDPOINT_REMOVE_TAG);
        $client->request('POST', $route);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $client->getResponse()->getStatusCode());
    }

    public function testShouldBlockUnauthorizedDELETERequest(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $route = str_replace('RANDOMID', new ObjectId(), self::ENDPOINT_REMOVE_TAG);
        $client->request('DELETE', $route);
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

        $route = str_replace('RANDOMID', $multimediaObject->getId(), self::ENDPOINT_REMOVE_TAG);
        $client->request('DELETE', $route, $postParams);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // NOTE: We need that because document manager have 2 instances: Test and API
        $this->documentManager->clear();
        $multimediaObject = $this->getMultimediaObjectFromMediaPackage($mediaPackage);

        $this->assertFalse($multimediaObject->containsTagWithCod(self::DEFAULT_ALLOWED_REMOVED_TAG));
    }

    protected function createAuthorizedClient()
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $dm = static::getContainer()->get('doctrine_mongodb')->getManager();

        $personalScopeRoleCode = static::getContainer()->get(PersonService::class)->getPersonalScopeRoleCode();
        if (!$dm->getRepository(Role::class)->findOneBy(['cod' => $personalScopeRoleCode])) {
            $role = new Role();
            $role->setCod($personalScopeRoleCode);
            $dm->persist($role);
        }

        $user = $dm->getRepository(User::class)->findOneBy(['username' => 'tester']);
        if (!$user) {
            $user = new User();
            $user->setUsername('tester');
            $user->setEmail('tester@pumukit.test');
            $user->setRoles(['ROLE_ACCESS_INGEST_API', 'ROLE_ACCESS_API']);
            $dm->persist($user);
        }
        $dm->flush();

        $client->loginUser($user, 'pumukit');

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
