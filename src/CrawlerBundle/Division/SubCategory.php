<?php


namespace CrawlerBundle\Division;


use Symfony\Component\Config\Definition\Exception\Exception;

class SubCategory extends WebPage
{
    public function __construct($url, $categoryNo, $subCategoryNo)
    {
        $category = new Category($url, $categoryNo);
        $subUrl = $category->getSubDivisionLink($subCategoryNo);
        parent::__construct($subUrl);
        $size = $category->getSize();
        if ( $subCategoryNo > $size ) {
            throw new Exception(
                sprintf(
                    "SubCategory with number %s is incorrect. Please select a number between 1 and %s",
                    $subCategoryNo + 1, $size
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