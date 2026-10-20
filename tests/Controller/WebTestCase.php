<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;

abstract class WebTestCase extends BaseWebTestCase
{
    protected static function getContent(KernelBrowser $client): string
    {
        // the internal response also captures BinaryFileResponse output
        return $client->getInternalResponse()->getContent();
    }
}
