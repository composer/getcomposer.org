<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

abstract class WebTestCase extends BaseWebTestCase
{
    /**
     * HTTPS by default, as RequestSubscriber redirects plain http requests outside of dev.
     */
    protected static function createHttpsClient(): KernelBrowser
    {
        return static::createClient([], ['HTTPS' => 'on', 'HTTP_HOST' => 'getcomposer.org']);
    }

    protected static function getContent(KernelBrowser $client): string
    {
        // the internal response also captures BinaryFileResponse output
        return $client->getInternalResponse()->getContent();
    }
}
