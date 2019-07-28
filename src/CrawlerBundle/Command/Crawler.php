<?php


namespace CrawlerBundle\Command;


use AppBundle\Entity\Categories;
use CrawlerBundle\Division\Category;
use CrawlerBundle\Division\Department;
use CrawlerBundle\Division\Product as ProductDivision;
use CrawlerBundle\Division\Segment;
use CrawlerBundle\Division\SubCategory;
use CrawlerBundle\Document\Product;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOException;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Goutte\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler extends Command
{
    use LockableTrait;

    const URL = 'https://altex.ro';
    const TEXT = "You cannot do this job";

    /** @var string  */
    protected static $defaultName = 'app:crawler';
    /** @var DocumentManager $dm */
    private $dm;
    /** @var EntityManagerInterface $em */
    private $em;
    /** @var array $departmentsLink */
    private $departmentsLink = [];

    /** @var array $input */
    private $input = [];
    /** @var Category $division*/
    private $division;
     /** @var array $allProductsName */
    private $allProductsName = array("start");
    /** @var int $noOfPromotions */
    private $noOfPromotions = 0;
    /** @var OutputInterface $output */
    private $output;


    /** @var int $noOfEntitiesSaved */
    private $noOfEntitiesSaved = 0;
    /** @var int $noOfDocumentsSaved */
    private $noOfDocumentsSaved = 0;
    /** @var string $makePromotionResponse */
    private $makePromotionResponse = "no";
    /** @var string $saveEntityResponse */
    private $saveEntityResponse = "no";
    /** @var string $saveDocumentResponse */
    private $saveDocumentResponse = "no";

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
        date_default_timezone_set('Europe/Kiev');
        $script_tz = date_default_timezone_get();
        if (strcmp($script_tz, ini_get('date.timezone'))){
            throw new Exception('Script timezone differs from ini-set timezone.');
        }

        parent::__construct();
        $client = new Client();
        $crawler = $client->request('GET', self::URL . "/home");
        $this->extractDepartmentsLink($crawler);

    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln("The command is already running in another process.");
            die;
        }
        parent::initialize($input, $output);
        $this->output = $output;
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

        switch ($this->input){
            case ( $this->input["segment"] > -1 ):
                try {
                    $this->division = new Segment(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"], $this->input["segment"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                }
                break;
            case ( $this->input["subCategory"] > -1 ):
                try {
                    $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                }
                break;
            case ( $this->input["category"] > -1 ):
                try {
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    $category = readline("Insert category: ");
                    $noOf = $this->getNoOf($exception->getMessage());
                    var_dump($noOf);die;
                    if ($category > $noOf) {echo self::TEXT;die;}
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $category);
                }
                break;
            case ( $this->input["department"] > -1 ):
                $this->division = new Department(self::URL . $this->departmentsLink[$this->input["department"]]);
                break;
            default:
                $this->division = null;
        }
    }

    protected function execute(InputInterface $input,OutputInterface  $output)
    {
        ExecutionTime::start();

        if (is_null($this->division)){
            foreach ($this->departmentsLink as $departmentLink) {
                $department = new Department(self::URL . $departmentLink);
                $categoriesLink = $department->getLinks();
                foreach ($categoriesLink as $categoryNo => $categoryLink) {
                    $category = new Category(self::URL . $departmentLink, $categoryNo);
                    $products = $category->getProducts();
                    if (empty($products)) {
                        $subCategoriesLink = $category->getLinks();
                        foreach ($subCategoriesLink as $subCategoryNo => $subCategoryLink) {
                            $subCategory = new SubCategory(self::URL . $departmentLink, $categoryNo, $subCategoryNo);
                            $products = $subCategory->getProducts();
                            if (empty($products)) {
                                $segmentsLink = $subCategory->getLinks();
                                foreach ($segmentsLink as $segmentNo => $segmentLink) {
                                    $segment = new Segment(self::URL . $departmentLink, $categoryNo, $subCategoryNo, $segmentNo);
                                    $products = $segment->getProducts();
                                    if (empty($products)) continue; //exists some sub segments
                                    $this->something($products);
                                }
                            } else {
                                $this->something($products);
                            }
                        }
                    } else {
                        $this->something($products);
                    }
                }
            }
        } else {
            $products = $this->division->getProducts();
            if (empty($products)) {
                $output->writeln(sprintf("Sorry, Category %s from Department %s does not exist!", $this->input["category"] + 1, $this->input["department"] + 1));
            } else {
                $this->something($products);
            }
        }

        $productsFound = count($this->division->getProducts());
        if ($productsFound > 0) {
            $output->writeln(sprintf('Found %s products crawling', $productsFound));
        }
        if ($this->noOfDocumentsSaved > 0) {
            $output->writeln(sprintf('Saved %s products as document', $this->noOfDocumentsSaved));
        }
        if ($this->noOfEntitiesSaved > 0) {
            $output->writeln(sprintf('Saved %s products as entity', $this->noOfEntitiesSaved));
        }
        if ($this->noOfPromotions > 0) {
            $output->writeln(sprintf('Send %s promotion(s) to be created', $this->noOfPromotions));
        }
        $output->writeln(sprintf('Execution time was %s seconds', ExecutionTime::elapsed()));
    }

    private function something($products)
    {
        $this->saveDocumentResponse = readline("Do you want to save products as document[Yes][No]: ");
        $this->saveEntityResponse = readline("Do you want to save products as entity[Yes][No]: ");
        /** @var ProductDivision $product */
        foreach ($products as $product) {
            $isDuplicate = array_search($product->getName(), $this->allProductsName);
            if (is_int($isDuplicate)) continue;
            $this->allProductsName[] = $product->getName();

            $existsInDatabase = $this->checkPrices($product);
            if (!$existsInDatabase && $this->saveEntityResponse === 'yes') {
                $this->createEntity($product);
                $this->noOfEntitiesSaved++;
            }
            if (!$existsInDatabase && $this->saveDocumentResponse === 'yes') {
                $this->createDocument($product);
                $this->noOfDocumentsSaved++;
            }
        }
        $this->dm->flush();
        $this->em->flush();
    }

    /**
     * @param ProductDivision $product
     * @return boolean
     */
    private function checkPrices(ProductDivision $product)
    {
        /** @var Product $old */
        $old = $this->dm->getRepository(Product::class)->findOneBy(["name" => $product->getName()]);

        if (!empty($old)) {
            $oldPrice = $old->getPrice();
            $newPrice = $product->getPrice();
            if ($this->makePromotionResponse === "no") {
                $this->makePromotionResponse = readline("Do you want to generate promotions[Yes][No]: ");
            }
            if ($oldPrice > $newPrice && $this->makePromotionResponse === "yes") {
                $this->generatePromotion($product->getName(), $oldPrice, $newPrice);
                $this->noOfPromotions++;
            }
            return True;
        }

        return False;
    }

    private function generatePromotion($name, $oldPrice, $newPrice)
    {
        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('promo', false, false, false, false);

        $data = [
            'name' => $name,
            'oldPrice' => $oldPrice,
            "newPrice" => $newPrice
        ];

        $msg = new AMQPMessage(json_encode($data));
        $channel->basic_publish($msg, '', 'promo');

        echo " [x] Sent product name: " . $name . "\n";

        $channel->close();
        $connection->close();
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

    private function getWhichDivisionHasException($file)
    {
        $arr = explode("\\", $file);
        $last = end($arr);
        $first = strtolower(explode(".", $last)[0]);
        return $first;
    }

    private function getNoOf($text)
    {
        $arr = explode(" ", $text);
        return end($arr);
    }

    /**
     * @param $product
     */
    private function createEntity($product)
    {
        $category = $this->em->getRepository(Categories::class)->find(rand(1, 6));
        /** @var ProductDivision $product */
        $newEntProduct = new \AppBundle\Entity\Product();
        $newEntProduct->setName($product->getName());
        $newEntProduct->setPrice($product->getPrice());
        $newEntProduct->setStock(rand(2, 100));
        $newEntProduct->setCategory($category);
        $this->em->persist($newEntProduct);
    }

    /**
     * @param $product
     */
    private function createDocument($product)
    {
        /** @var ProductDivision $product */
        $newDocProduct = new Product();
        $newDocProduct->setName($product->getName());
        $newDocProduct->setPrice($product->getPrice());
        $newDocProduct->setDate();
        $this->dm->persist($newDocProduct);
    }

    /**
     * @param DocumentManager $documentManager
     * @required
     */
    public function setDocumentManager(DocumentManager $documentManager)
    {
        $this->dm = $documentManager;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @required
     */
    public function setEntityManager(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    protected function interactComplex(InputInterface $input, OutputInterface $output)
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

        switch ($this->input){
            case ( $this->input["segment"] > -1 ):
                try {
                    $this->division = new Segment(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"], $this->input["segment"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                }
                break;
            case ( $this->input["subCategory"] > -1 ):
                try {
                    $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"]);
                }catch (Exception $exception) {
                    $division = $this->getWhichDivisionHasException($exception->getFile());
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
            case ( $this->input["category"] > -1 ):
                try {
                    var_dump($this->departmentsLink[$this->input["department"]]);
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    $category = readline("Insert category: ");
                    if ($category > $exception->getCode()) {echo self::TEXT;die;}
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $category);
                }
                break;
            case ( $this->input["department"] > -1 ):
                $this->division = new Department(self::URL . $this->departmentsLink[$this->input["department"]]);
                break;
            default:
                $this->division = null;
        }
    }
}