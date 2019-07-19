<?php


namespace CrawlerBundle\Division;


use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Department
{
    /** @var string */
    private $url;
    /** @var array */
    private $departments;
    /** @var array  */
    private $categories_link;
    /** @var integer */
    private $noOfCategories;

    public function __construct($url)
    {
        $this->url = $url;
        $this->setDepartment();
    }

    public function setDepartment()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->url);
        $this->extractLinks($crawler);
        $this->extractNames($crawler);
        $this->noOfCategories = count($this->categories_link);
    }

    private function extractLinks($crawler)
    {
        $this->categories_link = $crawler
            ->filterXPath('//ul[contains(@class, "Products--5to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (DomCrawler $crawler) {
                return $crawler
                    ->filter('a')
                    ->attr('href');
            });
    }

    private function extractNames($crawler)
    {
        $this->departments = $crawler
            ->filterXPath('//ul[contains(@class, "ProductsMenu")]')
            ->filterXPath('//li[contains(@class, "ProductsMenu-item")]')
            ->each(function (DomCrawler $crawler) {
                return $crawler
                    ->filter('a')
                    ->extract(['_text'])[0];
            });
    }

    /** @return integer */
    protected function getSize()
    {
        return $this->noOfCategories;
    }

    /**
     * @return string
     * @param $number
     */
    public function getDepartment($number)
    {
        return $this->departments[$number];
    }

    /** @return array */
    public function getDepartments()
    {
        return $this->departments;
    }

    /**
     * @return string
     *@param $number
     */
    public function getLink($number)
    {
        return $this->categories_link[$number];
    }

    /** @return array */
    public function getLinks()
    {
        return $this->categories_link;
    }
}