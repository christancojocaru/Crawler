<?php


namespace CrawlerBundle\Division;


class Department extends WebPage
{
    public function __construct($url)
    {
        parent::__construct($url);

        $this->setSubDivisionsNameAndLink();
        $this->noOfSubDivisions = count($this->sub_divisions_link);
        $this->divisions_link = $this->extractDepartmentsLink();
    }
}