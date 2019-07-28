<?php


namespace AppBundle\Controller\api;


use AppBundle\Entity\Product as ProductEntity;
use AppBundle\Entity\Product;
use AppBundle\Entity\Promotion;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception as Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MakePromotions extends Controller
{
    /** @var EntityManagerInterface */
    private $em;

    /**
     * @Route("api/promotion", methods={"POST"})
     * @param Request $request
     * @return Exception|Response
     */
    public function updateAction(Request $request)
    {
        $body = $request->getContent();
        $data = json_decode($body, true);

        try {
            $this->validation($data);
        }catch (Exception $exception) {
            return new Response($exception->getMessage());
        }

        $product = $this->em->getRepository(ProductEntity::class)->findOneBy([
            "name" => $data["name"]
        ]);

        if (is_null($product)) {
            return new  Response("Product not found");
        }

        $percent = floatval(1 - ($data["newPrice"] / $data["oldPrice"]) );
        $promo = new Promotion();
        $promo->setName("Crawler");
        $promo->setProduct($product);
        $promo->setPercent($percent);

        try {
            $promo->setDate();
        }catch (Exception $exception) {
            return new Exception($exception->getMessage(), $exception->getCode());
        }

        try {
            $this->em->persist($promo);
            $this->em->flush();
        }catch (ConstraintViolationException $exception) {
            if ($exception->getSQLState() == 23000) {
                return new Response(sprintf(
                    "Promotion Crawler is already created for product with id %s.",
                    $product->getId()
                ));
            }
        }

        return new Response(sprintf(
            "Promotion %s for product with id %s created successfully",
            $promo->getName(),
            $product->getId()
        ));
    }

    /**
     * @param $data
     * @return Exception|boolean
     */
    private function validation($data)
    {
        // TODO validate data
        if ( empty($data["name"]) || empty($data["oldPrice"]) || $data["newPrice"] ) {
            return new Exception("Data is not valid");
        }
        return True;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @required
     */
    public function setDoctrineManager(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }
}