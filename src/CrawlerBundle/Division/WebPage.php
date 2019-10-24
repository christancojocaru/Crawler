<?php


namespace CrawlerBundle\Division;


use CrawlerBundle\Model\Product;
use CrawlerBundle\Traits\DivisionTrait;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class WebPage
{
    use DivisionTrait;

    /** @var array */
    protected $divisions;
    /** @var array */
    protected $divisions_link;
    /** @var array  */
    protected $sub_divisions;
    /** @var array  */
    protected $sub_divisions_link;
    /** @var integer */
    protected $noOfSubDivisions;
    /** @var object */
    protected $crawler;
    /** @var array */
    protected $products = [];

    public function __construct($url)
    {
        $client = new Client();
        $this->crawler = $client->request('GET', $url);
    }

    public function extractDepartmentsLink()
    {
        $datas = $this->crawler
            ->filterXPath('//ul[contains(@class, "ProductsMenu")]')
            ->filterXPath('//li[contains(@class, "ProductsMenu-item")]')
            ->each(function (Crawler $crawler) {
                return $crawler
                    ->filter('a')
                    ->extract(['href', '_text'])[0];//make nested arrays otherwise
            });
        $response = [];
        foreach ($datas as $data) {
            $this->setDepartmentsName($data[1]);
            $response[] = $data[0];
        }
        return $response;
    }

    private function setDepartmentsName($name)
    {
        $this->sub_divisions[] = $name;
    }

    protected function setSubDivisionsNameAndLink()
    {
        $results = $this->crawler
            ->filterXPath('//ul[contains(@class, "Products--5to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (Crawler $crawler) {
                $name = $crawler
                    ->filter('a > h2')
                    ->extract(["_text"])[0];
                $link = $crawler
                    ->filter('a')
                    ->attr('href');
                return [$name, $link];
            });
        foreach ($results as $result) {
            $this->sub_divisions[] = $result[0];
            $this->sub_divisions_link[] = $result[1];
        }
    }

    public function extractProducts()
    {
        $products = $this->crawler
            ->filterXPath('//ul[contains(@class, "Products--4to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (Crawler $crawler) {
                $price = $crawler
                    ->filterXPath('//meta')
                    ->reduce(function ($node, $i) {
                        return ($i % 2) == 0;
                    })
                    ->extract(['content']);
                $name = $crawler
                    ->filterXPath('//h2')
                    ->reduce(function ($node, $i) {
                        return ($i % 2) == 0;
                    })
                    ->extract(['_text']);
                $image = $crawler
                    ->filter("img")
                    ->extract(["src"]);
                //all variable are array, that's why [0]
                return [$name[0], $price[0], $image[0]];
            });
        foreach ($products as $product) {
            $this->setProduct($product);
        }
    }

    protected function checkSubCategory()
    {
        $children = $this->crawler
            ->filterXPath('//ul[contains(@class, "Products")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->first()
            ->children()
            ->nodeName();
        if ( $children == 'a' ) {
            return True;
        }else{
            return False;
        }
    }

    private function setProduct($product)
    {
        $newProduct = new Product();
        $newProduct->setData($product[0]);
        $newProduct->setPrice(floatval($product[1]));
        $newProduct->setImage($product[2]);
        $this->products[] = $newProduct;
    }
}