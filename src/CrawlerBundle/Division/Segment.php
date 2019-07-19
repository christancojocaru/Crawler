<?php


namespace CrawlerBundle\Division;


use Goutte\Client;
use Symfony\Component\Config\Definition\Exception\Exception;

class Segment extends SubCategory
{
    private $url;

    public function __construct($url, $path, $categoryNo, $subCategoryNo, $segmentNo)
    {
        parent::__construct($url, $path, $categoryNo, $subCategoryNo);
        $noOfSegments = count(parent::getData());
        if ( $segmentNo > $noOfSegments ) {
            throw new Exception(
                sprintf(
                    "Segment with number %s is incorrect. Please select a number between 0 and %s",
                    $segmentNo + 1,
                    $noOfSegments
                ),
                $noOfSegments
                );
        }
        $this->url = parent::getData()[$segmentNo];
    }

    public function getData()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->url);
        if ($this->checkSubCategory($crawler)) {
            return $this->extractLinks($crawler);
        }else {
            return $this->extractProduct($crawler);
        }
    }
}