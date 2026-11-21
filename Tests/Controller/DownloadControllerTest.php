<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Tests\Controller;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MediaType\Metadata\VideoAudio;
use Pumukit\SchemaBundle\Document\MediaType\Storage;
use Pumukit\SchemaBundle\Document\MediaType\Track;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Document\ValueObject\i18nText;
use Pumukit\SchemaBundle\Document\ValueObject\Path;
use Pumukit\SchemaBundle\Document\ValueObject\StorageUrl;
use Pumukit\SchemaBundle\Document\ValueObject\Tags;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
class DownloadControllerTest extends PumukitTestCase
{
    private const DOWNLOAD_SERVER = 'https://dls.example.org';

    private KernelBrowser $client;
    private string $storageDir;
    private string $file;
    private Track $track;
    private array $previousEnv = [];

    public function setUp(): void
    {
        $this->storageDir = (string) realpath(sys_get_temp_dir()).'/external-api-'.bin2hex(random_bytes(6));
        mkdir($this->storageDir.'/downloads', 0777, true);
        foreach (['PUMUKIT_STORAGE_DIR' => $this->storageDir, 'PUMUKIT_DOWNLOAD_SERVER_URL' => self::DOWNLOAD_SERVER] as $name => $value) {
            $this->previousEnv[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->dm = static::getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->clearBBDD();

        $this->file = $this->storageDir.'/downloads/pumukit.mp4';
        file_put_contents($this->file, 'video');
        $this->track = Track::create(
            'pumukit.mp4',
            i18nText::create(['en' => '']),
            'en',
            Tags::create(['display']),
            false,
            true,
            0,
            Storage::create(StorageUrl::create(''), Path::create($this->file)),
            VideoAudio::create('{"format":{"duration":"10.000000"}}')
        );
        $series = new Series();
        $series->setNumericalID(1);
        $multimediaObject = new MultimediaObject();
        $multimediaObject->setNumericalID(1);
        $multimediaObject->setStatus(MultimediaObject::STATUS_BLOCKED);
        $multimediaObject->setSeries($series);
        $multimediaObject->addTrack($this->track);
        $this->dm->persist($series);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $user = new User();
        $user->setUsername('ingest-client');
        $user->setEmail('ingest-client@pumukit.test');
        $user->setRoles(['ROLE_ACCESS_INGEST_API', 'ROLE_ACCESS_API']);
        $user->setEnabled(true);
        $this->dm->persist($user);
        $this->dm->flush();
        $this->client->loginUser($user, 'pumukit');
    }

    public function tearDown(): void
    {
        foreach ($this->previousEnv as $name => [$env, $server]) {
            if (null === $env) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
        @unlink($this->file);
        @rmdir($this->storageDir.'/downloads');
        @rmdir($this->storageDir);
        parent::tearDown();
    }

    public function testATrackIsRedirectedToTheDownloadServerAsAnAttachment(): void
    {
        $this->client->request('GET', $this->url($this->track->id()));

        $this->assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith(self::DOWNLOAD_SERVER.'/files/downloads/pumukit.mp4?token=', $location);
        $this->assertStringContainsString('forcedl=1', $location);
    }

    public function testAMissingFileIsNotFound(): void
    {
        unlink($this->file);

        $this->client->request('GET', $this->url($this->track->id()));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertResponseNotHasHeader('Location');
    }

    public function testAnUnknownTrackIsNotFound(): void
    {
        $this->client->request('GET', $this->url(str_repeat('a', 24)));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTheDownloadNeedsTheIngestApiPermission(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $user = new User();
        $user->setUsername('other');
        $user->setEmail('other@pumukit.test');
        $user->setRoles(['ROLE_ACCESS_API']);
        $user->setEnabled(true);
        $this->dm->persist($user);
        $this->dm->flush();
        $client->loginUser($user, 'pumukit');

        $client->request('GET', $this->url($this->track->id()));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function url(string $trackId): string
    {
        return '/api/ingest/getDownloadTrack/'.$trackId;
    }
}
