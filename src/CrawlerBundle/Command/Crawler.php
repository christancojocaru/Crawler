<?php


namespace CrawlerBundle\Command;


use CrawlerBundle\Document\Product;
use Doctrine\ODM\MongoDB\DocumentManager;
use Goutte\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler extends Command
{
    /** @var string  */
    protected static $defaultName = 'app:crawler';
    /** @var DocumentManager $documentManager */
    private $documentManager;

    private $departmentsLink = [];

    private $noOfProducts = 0;

//    private $allProductsName = array();


    const URL = 'https://altex.ro';

    protected function configure()
    {
        $this->setDescription('Crawling');
//            ->addArgument('division', InputArgument::REQUIRED, "The Division to crawl: '");
    }

    public function __construct($name = null)
    {
        parent::__construct($name);
        $client = new Client();
        $crawler = $client->request('GET', self::URL);
        $this->extractDepartmentsLink($crawler);
    }

    protected function execute(InputInterface $input,OutputInterface  $output)
    {
        $output->writeln("Time is: ".date("H:i:s"));
        ExecutionTime::start();
        foreach ($this->departmentsLink as $departmentLink) {
            $departmentClient = new Client();
            $departmentCrawler = $departmentClient->request('GET', self::URL.$departmentLink);
            $categoriesLink = $this->extractCategoriesLink($departmentCrawler);
            foreach ($categoriesLink as $categoryLink) {
                $categoriesClient = new Client();
                $categoriesCrawler = $categoriesClient->request('GET', $categoryLink);
                $subCategoriesLink = $this->checkSubCategory($categoriesCrawler);
                if ($subCategoriesLink) {
                    foreach ($subCategoriesLink as $subCategoryLink) {
                        $subCategoriesClient = new Client();
                        $subCategoriesCrawler = $subCategoriesClient->request('GET', $subCategoryLink);
                            $subSubCategoriesLink = $this->checkSubCategory($subCategoriesCrawler);
                            if ($subSubCategoriesLink) {
                                foreach ($subSubCategoriesLink as $subSubCategoryLink) {
                                    $subSubCategoriesClient = new Client();
                                    $subSubCategoriesCrawler = $subSubCategoriesClient->request('GET', $subSubCategoryLink);
                                    if ($this->checkSubCategory($subSubCategoriesCrawler)) continue;
                                    $this->something($this->extractProduct($subSubCategoriesCrawler));
                                }
                            } else {
                                $this->something($this->extractProduct($subCategoriesCrawler));
                            }
                    }
                } else {
                    $this->something($this->extractProduct($categoriesCrawler));
                }
            }
        }
        $output->writeln(sprintf('Execution time was : %s seconds', ExecutionTime::elapsed()));
        $output->writeln("Now is: ".date("H:i:s"));
        $output->writeln(sprintf('Saved %s products into database', $this->noOfProducts));
    }

    






    private function something($products)
    {
        foreach ($products as $product) {
//            if (array_search($product[0], $this->allProductsName)) continue;
//            $this->allProductsName[] = $product[0];
//            $this->addToDatabases($product);
            $this->noOfProducts++;
        }
    }


    private function extractDepartmentsLink($crawler)
    {
        $this->departmentsLink = $crawler
            ->filterXPath('//ul[contains(@class, "ProductsMenu")]')
            ->filterXPath('//li[contains(@class, "ProductsMenu-item")]')
            ->each(function (DomCrawler $crawler) {
                return $crawler
                    ->filter('a')
                    ->attr('href');
            });
    }

    private function extractCategoriesLink($crawler)
    {
        return $crawler
            ->filterXPath('//ul[contains(@class, "Products--5to2")]')//some pages have categories AND products on same page
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (DomCrawler $crawler) {
                return $crawler
                    ->filter('a')
                    ->attr('href');
            });
    }

    private function checkSubCategory($crawler)
    {
        $childrens = $crawler
            ->filterXPath('//ul[contains(@class, "Products")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->first()
            ->children()
            ->nodeName();
        if ( $childrens == 'a' ) {
            return $this->extractCategoriesLink($crawler);
        }else{
            return False;
        }
    }

    private function extractProduct($crawler)
    {
        return $crawler
            ->filterXPath('//ul[contains(@class, "Products--4to2")]')
            ->filterXPath('//li[contains(@class, "Products-item")]')
            ->each(function (DomCrawler $crawler) {
                $price = $crawler
                    ->filterXPath('//meta')
                    ->reduce(function ($node, $i) {
                        return ($i % 2) == 0;
                    })
                    ->extract(['content']);
                $name = $crawler
                    ->filterXPath('//h2')
                    ->reduce(function ($node, $i) {
                        return ($i % 2) == 0;
                    })
                    ->extract(['_text']);
                return [$name[0], $price[0]];
            });
    }

    private function addToDatabases($product)
    {
        $newProduct = new Product();
        $newProduct->setName($product[0]);
        $newProduct->setPrice($product[1]);
        $newProduct->setDate();
        $this->documentManager->persist($newProduct);
        $this->documentManager->flush();
    }

    /**
     * @param DocumentManager $documentManager
     * @required
     */
    public function setDocumentManager(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }
}