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
<body class="bodyTest">
    <script id="script_dataLayer">
    dataLayer = [{
    'pageCategory': 'product detail',
    'pageLanguage': 'fr',
    'shopCategory': 'shop',
    'shopDevicecategory': 'desktop',
    'userDevicecategory': 'desktop',
    'userId': '',
    'userIp': '85.218.57.90',
    'adBlocker': '[ADBLOCKER]',
    'accountType': 'noreg',
    'userAgent': 'mozilla/5.0 (windows nt 10.0; wow64) applewebkit/537.36 (khtml, like gecko) chrome/50.0.2661.102 safari/537.36',
    'customerGender': '',
    'customerZip': '',
    'customerEmail': '',
    'productEan': '5025232804924',
    'productTitle': 'panasonic lumix dmc-lx100, 12.8mp, argent',
    'manufacturerName': 'panasonic',
    'sku': '1484715',
    'categoryName': 'appareils photo compacts',
    'categoryLevel1': 'photo & vidéo ',
    'categoryLevel2': 'caméras',
    'categoryLevel3': 'appareils photo compacts',
    'categoryLevel4': '',
    'categoryLevel5': '',
    'merchantId': '5e6e27af-dce1-4fd0-a718-054fa4cc4f43',
    'productAuthor': '',
    'productWeight': 0.500,
    'categoryId': 'idkq3gidn47e',
    'productMpn': 'dmc-lx100egs',
    'price': 639.00,
    'guaranty': 24,
    'noPickup': 0,
    }];
</script>
</body>
EOT;

        $crawler = new Crawler($html);
        $result = $crawler->filter('script#script_dataLayer')->text();
        
        $result = trim($result);
        $result = str_replace('dataLayer = ', '', $result);
        //$result = str_replace('\'', '"', $result);
        $result = rtrim($result, ';');
        $res = json_decode($result);
        echo $result; exit;

        return $this->render('@App/Default/test.html.twig', array(
            'curl' => null,
            'guzzle' => null,
            'crawler' => $result
        ));
    }
}
