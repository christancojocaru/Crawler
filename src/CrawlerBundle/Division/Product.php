<?php


namespace CrawlerBundle\Division;


class Product
{
    /** @var string */
    private $name;
    /** @var float */
    private $price;
    /** @var string */
    private $description;

    /**
     * @param string $data
     */
    public function setData($data)
    {
        $this->setName(explode(",", $data)[0]);
        $this->setDescription($data);
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }
}