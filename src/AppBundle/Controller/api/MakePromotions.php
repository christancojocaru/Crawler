<?php


namespace AppBundle\Controller\api;


use AppBundle\Entity\Product as ProductEntity;
use AppBundle\Entity\Product;
use AppBundle\Entity\Promotion;
use Doctrine\ORM\EntityManagerInterface;
use Exception as Exception;
use Exception as ExceptionAs;
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
        $data = json_decode($body, true);// need validation

        if (empty($data)) {
            return new Response("No data received!");
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
        $promo->setPercent($percent);
        try {
            $promo->setDate();
        }catch (Exception $exception) {
            return new Exception($exception->getMessage(), $exception->getCode());
        }
        $promo->setProduct($product);
        $this->em->persist($promo);

        try {
            $this->em->flush();
        }catch (ExceptionAs $exception) {
            return new Response($exception->getMessage());
        }

        return new Response(sprintf(
            "Promotion id %s for product with id %s created successfully",
            $promo->getId(),
            $product->getId()
        ));
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