<?php

namespace CrawlerBundle\Controller;

use CrawlerBundle\Document\Product;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /** @var DocumentManager $dm */
    private $dm;

    /**
     * @Route("/")
     * @return Response
     */
    public function indexAction()
    {
        $product = new Product();
        $product->setName("lalala");
        $product->setPrice(145.32);

//        $this->dm->persist($product);
//        $this->dm->flush();

        $dm = $this->get('doctrine_mongodb')->getManager();
        $dm->persist($product);
        $dm->flush();

        return $this->render(
            '@Crawler/Default/index.html.twig', [
                "response" => "creat"
            ]
        );
    }

    /**
     * @param DocumentManager $documentManager
     * @required
     */
    public function getDocumentManager(DocumentManager $documentManager)
    {
        $this->dm = $documentManager;
    }
}