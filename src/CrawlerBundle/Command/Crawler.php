<?php


namespace CrawlerBundle\Command;


use CrawlerBundle\Division\Category;
use CrawlerBundle\Division\Department;
use CrawlerBundle\Division\Product as ProductDivision;
use CrawlerBundle\Division\Segment;
use CrawlerBundle\Division\SubCategory;
use CrawlerBundle\Document\Product;
use Doctrine\ODM\MongoDB\DocumentManager;
use Goutte\Client;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler extends Command
{
    const TEXT = "You are an ASSHOLE";
    /** @var string  */
    protected static $defaultName = 'app:crawler';
    /** @var DocumentManager $documentManager */
    private $documentManager;

    private $departmentsLink = [];

    private $categoriesLink = [];

    private $subCategoriesLink = [];

    private $subSubCategoriesLink = [];

    private $noOfProducts = 0;

    private $input = [];

//    private $allProductsName = array();

    private $division;

    const URL = 'https://altex.ro';

    protected function configure()
    {
        $this->setDescription('Crawling')
            ->addOption(
                'department',
                'D',
                InputOption::VALUE_REQUIRED,
                "Specify which department to crawl.")
            ->addOption(
                'category',
                'C',
                InputOption::VALUE_REQUIRED,
                "Specify which category to crawl."
            )
            ->addOption(
                'subCategory',
                'c',
                InputOption::VALUE_OPTIONAL,
                "Specify which subCategory to crawl."
            )
            ->addOption(
                'segment',
                'S',
                InputOption::VALUE_OPTIONAL,
                "Specify which segment to crawl."
            )
            ->addOption(
                'subSegment',
                's',
                InputOption::VALUE_OPTIONAL,
                "Specify which subSegment to crawl."
            );
    }

    public function __construct()
    {
        parent::__construct(/*static::$defaultName*/);
        $client = new Client();
        $crawler = $client->request('GET', self::URL);
        $this->extractDepartmentsLink($crawler);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->input["department"] = $input->getOption('department') - 1;
        $this->input["category"] = $input->getOption('category') - 1;
        $this->input["subCategory"] = $input->getOption('subCategory') - 1;
        $this->input["segment"] = $input->getOption('segment') - 1;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);

        $noOfDepartment = count($this->departmentsLink);
        $department = $this->input["department"];
        if ($department > $noOfDepartment) {
            $output->write(sprintf(
                "\e[0;30;41mDepartment with number %s is incorrect. Please select a number between 1 and %s\e[0m\n",
                $department + 1, $noOfDepartment
            ));
            $department = readline("Insert department: ");
            if ($department > $noOfDepartment) {echo self::TEXT;die;}
            $this->input["department"] = $department - 1;
        }

        $options = array_slice($input->getOptions(), 0, 5);
        switch ($options){
            case ( array_key_exists("segment", $options) && !is_null($options["segment"]) ):
                try {
                    $this->division = new Segment(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"], $this->input["segment"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    $segment = readline("Insert segment: ");
                    if ($segment > $exception->getCode()) {echo self::TEXT;die;}
                    $this->division = new Segment(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"], $segment - 1);
                }
                break;
            case ( array_key_exists("subCategory", $options) && !is_null($options["subCategory"]) ):
                try {
                    $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"]);
                }catch (Exception $exception) {
                    $division = $this->getDivision($exception->getFile());
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    $subDivision = readline("Insert " . $division . ": ");
                    if ($subDivision > $exception->getCode() || $subDivision < 1) {echo self::TEXT;die;}
                    switch ($division) {
                        case "subcategory":
                            $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $subDivision - 1);
                            break;
                        case "category":
                            try {
                                $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $subDivision - 1, $this->input["subCategory"]);
                            }catch (Exception $exception) {
                                $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                                $subCategory = readline("Insert subcategory: ");
                                if ($subCategory > $exception->getCode() || $subCategory < 1) {echo self::TEXT;die;}
                                $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $subDivision - 1,$subCategory - 1);
                            }
                            break;
                    }
                }
                break;
            case ( array_key_exists("category", $options) && !is_null($options["category"]) ):
                try {
                    $this->division = new Category(self::URL.$this->departmentsLink[$this->input["department"]], $this->input["category"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    $category = readline("Insert category: ");
                    if ($category > $exception->getCode()) {echo self::TEXT;die;}
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $category);
                }
                break;
            case ( array_key_exists("department", $options) && !is_null($options["department"]) ):
                $this->division = new Department(self::URL . $this->departmentsLink[$this->input["department"]]);
                break;
            default:
                continue;
        }
    }

    protected function execute(InputInterface $input,OutputInterface  $output)
    {
        $output->write("Now is: ".date("H:i:s"));
        ExecutionTime::start();

//        $this->something($this->division->getDepartments());
        declare(ticks = 1);

        pcntl_signal(SIGTERM, [$this, 'doExit']);

        while (true) {
            foreach ($this->departmentsLink as $departmentLink) {
                $department = new Department(self::URL . $departmentLink);
                $categoriesLink = $department->getLinks();
                foreach ($categoriesLink as $categoryNo => $categoryLink) {
                    $category = new Category(self::URL . $departmentLink, $categoryNo);
                    $products = $category->getProducts();
                    if (empty($products)) {
                        echo "EMPTY" . PHP_EOL;
                        $subCategoriesLink = $category->getLinks();
                        foreach ($subCategoriesLink as $subCategoryNo => $subCategoryLink) {
                            $subCategory = new SubCategory(self::URL . $departmentLink, $categoryNo, $subCategoryNo);
                            $subProducts = $subCategory->getProducts();
                            if (empty($subProducts)) {
                                echo "SUB EMPTY" . PHP_EOL;
                                $segmentsLink = $subCategory->getLinks();
                                foreach ($segmentsLink as $segmentNo => $segmentLink) {
                                    $segment = new Segment(self::URL . $departmentLink, $categoryNo, $subCategoryNo, $segmentNo);
                                    $subSubProducts = $segment->getProducts();
                                    if (empty($subSubProducts)) continue;
                                    /** @var ProductDivision $subSubProduct */
                                    $subSubProduct = $subSubProducts[0];
                                    echo $subSubProduct->getName();
                                }
                            } else {
                                /** @var ProductDivision $subProduct */
                                $subProduct = $subProducts[0];
                                echo $subProduct->getName();
                                echo PHP_EOL;
                            }
                        }
                    } else {
                        /** @var ProductDivision $product */
                        $product = $products[0];
                        echo $product->getName();
                        echo PHP_EOL;
                    }
                }
            }
        }


//            $departmentClient = new Client();
//            $departmentCrawler = $departmentClient->request('GET', self::URL.$this->departmentsLink[$department]);
//            $categoriesLink = $this->extractCategoriesLink($departmentCrawler);
////            foreach ($categoriesLink as $categoryLink) {
//                $categoriesClient = new Client();
//                $categoriesCrawler = $categoriesClient->request('GET', $categoriesLink[$category]);
//                $subCategoriesLink = $this->checkSubCategory($categoriesCrawler);
//                var_dump($categoriesLink, $subCategoriesLink);die;
//                if ($subCategoriesLink) {
//                    foreach ($subCategoriesLink as $subCategoryLink) {
//                        $subCategoriesClient = new Client();
//                        $subCategoriesCrawler = $subCategoriesClient->request('GET', $subCategoryLink);
//                        $subSubCategoriesLink = $this->checkSubCategory($subCategoriesCrawler);
//                        if ($subSubCategoriesLink) {
//                            foreach ($subSubCategoriesLink as $subSubCategoryLink) {
//                                $subSubCategoriesClient = new Client();
//                                $subSubCategoriesCrawler = $subSubCategoriesClient->request('GET', $subSubCategoryLink);
//                                if ($this->checkSubCategory($subSubCategoriesCrawler)) continue;
//                                $this->something($this->extractProduct($subSubCategoriesCrawler));
//                            }
//                        } else {
//                            $this->something($this->extractProduct($subCategoriesCrawler));
//                        }
//                    }
//                } else {
//                    $this->something($this->extractProduct($categoriesCrawler));
//                }
////            }
////        }
        $output->write(sprintf('Execution time was : %s seconds', ExecutionTime::elapsed()));
        $output->write("Now is: ".date("H:i:s"));
        $output->write(sprintf('Saved %s products into database', $this->noOfProducts));
    }

    /**
     * Ctrl-Z
     */
    private function doExit()
    {
        exit;
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

    private function getDivision($exception)
    {
        $arr = explode("\\", $exception);
        $last = end($arr);
        $last = strtolower(explode(".", $last)[0]);
        return $last;
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