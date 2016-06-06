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
        $promises2 = [];

        $config_fr = array(
            'sites' => null
        );
        $config_de = array(
            'sites' => null
        );

        $searchQuery = null;
        $useEAN = null;

        $allSitesInfo = null;
        $totalResult = null;
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

                            $index_de = $this->getIndexFromLocale('de', $site['language']);
                            $index_fr = $this->getIndexFromLocale('fr', $site['language']);

                            if ($index_de !== null) {
                                $url_de = $site['parseUrl'][$index_de] . $queryEncoded;
                                $promises[] = $processRequest($url_de);
                                $config_de['sites'][] = $site;

                            }
                            if ($index_fr !== null) {
                                $url_fr = $site['parseUrl'][$index_fr] . $queryEncoded;
                                $promises2[] = $processRequest($url_fr);
                                $config_fr['sites'][] = $site;
                            }
                        } else {
                            $url_de = $this->getIndexFromLocale('de', $site['language']);
                            $url_fr = $this->getIndexFromLocale('fr', $site['language']);
                            if ($url_de !== null) {
                                $promises[] = $processRequest($url_de);
                                $config_de['sites'][] = $site;
                            }
                            if ($url_fr !== null) {
                                $promises2[] = $processRequest($url_fr);
                                $config_fr['sites'][] = $site;
                            }
                        }
                    } else {
                        if ($site['EAN'] === true) {
                            if ($site['searchType'] === 'urlQuery') {
                                $queryEncoded = urlencode($searchQuery);

                                $index_de = $this->getIndexFromLocale('de', $site['language']);
                                $index_fr = $this->getIndexFromLocale('fr', $site['language']);

                                if ($index_de !== null) {
                                    $url_de = $site['parseUrl'][$index_de] . $queryEncoded;
                                    $promises[] = $processRequest($url_de);
                                    $config_de[] = $site;
                                }
                                if ($index_fr) {
                                    $url_fr = $site['parseUrl'][$index_fr] . $queryEncoded;
                                    $promises2[] = $processRequest($url_fr);
                                    $config_fr[] = $site;
                                }
                            } else {
                                $url_de = $this->getIndexFromLocale('de', $site['language']);
                                $url_fr = $this->getIndexFromLocale('fr', $site['language']);
                                if ($url_de !== null) {
                                    $promises[] = $processRequest($url_de);
                                    $config_de[] = $site;
                                }
                                if ($url_fr !== null) {
                                    $promises2[] = $processRequest($url_fr);
                                    $config_fr[] = $site;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Promise 1 : parse and get all items
        $aggregate = Promise\all($promises)->then(
        // if Fullfilled promise (1)
            function ($values) use ($totalResult, $searchQuery, $config_de, $useEAN, $processRequest) {

                foreach ($values as $keySite => $value) {
                    // Get response body
                    $htmlResult = $value->getBody()->getContents();

                    // Parse the page content
                    $data = $this->parseArticles($htmlResult, $searchQuery, $config_de['sites'][$keySite], $useEAN, 'de');
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
                        if ($config_de['sites'][$keySite]['detailPage']['bigImageNode'] !== '') {
                            $detailsPromises[] = $processRequest($item['url_de']);
                        }
                    }
                    $aggregateDetails = Promise\all($detailsPromises)->then(
                    // if Fullfilled promise (2)
                        function ($values) use ($resultDetails, $config_de, $keySite) {

                            foreach ($values as $value) {
                                // Get response body
                                $htmlResult = $value->getBody()->getContents();

                                // Parse the page content
                                $resultDetails[] = $this->parseDetails($htmlResult, $config_de['sites'][$keySite]);
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
                    if ($config_de['sites'][$keySite]['detailPage']['bigImageNode'] !== '') {
                        for ($i = 0; $i < count($dataFinal); $i++) {
                            if (isset($config_de['sites'][$keySite]['detailPage']['eanNode'])) {
                                $dataFinal[$i]['ean'] = $details[$i]['ean'];
                            }

                            if ($config_de['sites'][$keySite]['imageNode']['type'] === 'absolute') {
                                $dataFinal[$i]['big_image'] = $details[$i]['big_image'];
                            } else {
                                $dataFinal[$i]['big_image'] = $config_de['sites'][$keySite]['baseUrl'] . $details[$i]['big_image'];
                            }
                        }
                    }

                    // Result array
                    $result = array(
                        'name' => $config_de['sites'][$keySite]['name'],
                        'logo' => $config_de['sites'][$keySite]['logo'],
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
                echo "An error occured (article query page - DE) : " . $reason;
            }
        )->then(
            function ($values) use ($promises2, $searchQuery, $config_de, $config_fr, $useEAN) {

                $aggregate2 = Promise\all($promises2)->then(
                    function ($values) use ($searchQuery, $config_fr, $useEAN) {

                        $totalResultFr = [];
                        foreach ($values as $keySite => $value) {
                            // Get response body
                            $htmlResult = $value->getBody()->getContents();

                            // Parse the page content
                            $dataFr = $this->parseArticles($htmlResult, $searchQuery, $config_fr['sites'][$keySite], $useEAN, 'fr');
                            // Remove filtered results
                            foreach ($dataFr as $keyResult => $valueResult) {
                                if ($valueResult === null) {
                                    unset($dataFr[$keyResult]);
                                }
                            }

                            // Reindex the data array)
                            $dataFr = array_values($dataFr);

                            $dataArrayFr = array(
                                'name' => $config_fr['sites'][$keySite]['name'],
                                'data' => $dataFr,
                            );

                            // Send site results into the global results array
                            if (count($dataFr) > 0) {
                                $totalResultFr[] = $dataArrayFr;
                            }
                        }
                        return $totalResultFr;
                    },
                    function ($reason) {
                        echo "An error occured (article query page - FR) : " . $reason;
                    }
                );

                $resFr = $aggregate2->wait();


                // Create an array with all site names
                $siteNameArray = null;
                foreach ($config_de['sites'] as $site) {
                    $siteNameArray[] = $site['name'];
                }

                for ($i = 0; $i < count($config_fr); $i++) {
                    //dump($i);
                    //$index = $this->getIndexFromSiteName($config_fr['sites'][$i]['name'], $siteNameArray);
                }
                //dump($values);
                //dump($values);

                return $values;
            },
            function ($reason) {
                echo "An error occured (then) : " . $reason;
            }
        );

        // Execute the promises
        if (count($postData) > 0) {
            $totalResult = $aggregate->wait();
        }


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
    protected function parseArticles($htmlResult, $searchQuery, $siteConfig, $useEAN, $locale)
    {
        $parseUrl = $siteConfig['parseUrl'][0];
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


        $data = $crawler->filter($siteConfig['mainNode'])->each(function ($node, $i) use ($searchQuery, $siteConfig, $useEAN, $locale) {
            $titleNode = $siteConfig['titleNode']['value'];
            $priceNode = $siteConfig['priceNode']['value'];
            $urlNode = $siteConfig['urlNode']['value'];
            $imageNode = $siteConfig['imageNode']['value'];

            $url_fr = null;
            $name_fr = null;
            $url_de = null;
            $name_de = null;

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

            // apply info to the right language variables
            if ($locale === 'fr') {
                $name_fr = trim($name);
                $url_fr = $url;
            } else {
                $name_de = trim($name);
                $url_de = $url;
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
                'name_de' => $name_de,
                'name_fr' => $name_fr,
                'locale' => $locale,
                'url_de' => $url_de,
                'url_fr' => $url_fr,
                'price' => trim($price),
                'image' => $image,
                'big_image' => null,
            );

            $filterCondition = $this->isValidData($data, $searchQuery, $useEAN, $locale);

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
        $crawler = new Crawler($htmlResult, $config['parseUrl'][0]);
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
    protected function isValidData($data, $search, $useEAN, $locale)
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

            if ($locale === 'fr') {
                $str = $data['name_fr'];
            } else {
                $str = $data['name_de'];
            }
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

    /**
     * Get the current url to use with the current locale string
     *
     * @param string $locale
     * @param array $localArray
     * @param array $urlArray
     *
     * @return mixed|null
     */
    protected function getIndexFromLocale($locale, $localArray)
    {
        $index = array_search($locale, $localArray);
        if ($index !== false) {
            return $index;
        } else {
            return null;
        }
    }

    /**
     * Get the current site from a list of sites
     *
     * @param $siteName
     * @param $siteArray
     * @return mixed|null
     */
    protected function getIndexFromSiteName($siteName, $siteArray)
    {
        $index = array_search($siteName, $siteArray);
        if ($index !== false) {
            return $index;
        } else {
            return null;
        }
    }

    /**
     * @Route("/admin", name="admin")
     */
    public function adminAction()
    {
        return $this->render('@App/Default/admin.html.twig', array());
    }
}