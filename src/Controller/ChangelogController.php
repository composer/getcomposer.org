<?php

namespace App\Controller;

use Composer\Pcre\Preg;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChangelogController extends AbstractController
{
    #[Route("/changelog/{version}", name: "changelog")]
    public function changelog(string $version, \Parsedown $parsedown, string $docDir, HttpClientInterface $client): Response
    {
        $changelog = file_get_contents($docDir.'/../CHANGELOG.md');
        assert(is_string($changelog));
        $changelog = strtr($changelog, ["\r\n" => "\n"]);

        if (!Preg::isMatchStrictGroups('{(?:^|\n)### \['.preg_quote($version).'\] (?P<date>.*)\n\n(?P<changelog>(?:^  (?:\*|  -).*\n)+)}mi', $changelog, $match)) {
            $resp = $client->request('GET', 'https://api.github.com/repos/composer/composer/releases/tags/' . $version);
            if ($resp->getStatusCode() >= 300) {
                if ($resp->getHeaders(false)['x-ratelimit-remaining'][0] === '0') {
                    return $this->redirect('https://github.com/composer/composer/releases/tag/' . $version);
                }
                throw $this->createNotFoundException('Requested page was not found.');
            }

            $data = json_decode($resp->getContent(false), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['body'], $data['created_at']) || !is_string($data['body']) || !is_string($data['created_at'])) {
                throw $this->createNotFoundException('Requested page was not found.');
            }

            $match = ['changelog' => $data['body'], 'date' => substr($data['created_at'], 0, 10)];
        }

        $changelog = $parsedown->text($match['changelog']);

        $changelog = str_replace('href="doc/', 'href="../doc/', $changelog);
        $changelog = str_replace('href="UPGRADE', 'href="../upgrade/UPGRADE', $changelog);

        return $this->render('changelog.html.twig', array(
            'changelog' => $changelog,
            'version' => $version,
            'date' => trim($match['date'], '- '),
            'page' => 'docs',
        ));
    }

    #[Route("/upgrade/{file}", name: "upgrade")]
    public function upgrade(string $file, \Parsedown $parsedown, string $docDir): Response
    {
        $filename = $docDir.'/../'.$file;
        if (!Preg::isMatch('{^UPGRADE[-\d.]+?\.md$}', $file) || !file_exists($filename)) {
            throw $this->createNotFoundException('Requested page was not found.');
        }

        $upgrade = file_get_contents($filename);
        $upgrade = $parsedown->text($upgrade);
        $upgrade = str_replace('href="doc/', 'href="../doc/', $upgrade);

        return $this->render('upgrade.html.twig', array(
            'upgrade' => $upgrade,
            'filename' => $file,
            'page' => 'docs',
        ));
    }
}
