<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Query;
use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

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
        $searchQuery = null;
        $useEAN = null;
        $totalResult = null;
        $allSitesInfo = null;
        $error = null;


        // Fetch config parameters
        $config = $this->getParameter('app.config');
        if (!isset($config['sites'])) {
            return $this->render('AppBundle:Default:error.html.twig');
        }


        // Search form
        $searchForm = $this->createFormBuilder($query)
            ->add('ean', TextType::class, array(
                'attr' => array(
                    'placeholder' => 'EAN (si supporté)',
                    'minlength' => 13,
                    'maxlength' => 13
                ),
                'required' => false,
                'empty_data' => null,
                'label' => false
            ))
            ->add('search', TextType::class, array(
                'attr' => array(
                    'placeholder' => 'Article',
                ),
                'required' => false,
                'empty_data' => null,
                'label' => false
            ))
            ->add('save', SubmitType::class, array('label' => 'Search'))
            ->getForm();

        // Fetch the data from the search form
        $postData = $request->request->all();

        // Save the query in a variable we can use later
        if (isset($postData['form']['ean']) && $postData['form']['ean'] !== '') {
            $searchQuery = $postData['form']['ean'];
            $useEAN = true;
        }
        if (isset($postData['form']['search']) && $postData['form']['search'] !== '') {
            $searchQuery = $postData['form']['search'];
            $useEAN = false;
        }


        /**
         * do an http request using Guzzle library
         *
         * @param $url
         * @return Promise\Promise
         */
        $processRequest = function ($url) use ($guzzleClient) {
            return Promise\coroutine(
                function () use ($guzzleClient, $url) {
                    try {
                        yield $guzzleClient->requestAsync('GET', $url, [
                            'curl' => [
                                [CURLOPT_FOLLOWLOCATION => true]
                            ]
                        ]);
                    } catch (\Exception $e) {
                        yield New RejectedPromise($e->getMessage());
                    }
                }
            );
        };


        // Create an array of promises to execute later
        if (count($postData) > 0) {
            foreach ($config['sites'] as $site) {

                if ($site['isFinished'] === true) {
                    if ($useEAN === false) {
                        if ($site['searchType'] === 'urlQuery') {
                            $queryEncoded = urlencode($searchQuery);
                            $url = $site['parseUrl'] . $queryEncoded;
                            $promises[] = $processRequest($url);
                        } else {
                            $promises[] = $processRequest($site['parseUrl']);
                        }
                    } else {
                        if ($site['EAN'] === true) {
                            if ($site['searchType'] === 'urlQuery') {
                                $queryEncoded = urlencode($searchQuery);
                                $url = $site['parseUrl'] . $queryEncoded;
                                $promises[] = $processRequest($url);
                            } else {
                                $promises[] = $processRequest($site['parseUrl']);
                            }
                        }
                    }
                }
            }
        }

        // Promise 1 : parse and get all items
        $aggregate = Promise\all($promises)->then(
        // if Fullfilled promise (1)
            function ($values) use ($totalResult, $searchQuery, $config, $useEAN, $processRequest) {

                foreach ($values as $keySite => $value) {
                    // Get response body
                    $htmlResult = $value->getBody()->getContents();

                    // Parse the page content
                    $data = $this->parseArticles($htmlResult, $searchQuery, $config['sites'][$keySite], $useEAN);

                    // Remove filtered results
                    foreach ($data as $keyResult => $valueResult) {
                        if ($valueResult === null) {
                            unset($data[$keyResult]);
                        }
                    }

                    // Reindex the data array)
                    $dataFinal = array_values($data);


                    // Promise 2 : get article details
                    $detailsPromises = [];
                    $resultDetails = [];
                    foreach ($dataFinal as $item) {
                        // only executed if the config is correctly filled
                        if ($config['sites'][$keySite]['detailPage']['bigImageNode'] !== '') {
                            $detailsPromises[] = $processRequest($item['url']);
                        }
                    }
                    $aggregateDetails = Promise\all($detailsPromises)->then(
                    // if Fullfilled promise (2)
                        function ($values) use ($resultDetails, $config, $keySite) {

                            foreach ($values as $value) {
                                // Get response body
                                $htmlResult = $value->getBody()->getContents();

                                // Parse the page content
                                $resultDetails[] = $this->parseDetails($htmlResult, $config['sites'][$keySite]);
                            }

                            return $resultDetails;
                        },
                        // if Rejected promise (2)
                        function ($reason) {
                            echo "An error occured (article details page) : " . $reason;
                        }
                    );
                    $details = $aggregateDetails->wait();

                    // Send the details data into the article data
                    if ($config['sites'][$keySite]['detailPage']['bigImageNode'] !== '') {
                        for ($i = 0; $i < count($dataFinal); $i++) {
                            if (isset($config['sites'][$keySite]['detailPage']['eanNode'])) {
                                $dataFinal[$i]['ean'] = $details[$i]['ean'];
                            }

                            if ($config['sites'][$keySite]['imageNode']['type'] === 'absolute') {
                                $dataFinal[$i]['big_image'] = $details[$i]['big_image'];
                            } else {
                                $dataFinal[$i]['big_image'] = $config['sites'][$keySite]['baseUrl'] . $details[$i]['big_image'];
                            }
                        }
                    }
                    //dump($dataFinal);

                    // Result array
                    $result = array(
                        'logo' => $config['sites'][$keySite]['logo'],
                        'data' => $dataFinal,
                        'big_image' => $details,
                        'dataCount' => count($data),
                    );


                    // Send site results into the global results array
                    if (count($data) > 0) {
                        $totalResult[] = $result;
                    }
                }

                return $totalResult;
            },
            // if Rejected promise (1)
            function ($reason) use ($totalResult) {
                echo "An error occured (article query page) : " . $reason;
            }
        );

        // Execute the promises
        $totalResult = $aggregate->wait();


        // Create an array with all information from the available sites
        foreach ($config['sites'] as $oneSite) {
            $oneSiteInfo = array(
                'siteName' => $oneSite['name'],
                'isFinished' => $oneSite['isFinished'],
                'logo' => $oneSite['logo'],
                'language' => $oneSite['language'],
                'ean' => $oneSite['EAN'],
                'baseUrl' => $oneSite['baseUrl']
            );
            $allSitesInfo[] = $oneSiteInfo;
        }


        // Feedback if no result found
        if (isset($searchQuery) && $totalResult === null) {
            $error = 'Pas de résultats trouvés.';
        }


        // Render the results
        return $this->render('AppBundle:Default:index.html.twig', array(
            'searchQuery' => $searchQuery,
            'results' => $totalResult,
            'sites' => $allSitesInfo,
            'form' => $searchForm->createView(),
            'error' => $error
        ));
    }

    /**
     * Parse the article result page from the initial query
     *
     * @param $htmlResult
     * @param $searchQuery
     * @param $siteConfig
     * @param $useEAN
     *
     * @return array
     */
    protected function parseArticles($htmlResult, $searchQuery, $siteConfig, $useEAN)
    {
        $parseUrl = $siteConfig['parseUrl'];
        $crawler = new Crawler($htmlResult, $parseUrl);
        $client = new Client();

        if ($siteConfig['searchType'] === 'formQuery') {
            $form = $crawler->filter($siteConfig['formID'])->first()->form();

            // Create the form inputs array
            $formArray = array(
                $siteConfig['inputKey'] => $searchQuery
            );

            if (count($siteConfig['formInputs']) > 0) {
                $formArray = array_merge($formArray, $siteConfig['formInputs']);
            }

            $crawler = $client->submit($form, $formArray);
        }


        $data = $crawler->filter($siteConfig['mainNode'])->each(function ($node, $i) use ($searchQuery, $siteConfig, $useEAN) {
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
                'big_image' => null,
            );

            $filterCondition = $this->isValidData($data, $searchQuery, $useEAN);

            if ($filterCondition === true) {
                return $data;
            } else {
                return null;
            }

        });

        return $data;
    }

    /**
     * Parse the details of a article page
     *
     * @param $htmlResult
     * @param $config
     *
     * @return null|string
     */
    protected function parseDetails($htmlResult, $config)
    {
        $crawler = new Crawler($htmlResult, $config['parseUrl']);
        $client = new Client();
        $ean = null;

        $bigImage = $crawler->filter($config['detailPage']['bigImageNode'])->attr('src');

        if (isset($config['detailPage']['eanNode'])) {
            //$ean = $crawler->filterXPath('(//meta');
            //dump($ean);
        }

        $data = array(
            'big_image' => $bigImage,
            'ean' => $ean
        );

        return $data;
    }

    /**
     * Check if the data returned is valid with the initial search query
     * EAN queries look for valid search query (13 digits format)
     * Regular queries look for valid article name in comparaison to the searched query
     *
     * @param string $search
     * @param array $data
     * @param bool $useEAN
     *
     * @return bool
     */
    protected function isValidData($data, $search, $useEAN)
    {
        // Create an array of the search words
        $trimmed = trim($search);
        $searchKeywords = explode(' ', $trimmed);
        $checkCount = count($searchKeywords);


        if ($useEAN) {
            // Regular expression
            $regEx = '/';
            $regEx .= '\b(?:\d{13})\b';
            $regEx .= '/';

            // Filter data
            $isValid = preg_match($regEx, $search, $matches);
        } else {
            $str = $data['name'];
            $hist = array();

            // count how many each words are present in the article name (for debug)
            foreach (preg_split('/\s+/', $str) as $word) {
                $word = strtolower(utf8_decode($word));

                if (isset($hist[$word])) {
                    $hist[$word]++;
                } else {
                    $hist[$word] = 1;
                }
            }

            // Create a list of the present words
            $keys = array_keys($hist);

            // Create a string with a single one of each present words
            $strEachWord = '';
            foreach ($keys as $word) {
                $strEachWord .= $word;
                $strEachWord .= ' ';
            }

            // Regular expression
            $regEx = '/';
            $i = 0;
            foreach ($searchKeywords as $word) {
                $i++;
                $regEx .= '\b' . $word;
                if ($i != $checkCount) {
                    $regEx .= '|';
                }
            }
            $regEx .= '/ i';

            // Filter data
            $isValid = preg_match_all($regEx, $strEachWord, $matches);
        }

        // Handle return response
        if ($isValid >= $checkCount) {
            return true;
        } else {
            return false;
        }
    }
}