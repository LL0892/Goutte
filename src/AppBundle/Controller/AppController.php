<?php

namespace AppBundle\Controller;

// Symfony lib
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\Query;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

// Goutte lib
use Goutte\Client;

// Guzzle lib
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\RejectedPromise;

class AppController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $query = new Query();
        $guzzleClient = new GuzzleClient();
        $promises = [];
        $totalResult = null;


        // Fetch config parameters
        $config = $this->getParameter('app.config');
        if (!isset($config['sites'])) {
            return $this->render('AppBundle:Default:error.html.twig');
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

        if (isset($postData['form']['search'])) {
            $searchQuery = $postData['form']['search'];
        } else {
            $searchQuery = null;
        }


        $processRequest = function ($url) use ($guzzleClient) {
            return Promise\coroutine(
                function () use ($guzzleClient, $url) {
                    try {
                        $value = (yield $guzzleClient->getAsync($url));
                    } catch (\Exception $e) {
                        yield New RejectedPromise($e->getMessage());
                    }
                }
            );
        };


        // Create an array of promises to execute later
        if (count($postData) > 0) {
            foreach ($config['sites'] as $site) {
                if ($site['searchType'] === 'urlQuery') {
                    $queryEncoded = urlencode($searchQuery);
                    $url = $site['parseUrl'].$queryEncoded;
                    $promises[] = $processRequest(htmlentities($url));
                } else {
                    $promises[] = $processRequest($site['parseUrl']);
                }
            }
        }


        // Promise handling and parsing
        $aggregate = Promise\all($promises)->then(
            // Fullfilled promise
            function ($values) use ($totalResult, $searchQuery, $config) {

                foreach ($values as $i => $value) {
                    $resBody = $value->getBody()->getContents();
                    $data = $this->parseRequest($resBody, $searchQuery, $config['sites'][$i]);

                    // Remove filtered results
                    foreach ($data as $key => $row) {
                        if ($row === null) {
                            unset($data[$key]);
                        }
                    }
                    $dataFinal = array_values($data);

                    // Add the site data to the result array
                    $result = array(
                        'siteName' => $config['sites'][$i]['name'],
                        'baseUrl' => $config['sites'][$i]['baseUrl'],
                        'data' => $dataFinal,
                        'dataCount' => count($data)
                    );
                    $totalResult[] = $result;
                }

                return $totalResult;
            },
            // Rejected promise
            function ($values) use ($totalResult) {
                var_dump('An error occured :'. $values);
                return $totalResult = null;
            }
        );

        // Execute the promises
        $totalResult = $aggregate->wait();

        return $this->render('AppBundle:Default:index.html.twig', array(
            'results' => $totalResult,
            'form' => $searchForm->createView()
        ));
    }

    /**
     * Parse the request
     *
     * @param $resBody
     * @param $searchQuery
     * @param $siteConfig
     *
     * @return array
     */
    private function parseRequest($resBody, $searchQuery, $siteConfig)
    {
        $parseUrl = $siteConfig['parseUrl'];
        $crawler = new Crawler($resBody, $parseUrl);
        $client = new Client();



        if ($siteConfig['searchType'] === 'formQuery') {
            $form = $crawler->filter($siteConfig['formNode'])->first()->form();

            // Create the form inputs array
            $formArray = array(
                $siteConfig['inputKey'] => $searchQuery
            );
            if (count($siteConfig['formInputs']) > 0) {
                $formArray = array_merge($formArray, $siteConfig['formInputs']);
            }

            $crawler = $client->submit($form, $formArray);
        }



        $data = $crawler->filter($siteConfig['mainNode'])->each(function ($node, $i) use ($searchQuery, $siteConfig) {

            $titleNode = $siteConfig['titleNode']['value'];
            $priceNode = $siteConfig['priceNode']['value'];
            $urlNode = $siteConfig['urlNode']['value'];
            $imageNode = $siteConfig['imageNode']['value'];

            // title handling
            if ($siteConfig['titleNode']['type'] === "innerHTML") {
                $name = $node->filter($titleNode)->text();
            } else {
                $name = $node->filter($titleNode)->attr($siteConfig['titleNode']['type']);
            }

            // price handling
            if ($siteConfig['priceNode']['type'] === 'innerHTML') {
                $price = $node->filter($priceNode)->text();
            } else {
                $price = $node->filter($titleNode)->attr($siteConfig['priceNode']['type']);
            }

            // url handling
            $urlFetched = $node->filter($urlNode)->attr('href');
            switch ($siteConfig['urlNode']['type']) {
                case 'relative':
                    $url = $siteConfig['baseUrl'] . trim($urlFetched);
                    break;
                case 'absolute':
                    $url = trim($urlFetched);
                    break;
                default:
                    $url = trim($urlFetched);
            }

            // image handling
            $imageFetched = $node->filter($imageNode)->attr('src');
            switch ($siteConfig['imageNode']['type']) {
                case 'relative':
                    $image = $siteConfig['baseUrl'] . trim($imageFetched);
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

            $filterCondition = $this->isValidData($searchQuery, $data);
            if ($filterCondition === true) {
                return $data;
            } else {
                return null;
            }
        });

        return $data;

    }


    /**
     * Check if the data returned is valid with the initial search query
     *
     * @param $search
     * @param $data
     *
     * @return bool
     */
    private function isValidData($search, $data)
    {
        // Create an array of the search words
        //$searchKeywords = array();
        $trimmed = trim($search);
        $searchKeywords = explode(' ', $trimmed);

        // Filter data
        $string = trim($data['name']);
        foreach ($searchKeywords as $keyword) {
            if (stripos($string, $keyword) !== false) {
                return true;
            }

        }
        return false;
    }

}
