<?php

namespace App\Controller;

use Composer\Pcre\Preg;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ChangelogController extends AbstractController
{
    /**
     * @Route("/changelog/{version}", name="changelog")
     */
    public function changelog(string $version, \Parsedown $parsedown, string $docDir): Response
    {
        $changelog = file_get_contents($docDir.'/../CHANGELOG.md');
        assert(is_string($changelog));
        $changelog = strtr($changelog, ["\r\n" => "\n"]);

        if (!Preg::isMatch('{(?:^|\n)### \['.preg_quote($version).'\] (?P<date>.*)\n\n(?P<changelog>(?:^  \*.*\n)+)}mi', $changelog, $match)) {
            throw $this->createNotFoundException('Requested page was not found.');
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

    /**
     * @Route("/upgrade/{file}", name="upgrade")
     */
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
