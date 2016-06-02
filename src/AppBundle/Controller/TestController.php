<?php

namespace AppBundle\Controller;

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Exception\BadResponseException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

class TestController extends Controller
{
    /**
     * @Route("/curl", name="curl")
     */
    public function curlAction()
    {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://www.digitec.ch/fr/s1/product/panasonic-lumix-dmc-lx100-noir-1280mpx-appareils-photo-2758631'
        ));

        $result = curl_exec($curl);

        curl_close($curl);

        return $this->render('@App/Default/test.html.twig', array(
            'curl' => $result,
            'guzzle' => null,
            'crawler' => null
        ));
    }

    /**
     * @Route("/guzzle", name="guzzle")
     */
    public function guzzleAction()
    {
        $client = new GuzzleClient();
        $response = null;

        try {
            $response = $client->get('https://www.digitec.ch/fr/s1/product/panasonic-lumix-dmc-lx100-noir-1280mpx-appareils-photo-2758631', ['config' => ['curl' => [CURLOPT_FOLLOWLOCATION => true], ['http_errors' => false]]]);
        } catch (BadResponseException $e) {
            echo 'An error occured : ' . $e->getMessage();
        }

        return $this->render('@App/Default/test.html.twig', array(
            'curl' => null,
            'guzzle' => $response,
            'crawler' => null,
        ));
    }

    /**
     * @Route("/crawler", name="crawler")
     */
    public function crawlerAction()
    {

// heredoc chain
$html =
<<<EOT
<script data-to-parse="I can parse it if I want"></script>
EOT;

        $crawler = new Crawler($html);
        $result = $crawler->filter('script')->attr('data-to-parse');

        return $this->render('@App/Default/test.html.twig', array(
            'curl' => null,
            'guzzle' => null,
            'crawler' => $result
        ));
    }
}
