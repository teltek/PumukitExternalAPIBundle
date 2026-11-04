<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\TagService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
class IngestClientContractTest extends PumukitTestCase
{
    private const PASSWORD = 'integration-password';
    private const LIST_URL = '/api/media/mmobj.json?criteria[tags.cod]=PUDEINGEST';

    public function testTheIngestClientFlowWithBasicAuth(): void
    {
        $client = $this->client();
        $user = $this->user('ingest-client', ['ROLE_ACCESS_API', 'ROLE_ACCESS_INGEST_API']);
        $multimediaObject = $this->taggedMultimediaObject($user);
        $id = $multimediaObject->getId();
        $headers = $this->basicAuth($user);

        $client->request('GET', self::LIST_URL, [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString($id, $client->getResponse()->getContent());

        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00.000 --> 00:01.000\nHello\n");
        $parameters = [
            'mediaPackage' => sprintf('<mediapackage id="%s" start="2026-01-01T00:00:00Z"></mediapackage>', $id),
            'flavor' => 'vtt',
            'language' => 'es',
        ];
        $client->request('POST', '/api/ingest/addAttachment', $parameters, ['BODY' => new UploadedFile($vtt, 'caption.vtt')], $headers + ['CONTENT_TYPE' => 'multipart/form-data']);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('DELETE', sprintf('/api/mmobjs/%s/tags/cod/CUSTOM_TAG', $id), [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $documentManager = $this->documentManager();
        $documentManager->clear();
        $multimediaObject = $documentManager->getRepository(MultimediaObject::class)->find($id);
        $this->assertFalse($multimediaObject->containsTagWithCod('CUSTOM_TAG'));
        $this->assertSame('vtt', $multimediaObject->getMaterials()[0]->getMimeType());
        $this->assertSame('es', $multimediaObject->getMaterials()[0]->getLanguage());
    }

    public function testReadingTheMediaNeedsTheApiPermission(): void
    {
        $client = $this->client();
        $user = $this->user('ingest-only', ['ROLE_SCOPE_GLOBAL', 'ROLE_ACCESS_INGEST_API']);

        $client->request('GET', self::LIST_URL, [], [], $this->basicAuth($user));
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/ingest/createMediaPackage', [], [], $this->basicAuth($user));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $client = $this->client();
        $user = $this->user('ingest-client', ['ROLE_ACCESS_API', 'ROLE_ACCESS_INGEST_API']);

        $client->request('POST', '/api/ingest/createMediaPackage', [], [], ['PHP_AUTH_USER' => $user->getUsername(), 'PHP_AUTH_PW' => 'wrong']);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    private function client(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return static::createClient();
    }

    private function documentManager(): DocumentManager
    {
        return static::getContainer()->get('doctrine_mongodb')->getManager();
    }

    private function user(string $username, array $roles): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username.'@pumukit.test');
        $user->setRoles($roles);
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, self::PASSWORD));

        $role = new Role();
        $role->setCod(static::getContainer()->get(PersonService::class)->getPersonalScopeRoleCode());

        $documentManager = $this->documentManager();
        $documentManager->persist($user);
        $documentManager->persist($role);
        $documentManager->flush();

        return $user;
    }

    private function basicAuth(User $user): array
    {
        return ['PHP_AUTH_USER' => $user->getUsername(), 'PHP_AUTH_PW' => self::PASSWORD];
    }

    private function taggedMultimediaObject(User $user): MultimediaObject
    {
        $documentManager = $this->documentManager();
        $factory = static::getContainer()->get(FactoryService::class);
        $multimediaObject = $factory->createMultimediaObject($factory->createSeries($user), true, $user);

        foreach (['PUDEINGEST', 'CUSTOM_TAG'] as $cod) {
            $tag = new Tag();
            $tag->setCod($cod);
            $tag->setTitle($cod);
            $tag->setDisplay(false);
            $tag->setMetatag(true);
            $documentManager->persist($tag);
            $documentManager->flush();
            static::getContainer()->get(TagService::class)->addTagToMultimediaObject($multimediaObject, $tag->getId(), true);
        }

        return $multimediaObject;
    }
}
