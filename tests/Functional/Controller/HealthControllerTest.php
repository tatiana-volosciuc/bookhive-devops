<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testHealthEndpointReturnsJsonContentType(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testHealthEndpointReturnsExpectedPayload(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $content = $client->getResponse()->getContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertSame('ok', $data['status']);
    }

    public function testHealthEndpointTimestampIsValidAtomFormat(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $data = json_decode($client->getResponse()->getContent(), true);

        $timestamp = \DateTimeImmutable::createFromFormat(
            \DateTimeInterface::ATOM,
            $data['timestamp']
        );

        $this->assertInstanceOf(\DateTimeImmutable::class, $timestamp);
    }

    public function testHealthEndpointRejectsPost(): void
    {
        $client = static::createClient();
        $client->request('POST', '/health');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testHealthEndpointRejectsPut(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/health');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testHealthEndpointRejectsDelete(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/health');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testHealthEndpointIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotSame(401, $statusCode);
        $this->assertNotSame(403, $statusCode);
    }
}
