<?php

declare(strict_types = 1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ApiDocRoutesTest extends WebTestCase
{
    /**
     * The doc routes are registered under `when@dev` only. The test kernel is
     * not dev, so they must be absent here exactly as they are in prod.
     */
    public function testApiDocIsNotRoutedOutsideDev(): void
    {
        $client = static::createClient();

        $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/doc');
        static::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/doc.json');
        static::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }
}
