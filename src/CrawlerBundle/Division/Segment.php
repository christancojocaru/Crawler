<?php


namespace CrawlerBundle\Division;


use Symfony\Component\Config\Definition\Exception\Exception;

class Segment extends WebPage
{
    public function __construct($url, $categoryNo, $subCategoryNo, $segmentNo)
    {
        $subCategory = new SubCategory($url, $categoryNo, $subCategoryNo);
        $subUrl = $subCategory->getSubDivisionLink($subCategoryNo);
        parent::__construct($subUrl);
        $size = $subCategory->getSize();
        if ( $segmentNo > $size ) {
            throw new Exception(
                sprintf(
                    "Segment with number %s is incorrect. Please select a number between 1 and %s",
                    $segmentNo + 1, $size
                ));
        }
        if ($this->checkSubCategory()) {
            $this->setSubDivisionsNameAndLink();
            $this->noOfSubDivisions = count($this->sub_divisions_link);
        } else {
            $this->extractProducts();
        }
    }
}