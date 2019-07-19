<?php


namespace CrawlerBundle\Division;


use Goutte\Client;
use Symfony\Component\Config\Definition\Exception\Exception;

class SubCategory extends Category
{
    /** @var string */
    private $url;
    /** @var array */
    private $subCategories;
    /** @var array */
    private $segments_link;
    /** @var integer */
    private $noOfSegments;
    /** @var array */
    private $products = [];

    public function __construct($url, $categoryNo, $subCategoryNo)
    {
        parent::__construct($url, $categoryNo);
        $size = parent::getSize();
        if ( $subCategoryNo > $size ) {
            throw new Exception(
                sprintf(
                    "SubCategory with number %s is incorrect. Please select a number between 1 and %s",
                    $subCategoryNo + 1,
                    $size
                ),
                $size
            );
        }
        $this->url = parent::getLink($subCategoryNo);
        $this->setSubCategory();
    }

    public function setSubCategory()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->url);
        if ($this->checkSubCategory($crawler)) {
            $this->segments_link = $this->extractLinks($crawler);
            $this->subCategories = $this->extractNames($crawler);
            $this->noOfSegments = count($this->segments_link);
        }else {
            $products = $this->extractProduct($crawler);
            foreach ($products as $product) {
                $this->products[] = $this->setProduct($product);
            }
            var_dump($this->products);die;
        }
    }

    /** @return integer */
    protected function getSize()
    {
        return $this->noOfSegments;
    }

    /**
     * @return string
     * @param $number
     */
    public function getSubCategory($number)
    {
        return $this->subCategories[$number];
    }

    /** @return array */
    public function getSubCategories()
    {
        return $this->subCategories;
    }

    /**
     * @return string
     *@param $number
     */
    public function getLink($number)
    {
        return $this->segments_link[$number];
    }

    /** @return array */
    public function getLinks()
    {
        return $this->segments_link;
    }
}