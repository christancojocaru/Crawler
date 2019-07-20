<?php


namespace CrawlerBundle\Division;


use Goutte\Client;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Category extends Department
{
    /** @var string */
    private $url;
    /** @var array */
    private $categories;
    /** @var array */
    private $sub_categories_link;
    /** @var integer */
    private $noOfSubCategories;
    /** @var array */
    private $products = [];

    public function __construct($url, $number)
    {
        parent::__construct($url);
        $size = parent::getSize();
        if ( $number > $size ) {
            throw new Exception(
                sprintf(
                    "Category with number %s is incorrect. Please select a number between 1 and %s",
                    $number + 1, $size
                ),
                $size
            );
        }
        $this->url = parent::getLink($number);
        $this->setCategory();
    }

    public function setCategory()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->url);
        $this->categories = $this->extractNames($crawler);
        if ($this->checkSubCategory($crawler)) {
            $this->sub_categories_link = $this->extractLinks($crawler);
            $this->noOfSubCategories = count($this->sub_categories_link);
        }else {
            $products = $this->extractProducts($crawler);
            foreach ($products as $product) {
                $this->products[] = $this->setProduct($product);
                break;
            }
        }
    }

    protected function extractLinks($crawler)
    {
        return $crawler
            ->filterXPath('//ul[contains(@class, "Products--5to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (DomCrawler $crawler) {
                return $crawler
                    ->filter('a')
                    ->attr('href');
            });
    }

    protected function extractNames($crawler)
    {
        return $crawler
            ->filterXPath('//ul[contains(@class, "Products--5to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (DomCrawler $crawler) {
                return $crawler
                    ->filter('a > h2')
                    ->extract(['_text']);
            });
    }


    protected function setProduct($product)
    {
        $newProduct = new Product();
        $newProduct->setData($product[0]);
        $newProduct->setPrice(floatval($product[1]));
        return $newProduct;
    }

    protected function extractProducts($crawler)
    {
        return $crawler
            ->filterXPath('//ul[contains(@class, "Products--4to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (DomCrawler $crawler) {
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
                return [$name[0], $price[0]];
            });
    }

    protected function checkSubCategory($crawler)
    {
        $children = $crawler
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

    /** @return integer */
    protected function getSize()
    {
        return $this->noOfSubCategories;
    }

    /**
     * @return string
     * @param $number
     */
    public function getCategory($number)
    {
        return $this->categories[$number];
    }

    /** @return array */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @return string
     *@param $number
     */
    public function getLink($number)
    {
        return $this->sub_categories_link[$number];
    }

    public function getParentLinks()
    {
        return parent::getLinks();
    }

    /** @return array */
    public function getLinks()
    {
        return $this->sub_categories_link;
    }

    /** @return array */
    public function getProducts()
    {
        return $this->products;
    }
}