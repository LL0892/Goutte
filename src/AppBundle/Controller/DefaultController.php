<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\Query;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\DomCrawler\Crawler;
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


        // Fetch config parameters
        $config = $this->getParameter('app.config');
        if (!$config['sites']) {
            return $this->render('AppBundle:Default:nosetup.html.twig');
        }


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

        if (count($postData) > 0) {

            foreach ($config['sites'] as $site) {

                $baseUrl = $site['parseUrl'];
                $crawler = $client->request('GET', $baseUrl);

                $form = $crawler->filter($site['formNode'])->first()->form();

                // Create the form inputs array
                $formArray = array(
                    $site['inputKey'] => $searchQuery
                );
                if (count($site['formInputs']) > 0) {
                    $formArray = array_merge($formArray, $site['formInputs']);
                }

                $crawler = $client->submit($form, $formArray);

                //print $crawler->html(); exit;

                $data = $crawler->filter($site['mainNode'])->each(function ($node, $i) use ($site) {

                    $titleNode = $site['titleNode'];
                    $priceNode = $site['priceNode'];
                    $urlNode = $site['urlNode']['value'];
                    $imageNode = $site['imageNode']['value'];

                    // title handling
                    if ($site['titleStandardNode'] === true) {
                        $name = $node->filter($titleNode)->text();
                    } else {
                        $name = $node->filter($titleNode)->attr('title');
                    }

                    // price handling
                    $price = $node->filter($priceNode)->text();

                    // url handling
                    $urlFetched = $node->filter($urlNode)->attr('href');
                    switch ($site['urlNode']['type']) {
                        case 'relative':
                            $url = $site['baseUrl'] . trim($urlFetched);
                            break;
                        case 'absolute':
                            $url = trim($urlFetched);
                            break;
                        default:
                            $url = trim($urlFetched);
                    }

                    // image handling
                    $imageFetched = $node->filter($imageNode)->attr('src');
                    switch ($site['imageNode']['type']) {
                        case 'relative':
                            $image = $site['baseUrl'] . trim($imageFetched);
                            break;
                        case 'absolute':
                            $image = trim($imageFetched);
                            break;
                        default:
                            $image = trim($imageFetched);
                    }

                    $data = array(
                        'name' => trim($name),
                        'price' => trim($price),
                        'url' => $url,
                        'image' => $image,
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
            
            if ($config['debug'] === true) {
                dump($totalResult);
                exit;
            }

            dump($totalResult);
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
