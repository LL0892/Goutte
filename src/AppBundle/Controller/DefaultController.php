<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\Query;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Goutte\Client;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $client = new Client();
        $searchQuery = 'PANASONIC DMC-LX100';
        

        /*
         * HEINIGERAG.CH
         */
        $baseUrl = 'http://shop.heinigerag.ch/';
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
            'baseUrl' => $baseUrl,
            'data' => $data,
            'dataCount' => count($data)
        );
        $totalResult[] = $result;


        /*
         * MELECTRONICS.CH
         */
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

        /*
         * HAWK.CH
         */
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
        $totalResult[] = $result;
        
        //dump($totalResult);
        //print $crawler->html();
        //dump($form);
        //dump($crawler);
        //dump($client->getResponse()->getContent());
        //exit;

        return $this->render('AppBundle:Default:index.html.twig', array(
            'results' => $totalResult,
            'form' => $form
        ));
    }
}
