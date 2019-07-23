<?php


namespace AppBundle\Controller\web;


use AppBundle\Entity\Product;
use AppBundle\Entity\Promotion;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Index extends Controller
{
    /**
     * @Route("/", name="index_action")
     * @return Response
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Product $product */
        $products = $em->getRepository(Product::class)->findAll();

        $data = [];
        foreach ($products as $product) {
            /** @var Promotion $promo */
            $promo = $em->getRepository(Promotion::class)->findOneBy(["product" => $product->getId()]);
            if (is_null($promo)) continue;
            $discount = number_format($promo->getPercent(), 2);
            $promoPrice = $product->getPrice() * $promo->getPercent();
            $newPrice = $product->getPrice() - $promoPrice;
            $data[$product->getId()] = [
                "name" => $product->getName(),
                "oldPrice" => $product->getPrice(),
                "newPrice" => number_format($newPrice, 2),
                "promoPrice" => number_format($promoPrice, 2),
                "discount" => $discount * 100
            ];
        }

        return $this->render("index.html.twig", [
            "products" => $data
        ]);
    }

}