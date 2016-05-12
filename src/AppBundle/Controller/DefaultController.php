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
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Pool;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $client = new Client();
        $guzzleClient = new GuzzleClient();
        $query = new Query();
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

        if (count($postData) > 0) {

            foreach ($config['sites'] as $site) {

                $parseUrl = $site['parseUrl'];
                $guzzleResponse = $guzzleClient->get($parseUrl);
                $crawler = new Crawler($guzzleResponse->getBody()->getContents(), $parseUrl);

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

                $data = $crawler->filter($site['mainNode'])->each(function ($node, $i) use ($searchQuery, $site) {

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

                    $filterCondition = $this->isValidData($searchQuery, $data);
                    if ($filterCondition === true) {
                        return $data;
                    } else {
                        return null;
                    }
                });

                // Remove filtered results
                foreach ($data as $key => $row) {
                    if ($row === null) {
                        unset($data[$key]);
                    }
                }
                $dataFinal = array_values($data);

                $result = array(
                    'siteName' => $site['name'],
                    'baseUrl' => $parseUrl,
                    'data' => $dataFinal,
                    'dataCount' => count($data)
                );
                $totalResult[] = $result;
            }

            if ($config['debug'] === true) {
                dump($totalResult);
                exit;
            }

            ///dump($totalResult);
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

    /**
     * @Route("/multicurl", name="multicurl")
     */
    public function multicurlAction()
    {

        $data = array(
            'http://shop.heinigerag.ch/',
            'http://www.melectronics.ch/fr/',
            'http://www.hawk.ch/',
            'http://www.steg-electronics.ch/fr/'
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

    /**
     * @Route("/async", name="async")
     */
    public function asyncAction()
    {
        $client = new GuzzleClient();
        $res = $client->request('GET', 'https://api.github.com/user', [
            'auth' => ['LL0892', 'L!3nH3r?']
        ]);
        dump($res->getStatusCode());
        dump($res->getHeaders());
        dump($res->getBody()->getContents());

        // Send an asynchronous request.
        $request = new GuzzleRequest('GET', 'http://httpbin.org');
        $promise = $client->sendAsync($request)->then(function ($response) {
            dump($response->getBody()->getContents());
        });
        $promise->wait();

        exit;
    }

    /**
     * @Route ("/promise", name="promise")
     */
    public function promiseAction()
    {
        /* test 1 (promises) */
        //$client = new GuzzleClient();
        //$promise = $client->requestAsync('GET', 'http://httpbin.org/get');
        //$promise->then(function ($res) {
        //    return $res->getStatusCode();
        //})->then(function ($value) {
        //    echo "j'ai recu un code $value";
        //});
        //$promise->wait();

        /* test 2 (batch) */
        //$client = new GuzzleClient(['base_uri' => 'http://httpbin.org/']);
        //$batch = [
        //    'image' => '/image',
        //    'png' => '/image/png',
        //    'jpeg' => '/image/jpeg',
        //    'webp' => '/image/webp'
        //];
        //$requests = function ($batch) {
        //    foreach ($batch as $url) {
        //        yield new GuzzleRequest('GET', $url);
        //    }
        //};
        //$pool = new Pool($client, $requests($batch), [
        //    'fulfilled' => function ($response, $index) {
        //        dump($index);
        //    },
        //    'concurrency' => 2,
        //]);
        //$promise = $pool->promise();
        //$promise->wait();

        /* test3 (first promise) */
        //$client = new GuzzleClient(['base_uri' => 'http://httpbin.org/']);
        //$promises = [
        //    'image' => $client->getAsync('/image'),
        //    'png' => $client->getAsync('/image/png'),
        //    'jpeg' => $client->getAsync('/image/jpeg'),
        //    'webp' => $client->getAsync('/image/webp')
        //];
        //$result = Promise\any($promises)->then(function ($value) {
        //    dump($value->getHeader('Content-Type'));
        //});
        //$result->wait();

        /* test 4 (first two promises) */
        //$client = new GuzzleClient(['base_uri' => 'http://httpbin.org/']);
        //$promises = [
        //    'image' => $client->getAsync('/image'),
        //    'png' => $client->getAsync('/image/png'),
        //    'jpeg' => $client->getAsync('/image/jpeg'),
        //    'webp' => $client->getAsync('/image/webp')
        //];
        //$result = Promise\some(2, $promises)
        //    ->then(function ($results) {
        //        foreach ($results as $value) {
        //            dump($value->getHeader('Content-Type'));
        //        }
        //    });
        //$result->wait();

        /* test 5 (generator promise) */
        //$client = new GuzzleClient(['base_uri' => 'http://httpbin.org/']);
        //$promiseGenerator = function () use ($client) {
        //    yield $client->getAsync('/image');
        //    yield $client->getAsync('/image/png');
        //    yield $client->getAsync('/image/jpeg');
        //    yield $client->getAsync('/image/webp');
        //};
        //$result = array();
        //$promise = Promise\each_limit($promiseGenerator(), 2, function ($value, $idx) use (&$result) {
        //    $result[$idx] = $value;
        //});
        //$promise->wait();


        /* test 6 (coroutine promises) */
        $client = new GuzzleClient();
        $myfunction = function ($url) use ($client) {
            return Promise\coroutine(
                function () use ($client, $url) {
                    try {
                        $value = (yield $client->getAsync($url));
                    } catch (\Exception $e) {
                        yield New RejectedPromise($e->getMessage());
                    }
                }
            );
        };
        $sites = ['http://google.com', 'http://google.ch', 'http://google.de'];
        $promises = [];
        foreach ($sites as $site) {
            $promises[] = $myfunction($site);
        }
        $aggregate = Promise\all($promises)->then(
            function ($values) {
                dump($values);
            }, function ($values) {
                dump($values);
            });
        $aggregate->wait();

        exit;
    }

    /**
     * @Route ("/pool", name="pool")
     */
    public function poolAction()
    {
        exit;
    }
}
