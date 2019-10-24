<?php


namespace CrawlerBundle\Traits;


trait DivisionTrait
{
    /** @return integer */
    public function getSize()
    {
        return $this->noOfSubDivisions;
    }

    /**
     * @param $number
     * @return mixed
     */
    public function getSubDivisionLink($number)
    {
        return $this->sub_divisions_link[$number];
    }

    /**
     * @return mixed
     */
    public function getSubDivisionsLink()
    {
        return $this->sub_divisions_link;
    }

    /**
     * @param $number
     * @return mixed
     */
    public function getDivisionLink($number)
    {
        return $this->divisions_link[$number];
    }

    /** @return array */
    public function getProducts()
    {
        return $this->products;
    }

    public function getSubDivision($number)
    {
        return $this->sub_divisions[$number];
    }
}