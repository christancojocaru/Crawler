<?php


namespace AppBundle\Repository;


use AppBundle\Entity\Product;
use Doctrine\ORM\EntityRepository;

class ProductsRepository extends EntityRepository
{
    /**
     * @param $categoryId
     * @return mixed
     */
    public function findAllByCategory($categoryId)
    {
        return $this->createQueryBuilder('p')
            ->where("p.category = :categoryId")
            ->setParameter("categoryId", $categoryId)
            ->getQuery()
            ->execute();
    }

    /**
     * @param $productName
     * @return array
     */
    public function checkProductName($productName)
    {
        $result = [];
        $products = $this->findBy(["name" => $productName]);
        if ($products) {
            /** @var Product $product */
            foreach ($products as $product) {
                $result[] = $product->getName();
            }
        }

        return $result;
    }
}