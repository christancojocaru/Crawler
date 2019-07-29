<?php


namespace CrawlerBundle\Division;


use Symfony\Component\Config\Definition\Exception\Exception;

class Category extends WebPage
{
    public function __construct($url, $number)
    {
        $department = new Department($url);
        $size = $department->getSize();
        if ( $number > $size ) {
            throw new Exception(
                sprintf(
                    "Category with number %s is incorrect. Please select a number between 1 and %s",
                    $number + 1, $size
                ));
        }
        $subUrl = $department->getSubDivisionLink($number);
        parent::__construct($subUrl);
        if ($this->checkSubCategory()) {
            $this->setSubDivisionsNameAndLink();
            $this->noOfSubDivisions = count($this->sub_divisions_link);
        } else {
            $this->extractProducts();
        }
    }
}