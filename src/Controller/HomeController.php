<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(string $projectDir): Response
    {
        $logos = glob($projectDir.'/web/img/logo-composer-transparent*.png');
        $logo = basename($logos[array_rand($logos)]);

        $versions = json_decode(file_get_contents($projectDir.'/web/versions'), true);

        $latestStable = $versions['stable'][0]['version'];
        $latestPreview = $versions['preview'][0]['version'];
        $majorPreviewAvailable = (int) $latestPreview !== (int) $latestStable;
        $previewAvailable = $latestPreview !== $latestStable;

        return $this->render('index.html.twig', [
            'logo' => $logo,
            'latestStable' => $latestStable,
            'latestPreview' => $latestPreview,
            'majorPreviewAvailable' => $majorPreviewAvailable,
            'previewAvailable' => $previewAvailable,
        ]);
    }

    /**
     * @Route("/download/", name="download")
     */
    public function download(string $projectDir, Request $req): Response
    {
        $versions = array();
        foreach (glob($projectDir.'/web/download/*', GLOB_ONLYDIR) as $version) {
            $versions[basename($version)] = ['date' => new \DateTime('@'.filemtime($version.'/composer.phar')), 'sha256sum' => preg_replace('{^(\S+).*}', '$1', file_get_contents($version.'/composer.phar.sha256sum'))];
        }

        uksort($versions, 'version_compare');
        $versions = array_reverse($versions);

        foreach ($versions as $version => $versionMeta) {
            if (strpos($version, '-') === false) {
                $latestStable = $version;
                break;
            }
        }

        $data = array(
            'page' => 'download',
            'versions' => $versions,
            'latestStable' => $latestStable,
            'windows' => false !== strpos($req->headers->get('User-Agent'), 'Windows'),
        );

        return $this->render('download.html.twig', $data);
    }

    /**
     * @Route("/download/{version}/composer.phar", name="download_version")
     */
    public function downloadNotFound(): Response
    {
        return new Response('Version Not Found', 404);
    }

    /**
     * @Route("/composer-stable.phar", name="download_stable")
     * @Route("/composer-preview.phar", name="download_preview")
     * @Route("/composer-1.phar", name="download_1x")
     * @Route("/composer-2.phar", name="download_2x")
     */
    public function downloadVersion(string $projectDir, Request $req): Response
    {
        $channel = str_replace('download_', '', $req->attributes->get('_route'));
        $channel = preg_replace('{^(\d+)x$}', '$1', $channel);

        $versions = json_decode(file_get_contents($projectDir.'/web/versions'), true);

        return new BinaryFileResponse($projectDir.'/web'.$versions[$channel][0]['path'], 200, [], false, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    /**
     * @Route("/schema.json", name="schema")
     */
    public function schema(string $docDir): Response
    {
        return new Response(file_get_contents($docDir.'/../res/composer-schema.json'), 200, [
            'content-type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET',
            'Access-Control-Allow-Headers' => 'X-Requested-With,If-Modified-Since',
        ]);
    }
}
