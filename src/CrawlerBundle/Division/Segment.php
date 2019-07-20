<?php


namespace CrawlerBundle\Division;


use Goutte\Client;
use Symfony\Component\Config\Definition\Exception\Exception;

class Segment extends SubCategory
{
    /** @var string */
    private $url;
    /** @var array */
    private $segments;
    /** @var array */
    private $sub_segments_link;
    /** @var integer */
    private $noOfSubSegments;
    /** @var array */
    private $products = [];

    public function __construct($url, $categoryNo, $subCategoryNo, $segmentNo)
    {
        parent::__construct($url, $categoryNo, $subCategoryNo);
        $size = parent::getSize();
        if ( $segmentNo > $size ) {
            throw new Exception(
                sprintf(
                    "Segment with number %s is incorrect. Please select a number between 1 and %s",
                    $segmentNo + 1, $size
                ),
                $size
                );
        }
        $this->url = parent::getLink($segmentNo);
        $this->setSegment();

    }

    public function setSegment()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->url);
        $this->segments = $this->extractNames($crawler);
        if ($this->checkSubCategory($crawler)) {
            $this->sub_segments_link = $this->extractLinks($crawler);
            $this->noOfSubSegments = count($this->sub_segments_link);
        }else {
            $products = $this->extractProducts($crawler);
            foreach ($products as $product) {
                $this->products[] = $this->setProduct($product);
            }
        }
    }

    /** @return integer */
    protected function getSize()
    {
        return $this->noOfSubSegments;
    }

    /**
     * @return string
     * @param $number
     */
    public function getSegment($number)
    {
        return $this->segments[$number];
    }

    /** @return array */
    public function getSegments()
    {
        return $this->segments;
    }

    /**
     * @return string
     *@param $number
     */
    public function getLink($number)
    {
        return $this->sub_segments_link[$number];
    }

    /** @return array */
    public function getLinks()
    {
        return $this->sub_segments_link;
    }

    public function getParentLinks()
    {
        return parent::getLinks();
    }

    /** @return array */
    public function getProducts()
    {
        return $this->products;
    }
}