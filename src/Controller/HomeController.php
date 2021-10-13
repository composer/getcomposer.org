<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
     * @Route("/composer.phar", name="download_snapshot")
     * @Route("/download/latest-stable/composer.phar", name="download_stable")
     * @Route("/download/latest-preview/composer.phar", name="download_preview")
     * @Route("/download/latest-1.x/composer.phar", name="download_1x")
     * @Route("/download/latest-2.x/composer.phar", name="download_2x")
     * @Route("/composer-stable.phar", name="download_stable_bc")
     * @Route("/composer-preview.phar", name="download_preview_bc")
     * @Route("/composer-1.phar", name="download_1x_bc")
     * @Route("/composer-2.phar", name="download_2x_bc")
     */
    public function downloadVersion(string $projectDir, Request $req): Response
    {
        $channel = str_replace(array('download_', '_bc'), '', $req->attributes->get('_route'));
        $channel = preg_replace('{^(\d+)x$}', '$1', $channel);

        $versions = json_decode(file_get_contents($projectDir.'/web/versions'), true);

        return new BinaryFileResponse($projectDir.'/web'.$versions[$channel][0]['path'], 200, [], false, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    /**
     * @Route("/composer.phar.sha256", name="download_sha256_snapshot")
     * @Route("/download/latest-stable/composer.phar.sha256", name="download_sha256_stable")
     * @Route("/download/latest-preview/composer.phar.sha256", name="download_sha256_preview")
     * @Route("/download/latest-1.x/composer.phar.sha256", name="download_sha256_1x")
     * @Route("/download/latest-2.x/composer.phar.sha256", name="download_sha256_2x")
     * @Route("/composer-stable.phar.sha256", name="download_sha256_stable_bc")
     * @Route("/composer-preview.phar.sha256", name="download_sha256_preview_bc")
     * @Route("/composer-1.phar.sha256", name="download_sha256_1x_bc")
     * @Route("/composer-2.phar.sha256", name="download_sha256_2x_bc")
     */
    public function downloadSha256(string $projectDir, Request $req): Response
    {
        $channel = str_replace(array('download_sha256_', '_bc'), '', $req->attributes->get('_route'));
        $channel = preg_replace('{^(\d+)x$}', '$1', $channel);

        if ($channel === 'snapshot') {
            $file = $projectDir . '/web/composer.phar.sha256sum';
        } else {
            $versions = json_decode(file_get_contents($projectDir.'/web/versions'), true);
            $file = $projectDir.'/web'.$versions[$channel][0]['path'].'.sha256sum';
        }

        $content = file_get_contents($file);

        // only return checksum without the filename
        return new Response(substr($content, 0, strpos($content, ' ')), 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * @Route("/composer.phar.sha256sum", name="download_sha256sum_snapshot")
     * @Route("/download/latest-stable/composer.phar.sha256sum", name="download_sha256sum_stable")
     * @Route("/download/latest-preview/composer.phar.sha256sum", name="download_sha256sum_preview")
     * @Route("/download/latest-1.x/composer.phar.sha256sum", name="download_sha256sum_1x")
     * @Route("/download/latest-2.x/composer.phar.sha256sum", name="download_sha256sum_2x")
     * @Route("/composer-stable.phar.sha256sum", name="download_sha256sum_stable_bc")
     * @Route("/composer-preview.phar.sha256sum", name="download_sha256sum_preview_bc")
     * @Route("/composer-1.phar.sha256sum", name="download_sha256sum_1x_bc")
     * @Route("/composer-2.phar.sha256sum", name="download_sha256sum_2x_bc")
     */
    public function downloadSha256Sum(string $projectDir, Request $req): Response
    {
        $channel = str_replace('download_sha256sum_', '', $req->attributes->get('_route'));
        $channel = preg_replace('{^(\d+)x$}', '$1', $channel);

        $versions = json_decode(file_get_contents($projectDir.'/web/versions'), true);

        return new Response(file_get_contents($projectDir.'/web'.$versions[$channel][0]['path'].'.sha256sum'), 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * @Route("/download/latest-stable/composer.phar.asc", name="download_asc_stable")
     * @Route("/download/latest-preview/composer.phar.asc", name="download_asc_preview")
     * @Route("/download/latest-2.x/composer.phar.asc", name="download_asc_2x")
     * @Route("/download/{version}/composer.phar.asc", name="download_asc_specific")
     */
    public function downloadPGPSignature(string $projectDir, Request $req, string $version = null): Response
    {
        $channel = str_replace('download_asc_', '', $req->attributes->get('_route'));
        if ($channel !== 'specific') {
            $channel = preg_replace('{^(\d+)x$}', '$1', $channel);
            $versions = json_decode(file_get_contents($projectDir.'/web/versions'), true);
            $version = $versions[$channel][0]['version'];
        }

        return new RedirectResponse('https://github.com/composer/composer/releases/download/'.$version.'/composer.phar.asc');
    }

    /**
     * @Route("/download/{version}/composer.phar", name="download_version")
     * @Route("/download/{version}/composer.phar.sha256sum", name="download_version_sha256sum")
     * @Route("/download/{version}/composer.phar.sha256", name="download_version_sha256")
     */
    public function downloadNotFound(): Response
    {
        return new Response('Version Not Found', 404);
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
