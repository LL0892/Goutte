<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\Query;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
//use Symfony\Component\BrowserKit\Response;
use Goutte\Client;

// Guzzle lib test
//use GuzzleHttp\Client as GuzzleClient;
//use GuzzleHttp\Promise\Promise;
//use GuzzleHttp\Pool;
//use GuzzleHttp\Psr7\Request as GuzzleRequest;
//use Psr\Http\Message\ResponseInterface;
//use GuzzleHttp\Exception\RequestException;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $client = new Client();
        $query = new Query();
        $totalResult = null;


        // Search form
        $searchForm = $this->createFormBuilder($query)
            ->add('search', TextType::class, array(
                'attr' => array(
                    'placeholder' => 'Votre recherche',
                ),
                'label' => false
            ))
            ->add('save', SubmitType::class, array('label' => 'Search'))
            ->getForm();


        $postData = $request->request->all();

        if (count($postData) <= 0) {
            $searchQuery = null;
        } else {
            $searchQuery = $postData['form']['search'];
        }

        //$searchQuery = 'PANASONIC DMC-LX100';

        if (count($postData) > 0) {

            $config = $this->getParameter('app.config');

            foreach ($config['sites'] as $site) {

                $baseUrl = $site['url'];
                $crawler = $client->request('GET', $baseUrl);

                $form = $crawler->filter($site['formNode'])->first()->form();

                $crawler = $client->submit($form, array(
                    $site['inputKey'] => $searchQuery
                ));

                $data = $crawler->filter($site['mainNode'])->each(function ($node, $i) use ($site) {

                    $titleNode = $site['titleNode'];
                    $priceNode = $site['priceNode'];
                    $urlNode = $site['urlNode'];
                    $imageNode = $site['imageNode'];
                    $descNode = $site['descNode'];

                    if ($site['titleStandardNode'] === true) {
                        $name = $node->filter($titleNode)->text();
                    } else {
                        $name = $node->filter($titleNode)->attr('title');
                    }
                    $price = $node->filter($priceNode)->text();
                    $url = $node->filter($urlNode)->attr('href');
                    $image = $node->filter($imageNode)->attr('src');

                    $data = array(
                        'name' => trim($name),
                        'price' => trim($price),
                        'url' => trim($url),
                        'image' => trim($image),
                    );

                    return $data;
                });

                $result = array(
                    'siteName' => $site['name'],
                    'baseUrl' => $baseUrl,
                    'data' => $data,
                    'dataCount' => count($data)
                );
                $totalResult[] = $result;
            }

            //dump($totalResult);
            //exit;



/*            $baseUrl = 'http://shop.heinigerag.ch/';
            $crawler = $client->request('GET', $baseUrl);
            $form = $crawler->filter('#quicksearch')->first()->form();
            $crawler = $client->submit($form, array(
                'q' => $searchQuery,
                'sort' => 'relevance|desc'
            ));

            $data = $crawler->filter('div.item.media')->each(function ($node) {
                $name = $node->filter('h4.media-heading > a')->text();
                $subUrl = $node->filter('h4.media-heading > a')->attr('href');
                $desc = $node->filter('span.productName')->text();
                $price = $node->filter('span.price')->text();
                $image = $node->filter('div.media-left > a > img')->attr('src');

                $data = array(
                    'name' => $name,
                    'url' => $subUrl,
                    'price' => $price,
                    'desc' => $desc,
                    'image' => $image
                );

                return $data;
            });

            $result = array(
                "siteName" => "heinigerag",
                'baseUrl' => $baseUrl,
                'data' => $data,
                'dataCount' => count($data)
            );
            $totalResult[] = $result;



            $baseUrl = 'http://www.melectronics.ch/fr/';
            $crawler = $client->request('GET', $baseUrl);
            $form = $crawler->filter('#searchbox')->first()->form();
            $crawler = $client->submit($form, array(
                'q' => $searchQuery
            ));

            $data = $crawler->filter('div.listing > ul > li')->each(function ($node) {
                $name = $node->filter('h3.productname')->text();
                $subUrl = $node->filter('div.productcell > div.content > a')->attr('href');
                $desc = $node->filter('span.topfacts')->text();
                $price = $node->filter('span.price > span.current')->text();
                $image = $node->filter('span.product > img')->attr('src');

                $data = array(
                    'name' => $name,
                    'url' => $subUrl,
                    'price' => $price,
                    'desc' => $desc,
                    'image' => $image
                );

                return $data;
            });

            $result = array(
                'baseUrl' => $baseUrl,
                'data' => $data,
                'dataCount' => count($data)
            );

            $totalResult[] = $result;



            $baseUrl = 'http://www.hawk.ch';
            $crawler = $client->request('GET', $baseUrl);
            $form = $crawler->filter('#search_mini_form')->first()->form();
            $crawler = $client->submit($form, array(
                'q' => $searchQuery
            ));

            $data = $crawler->filter('ul.products-grid > li.item')->each(function ($node) {
                $name = $node->filter('h2.product-name a')->attr('title');
                $subUrl = $node->filter('h2.product-name > a')->attr('href');
                $desc = null;
                $price = $node->filter('span.price')->text();
                $image = $node->filter('a.product-image > img')->attr('src');

                $data = array(
                    'name' => $name,
                    'url' => $subUrl,
                    'price' => $price,
                    'desc' => $desc,
                    'image' => $image
                );

                return $data;
            });

            $result = array(
                'baseUrl' => $baseUrl,
                'data' => $data,
                'dataCount' => count($data)
            );
            $totalResult[] = $result;*/

            //dump($totalResult);
            //print $crawler->html();
            //dump($form);
            //dump($crawler);
            //dump($client->getResponse()->getContent());
            //exit;
        }


        return $this->render('AppBundle:Default:index.html.twig', array(
            'results' => $totalResult,
            'form' => $searchForm->createView()
        ));
    }

    private function filterResult ($search, $result) {
        $arrayFiltered = array();
        foreach ($result['data'] as $res) {
            $trimmed = trim($res['name']);
            $split = explode(' ', $trimmed);
            array_push($arrayFiltered, $split);
        }

        dump($arrayFiltered);
    }

    /**
     * @Route("/test", name="test")
     */
    public function testAction(Request $request)
    {

        $data = array(
            'http://shop.heinigerag.ch/',
            'http://www.melectronics.ch/fr/',
            'http://www.hawk.ch'
        );

        // array of curl handles
        $curly = array();
        // data to be returned
        $result = array();

        // multi handle
        $mh = curl_multi_init();

        // loop through $data and create curl handles
        // then add them to the multi-handle
        foreach ($data as $id => $d) {

            $curly[$id] = curl_init();

            $url = (is_array($d) && !empty($d['url'])) ? $d['url'] : $d;
            curl_setopt($curly[$id], CURLOPT_URL, $url);
            curl_setopt($curly[$id], CURLOPT_HEADER, 0);
            curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);

            // post?
            if (is_array($d)) {
                if (!empty($d['post'])) {
                    curl_setopt($curly[$id], CURLOPT_POST, 1);
                    curl_setopt($curly[$id], CURLOPT_POSTFIELDS, $d['post']);
                }
            }

            curl_multi_add_handle($mh, $curly[$id]);
        }

        // execute the handles
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);


        // get content and remove handles
        foreach ($curly as $id => $c) {
            $result[$id] = curl_multi_getcontent($c);
            curl_multi_remove_handle($mh, $c);
        }

        // all done
        curl_multi_close($mh);

        foreach ($result as $res) {
            dump($res);
        }



        return $this->render('AppBundle:Default:test.html.twig', array());
    }
}
