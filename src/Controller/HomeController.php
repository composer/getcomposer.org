<?php

namespace App\Controller;

use Composer\Pcre\Preg;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeController extends AbstractController
{
    #[Route("/", name: "home")]
    public function index(string $projectDir): Response
    {
        $logos = glob($projectDir.'/web/img/logo-composer-transparent*.png');
        if (false === $logos) {
            throw new \RuntimeException('Logos not found');
        }
        $logo = basename($logos[array_rand($logos)]);

        $versions = $this->getVersionData($projectDir);

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

    #[Route("/download/", name: "download")]
    public function download(string $projectDir, Request $req): Response
    {
        $versions = array();
        $paths = glob($projectDir.'/web/download/*', GLOB_ONLYDIR);
        if ($paths === false) {
            throw new \RuntimeException('Glob failed');
        }
        foreach ($paths as $version) {
            $sha256sum = file_get_contents($version.'/composer.phar.sha256sum');
            assert(is_string($sha256sum));
            $versions[basename($version)] = [
                'date' => new \DateTime('@'.filemtime($version.'/composer.phar')),
                'sha256sum' => Preg::replace('{^(\S+).*}', '$1', $sha256sum),
            ];
        }

        uksort($versions, fn ($a, $b) => version_compare($a, $b));
        $versions = array_reverse($versions);

        $latestStable = '?';
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
            'windows' => str_contains($req->headers->get('User-Agent', ''), 'Windows'),
        );

        return $this->render('download.html.twig', $data);
    }

    #[Route("/composer.phar", name: "download_snapshot")]
    #[Route("/download/latest-stable/composer.phar", name: "download_stable")]
    #[Route("/download/latest-preview/composer.phar", name: "download_preview")]
    #[Route("/download/latest-1.x/composer.phar", name: "download_1x")]
    #[Route("/download/latest-2.x/composer.phar", name: "download_2x")]
    #[Route("/download/latest-2.2.x/composer.phar", name: "download_2.2_lts")]
    #[Route("/composer-stable.phar", name: "download_stable_bc")]
    #[Route("/composer-preview.phar", name: "download_preview_bc")]
    #[Route("/composer-1.phar", name: "download_1x_bc")]
    #[Route("/composer-2.phar", name: "download_2x_bc")]
    public function downloadVersion(string $projectDir, string $_route): Response
    {
        $channel = str_replace(array('download_', '_bc'), '', $_route);
        $path = $this->getVersionInfo($projectDir, $channel)['path'];

        return new BinaryFileResponse($projectDir.'/web'.$path, 200, [], false, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route("/composer.phar.sha256", name: "download_sha256_snapshot")]
    #[Route("/download/latest-stable/composer.phar.sha256", name: "download_sha256_stable")]
    #[Route("/download/latest-preview/composer.phar.sha256", name: "download_sha256_preview")]
    #[Route("/download/latest-1.x/composer.phar.sha256", name: "download_sha256_1x")]
    #[Route("/download/latest-2.x/composer.phar.sha256", name: "download_sha256_2x")]
    #[Route("/download/latest-2.2.x/composer.phar.sha256", name: "download_sha256_2.2_lts")]
    #[Route("/composer-stable.phar.sha256", name: "download_sha256_stable_bc")]
    #[Route("/composer-preview.phar.sha256", name: "download_sha256_preview_bc")]
    #[Route("/composer-1.phar.sha256", name: "download_sha256_1x_bc")]
    #[Route("/composer-2.phar.sha256", name: "download_sha256_2x_bc")]
    public function downloadSha256(string $projectDir, string $_route): Response
    {
        $channel = str_replace(array('download_sha256_', '_bc'), '', $_route);

        if ($channel === 'snapshot') {
            $file = $projectDir . '/web/composer.phar.sha256sum';
        } else {
            $path = $this->getVersionInfo($projectDir, $channel)['path'];
            $file = $projectDir.'/web'.$path.'.sha256sum';
        }

        $content = file_get_contents($file);
        assert(is_string($content));

        // only return checksum without the filename
        return new Response(substr($content, 0, (int) strpos($content, ' ')), 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    #[Route("/composer.phar.sha256sum", name: "download_sha256sum_snapshot")]
    #[Route("/download/latest-stable/composer.phar.sha256sum", name: "download_sha256sum_stable")]
    #[Route("/download/latest-preview/composer.phar.sha256sum", name: "download_sha256sum_preview")]
    #[Route("/download/latest-1.x/composer.phar.sha256sum", name: "download_sha256sum_1x")]
    #[Route("/download/latest-2.x/composer.phar.sha256sum", name: "download_sha256sum_2x")]
    #[Route("/download/latest-2.2.x/composer.phar.sha256sum", name: "download_sha256sum_2.2_lts")]
    #[Route("/composer-stable.phar.sha256sum", name: "download_sha256sum_stable_bc")]
    #[Route("/composer-preview.phar.sha256sum", name: "download_sha256sum_preview_bc")]
    #[Route("/composer-1.phar.sha256sum", name: "download_sha256sum_1x_bc")]
    #[Route("/composer-2.phar.sha256sum", name: "download_sha256sum_2x_bc")]
    public function downloadSha256Sum(string $projectDir, string $_route): Response
    {
        $channel = str_replace('download_sha256sum_', '', $_route);
        $path = $this->getVersionInfo($projectDir, $channel)['path'];

        return new BinaryFileResponse($projectDir.'/web'.$path.'.sha256sum', 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    #[Route("/download/latest-stable/composer.phar.asc", name: "download_asc_stable")]
    #[Route("/download/latest-preview/composer.phar.asc", name: "download_asc_preview")]
    #[Route("/download/latest-2.x/composer.phar.asc", name: "download_asc_2x")]
    #[Route("/download/latest-2.2.x/composer.phar.asc", name: "download_asc_2.2_lts")]
    #[Route("/download/{version}/composer.phar.asc", name: "download_asc_specific")]
    public function downloadPGPSignature(string $projectDir, string $_route, string $version = null): Response
    {
        $channel = str_replace('download_asc_', '', $_route);
        if ($channel !== 'specific') {
            $version = $this->getVersionInfo($projectDir, $channel)['version'];
        }

        return new RedirectResponse('https://github.com/composer/composer/releases/download/'.$version.'/composer.phar.asc');
    }

    #[Route("/download/{version}/composer.phar", name: "download_version")]
    #[Route("/download/{version}/composer.phar.sha256sum", name: "download_version_sha256sum")]
    #[Route("/download/{version}/composer.phar.sha256", name: "download_version_sha256")]
    public function downloadNotFound(): Response
    {
        return new Response('Version Not Found', 404);
    }

    #[Route("/schema.json", name: "schema")]
    public function schema(string $docDir): Response
    {
        return new BinaryFileResponse($docDir.'/../res/composer-schema.json', 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET',
            'Access-Control-Allow-Headers' => 'X-Requested-With,If-Modified-Since',
        ]);
    }

    /**
     * @return array{path: string, version: string, min-php: int}
     */
    private function getVersionInfo(string $projectDir, string $channel): array
    {
        $origChannel = $channel;

        $channel = Preg::replace('{^(\d+)x$}', '$1', $channel);
        $versions = $this->getVersionData($projectDir);

        if (str_ends_with($channel, 'lts')) {
            list($prefix) = explode('_', $channel);
            foreach ($versions['stable'] as $version) {
                if (str_starts_with($version['version'], $prefix)) {
                    return $version;
                }
            }

            throw new \LogicException('No lts version found with prefix '.$prefix.' (passed in as '.$origChannel.')');
        }

        if (!isset($versions[$channel])) {
            throw new \LogicException('Unknown channel '.$channel.' (passed in as '.$origChannel.')');
        }

        return $versions[$channel][0];
    }

    /**
     * @return array<string, array<int, array{path: string, version: string, min-php: int}>>
     */
    private function getVersionData(string $projectDir): array
    {
        $versionData = file_get_contents($projectDir.'/web/versions');
        assert(is_string($versionData));
        /** @var array<string, array<int, array{path: string, version: string, min-php: int}>> */
        return json_decode($versionData, true);
    }
}
