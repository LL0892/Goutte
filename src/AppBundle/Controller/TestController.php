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
        $crawler = new Crawler();
        $html = '<p class="message">message ici</p>';
        $result = $crawler->filter('p.message')->html();

        return $this->render('@App/Default/test.html.twig', array(
            'curl' => null,
            'guzzle' => null,
            'crawler' => $result
        ));
    }
}
