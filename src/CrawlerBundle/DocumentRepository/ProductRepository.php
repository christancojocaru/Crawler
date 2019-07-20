<?php


namespace CrawlerBundle\DocumentRepository;


use Doctrine\ODM\MongoDB\DocumentRepository;

class ProductRepository extends DocumentRepository
{
    public function getPrice($name)
    {
        return $this->createQueryBuilder()
            ->field('name')->equals($name)
            ->getQuery()
            ->execute();

    }
}