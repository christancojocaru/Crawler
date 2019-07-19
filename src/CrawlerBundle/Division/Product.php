<?php


namespace CrawlerBundle\Division;


use CrawlerBundle\Division\Abstracts\Product as AbstractProduct;
//use Symfony\Component\Config\Definition\Exception\Exception;

class Product extends AbstractProduct
{
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


}