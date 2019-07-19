<?php


namespace CrawlerBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 */
class Product extends \CrawlerBundle\Division\Abstracts\Product
{
    /** @MongoDB\Id */
    protected $id;

    /** @MongoDB\Field(type="string") */
    protected $name;

    /** @MongoDB\Field(type="float") */
    protected $price;

    /** @MongoDB\Field(type="date") */
    protected $date;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    public function setDate()
    {
        $this->date = date("Y-m-d H:i:s");
    }

}