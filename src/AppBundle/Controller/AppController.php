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
        if (!isset($config['sites']))
        {
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
        if (isset($postData['form']['ean']) && $postData['form']['ean'] !== '')
        {
            $searchQuery = $postData['form']['ean'];
            $useEAN = true;
        }
        if (isset($postData['form']['search']) && $postData['form']['search'] !== '')
        {
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
                        yield $guzzleClient->getAsync($url);
                    } catch (\Exception $e) {
                        yield New RejectedPromise($e->getMessage());
                    }
                }
            );
        };


        // Create an array of promises to execute later
        if (count($postData) > 0)
        {
            foreach ($config['sites'] as $site)
            {

                if ($useEAN === false)
                {
                    if ($site['searchType'] === 'urlQuery')
                    {
                        $queryEncoded = urlencode($searchQuery);
                        $url = $site['parseUrl'].$queryEncoded;
                        $promises[] = $processRequest($url);
                    }
                    else
                    {
                        $promises[] = $processRequest($site['parseUrl']);
                    }
                }
                else
                {
                    if ($site['EAN'] === true)
                    {
                        if ($site['searchType'] === 'urlQuery')
                        {
                            $queryEncoded = urlencode($searchQuery);
                            $url = $site['parseUrl'].$queryEncoded;
                            $promises[] = $processRequest($url);
                        }
                        else
                        {
                            $promises[] = $processRequest($site['parseUrl']);
                        }
                    }
                }

            }
        }

        // Promise handling and parsing
        $aggregate = Promise\all($promises)->then(
            // Fullfilled promise
            function ($values) use ($totalResult, $searchQuery, $config, $useEAN)
            {

                foreach ($values as $i => $value)
                {
                    // Get body response
                    $htmlResult = $value->getBody()->getContents();

                    // Parse the content of the page
                    $data = $this->parseHtml($htmlResult, $searchQuery, $config['sites'][$i], $useEAN);

                    // Remove filtered results
                    foreach ($data as $key => $row)
                    {
                        if ($row === null)
                        {
                            unset($data[$key]);
                        }
                    }
                    $dataFinal = array_values($data);


                    // Result array
                    $result = array(
                        'logo' => $config['sites'][$i]['logo'],
                        'data' => $dataFinal,
                        'dataCount' => count($data)
                    );

                    if ($config['sites'][$i]['EAN'] === true && $useEAN === true)
                    {
                        if (count($data) > 0)
                        {
                            $totalResult[] = $result;
                        }
                    }
                    if($useEAN === false)
                    {
                        if (count($data) > 0)
                        {
                            $totalResult[] = $result;
                        }
                    }
                }

                return $totalResult;
            },
            // Rejected promise
            function ($values) use ($totalResult)
            {
                // TODO : clean error message
                var_dump('An error occured :'. $values);
                return $totalResult = null;
            }
        );

        // Execute the promises
        $totalResult = $aggregate->wait();



        // Create an array with all information from the available sites
        foreach ($config['sites'] as $oneSite)
        {
            //$eanCompatible = ($oneSite['EAN'] === true) ? 'true' : 'false';

            // Site info array
            $oneSiteInfo = array(
                'siteName' => $oneSite['name'],
                'logo' => $oneSite['logo'],
                'language' => $oneSite['language'],
                'ean' => $oneSite['EAN'],
                'baseUrl' => $oneSite['baseUrl']
            );
            $allSitesInfo[] = $oneSiteInfo;
        }



        // Feedback if no result found
        if (isset($searchQuery) && $totalResult === null)
        {
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
     * Parse the request
     *
     * @param $htmlResult
     * @param $searchQuery
     * @param $siteConfig
     * @param $useEAN
     *
     * @return array
     */
    protected function parseHtml($htmlResult, $searchQuery, $siteConfig, $useEAN)
    {
        $parseUrl = $siteConfig['parseUrl'];
        $crawler = new Crawler($htmlResult, $parseUrl);
        $client = new Client();

        if ($siteConfig['searchType'] === 'formQuery')
        {
            $form = $crawler->filter($siteConfig['formID'])->first()->form();

            // Create the form inputs array
            $formArray = array(
                $siteConfig['inputKey'] => $searchQuery
            );

            if (count($siteConfig['formInputs']) > 0)
            {
                $formArray = array_merge($formArray, $siteConfig['formInputs']);
            }

            $crawler = $client->submit($form, $formArray);
        }



        $data = $crawler->filter($siteConfig['mainNode'])->each(function ($node, $i) use ($searchQuery, $siteConfig, $useEAN)
        {
            $titleNode = $siteConfig['titleNode']['value'];
            $priceNode = $siteConfig['priceNode']['value'];
            $urlNode = $siteConfig['urlNode']['value'];
            $imageNode = $siteConfig['imageNode']['value'];

            // title handling
            if ($siteConfig['titleNode']['type'] === "innerHTML")
            {
                $name = $node->filter($titleNode)->text();
            }
            else
            {
                $name = $node->filter($titleNode)->attr($siteConfig['titleNode']['type']);
            }

            // price handling
            if ($siteConfig['priceNode']['type'] === 'innerHTML')
            {
                $price = $node->filter($priceNode)->text();
            }
            else
            {
                $price = $node->filter($titleNode)->attr($siteConfig['priceNode']['type']);
            }

            // url handling
            $urlFetched = $node->filter($urlNode)->attr('href');
            switch ($siteConfig['urlNode']['type'])
            {
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
            switch ($siteConfig['imageNode']['type'])
            {
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

            $filterCondition = $this->isValidData($searchQuery, $data, $useEAN);

            if ($filterCondition === true)
            {
                return $data;
            }
            else
            {
                return null;
            }

        });

        return $data;
    }

    /**
     * Check if the data returned is valid with the initial search query
     * EAN queries look for valid search query (13 digits format)
     *
     * @param string $search
     * @param array $data
     * @param bool $useEAN
     *
     * @return bool
     */
    protected function isValidData($search, $data, $useEAN)
    {
        // Create an array of the search words
        $trimmed = trim($search);
        $searchKeywords = explode(' ', $trimmed);
        $checkCount = count($searchKeywords);


        if ($useEAN)
        {
            // Regular expression
            $regEx = '/';
            $regEx.= '\b(?:\d{13})\b';
            $regEx.= '/';

            // Filter data
            $isValid = preg_match($regEx, $search, $matches);
        }
        else
        {
            $str = $data['name'];
            $hist = array();

            // count how many each words are present in the article name (for debug)
            foreach (preg_split('/\s+/', $str) as $word)
            {
                $word = strtolower(utf8_decode($word));

                if (isset($hist[$word]))
                {
                    $hist[$word]++;
                }
                else
                {
                    $hist[$word] = 1;
                }
            }

            // Create a list of the present words
            $keys = array_keys($hist);

            // Create a string with a single one of each present words
            $strEachWord = '';
            foreach ($keys as $word)
            {
                $strEachWord.= $word;
                $strEachWord.= ' ';
            }

            // Regular expression
            $regEx = '/';
            $i = 0;
            foreach ($searchKeywords as $word)
            {
                $i++;
                $regEx.= '\b'.$word;
                if ($i != $checkCount) {
                    $regEx.= '|';
                }
            }
            $regEx.= '/ i';

            // Filter data
            $isValid = preg_match_all($regEx, $strEachWord, $matches);
        }

        // Handle return response
        if ($isValid >= $checkCount)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

}