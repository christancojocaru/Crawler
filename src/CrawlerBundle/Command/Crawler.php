<?php


namespace CrawlerBundle\Command;


use AppBundle\Entity\Categories;
use AppBundle\Entity\Product as ProductEntity;
use CrawlerBundle\Document\Product as ProductDocument;
use CrawlerBundle\Model\Product as ProductModel;

use CrawlerBundle\Division\Category;
use CrawlerBundle\Division\Department;
use CrawlerBundle\Division\Segment;
use CrawlerBundle\Division\SubCategory;
use CrawlerBundle\Division\WebPage;

use CrawlerBundle\Model\Product;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        $webPage = new WebPage(self::URL);
        $this->departmentsLink = $webPage->extractDepartmentsLink();

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
            case ($this->input["segment"] > -1):
                try {
                    $this->division = new Segment(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"], $this->input["segment"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    exit;
                }
                break;
            case ( $this->input["subCategory"] > -1):
                try {
                    $this->division = new SubCategory(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"], $this->input["subCategory"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    exit;
                }
                break;
            case ( $this->input["category"] > -1 ):
                try {
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"]);
                }catch (Exception $exception) {
                    $output->write("\e[1;37;41m" . $exception->getMessage() . "\e[0m\n");
                    $category_inserted = readline("Insert category number: ");
                    $noOf = $this->getNoOf($exception->getMessage());
                    if ($category_inserted > $noOf) {echo self::TEXT;die;}
                    $this->input["category"] = $category_inserted - 1;
                    $this->division = new Category(self::URL . $this->departmentsLink[$this->input["department"]], $this->input["category"]);
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

        if ($this->division instanceof Department) {
            $categoriesLink = $this->division->getSubDivisionsLink();
            foreach ($categoriesLink as $categoryNo => $categoryLink) {
                $category = new Category(self::URL . $this->division->getDivisionLink($this->input["department"]), $categoryNo);
                $products = $category->getProducts();
                if (empty($products)) {
                    $subCategoriesLink = $category->getSubDivisionsLink();
                    foreach ($subCategoriesLink as $subCategoryNo => $subCategoryLink) {
                        $subCategory = new SubCategory(self::URL . $this->division->getDivisionLink($this->input["department"]), $categoryNo, $subCategoryNo);
                        $products = $subCategory->getProducts();
                        if (empty($products)) {
                            $segmentsLink = $subCategory->getSubDivisionsLink();
                            foreach ($segmentsLink as $segmentNo => $segmentLink) {
                                $segment = new Segment(self::URL . $this->division->getDivisionLink($this->input["department"]), $categoryNo, $subCategoryNo, $segmentNo);
                                $products = $segment->getProducts();
                                if (empty($products)) continue; //exists some sub segments
                                $this->something($products, $this->input["department"], $categoryNo, $subCategoryNo, $segmentNo);
                            }
                        } else {
                            $this->something($products, $this->input["department"], $categoryNo, $subCategoryNo);
                        }
                    }
                } else {
                    $this->something($products, $this->input["department"], $categoryNo);
                }
            }
        } else if (is_null($this->division)){

            foreach ($this->departmentsLink as $departmentNo => $departmentLink) {
                $department = new Department(self::URL . $departmentLink);
                $categoriesLink = $department->getSubDivisionsLink();

                foreach ($categoriesLink as $categoryNo => $categoryLink) {
                    $category = new Category(self::URL . $departmentLink, $categoryNo);
                    /** @var ProductModel $products */
                    $products = $category->getProducts();
                    if (empty($products)) {
                        $subCategoriesLink = $category->getSubDivisionsLink();

                        foreach ($subCategoriesLink as $subCategoryNo => $subCategoryLink) {
                            $subCategory = new SubCategory(self::URL . $departmentLink, $categoryNo, $subCategoryNo);
                            $products = $subCategory->getProducts();
                            if (empty($products)) {
                                $segmentsLink = $subCategory->getSubDivisionsLink();

                                foreach ($segmentsLink as $segmentNo => $segmentLink) {
                                    $segment = new Segment(self::URL . $departmentLink, $categoryNo, $subCategoryNo, $segmentNo);
                                    $products = $segment->getProducts();
                                    if (empty($products)) continue; //exists some sub segments
                                    $this->something($products, $departmentNo, $categoryNo, $subCategoryNo, $segmentNo);
                                }
                            } else {
                                $this->something($products, $departmentNo, $categoryNo, $subCategoryNo);
                            }
                        }
                    } else {
                        $this->something($products, $departmentNo, $categoryNo);
                    }
                }
            }
        } else {
            $products = $this->division->getProducts();
//            $division = $this->division->get
            if (empty($products)) {
                $output->writeln(sprintf("Sorry, Category %s from Department %s does not exist!", $this->input["category"] + 1, $this->input["department"] + 1));
            } else {
                $this->something($products, $this->input["department"], $this->input["category"], $this->input["subCategory"], $this->input["segment"]);
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

    private function something($products, $department = null, $category = null, $subCategory = null, $segment = null)
    {
        if ($this->saveDocumentResponse === "no") {
            $this->saveDocumentResponse = readline("Do you want to save products as document[Yes][No]: ");
            $this->saveEntityResponse = readline("Do you want to save products as entity[Yes][No]: ");
        }
        /** @var ProductModel $product */
        foreach ($products as $product) {
            if (!is_null($category)) {
                $product->setCategory($category + 1);
            }
            $name = $product->getName();
            //need improvement like differentiate product by specs, price...
            $isDuplicate = array_search($name, $this->allProductsName);
            if (is_int($isDuplicate)) {
//                var_dump($product);
                continue;
            };
            $this->allProductsName[] = $name;

            $existsInDatabase = $this->checkPriceInDatabase($product);
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
     * @param ProductModel $product
     * @return boolean
     */
    private function checkPriceInDatabase(ProductModel $product)
    {
        $old = $this->dm->getRepository(ProductDocument::class)->findOneBy(["name" => $product->getName()]);

        if (!empty($old)) {
            $oldPrice = $old->getPrice();
            $newPrice = $product->getPrice();
            $new_price_is_lower = $newPrice < $oldPrice;

            if ($new_price_is_lower) {
                $this->promotion($product->getName(), $oldPrice, $newPrice);
                var_dump("OLD PRICE" . $oldPrice . PHP_EOL);
                var_dump($product);

            }
            return True;
        }
        return False;
    }

    private function promotion($name, $oldPrice, $newPrice)
    {
        if ($this->makePromotionResponse === "no") {
            $this->makePromotionResponse = readline("Do you want to generate promotions[Yes][No]: ");

        } else if ($this->makePromotionResponse === "yes") {
            $this->generatePromotion($name, $oldPrice, $newPrice);
            $this->noOfPromotions++;
        }
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

    private function getNoOf($text)
    {
        $arr = explode(" ", $text);
        return end($arr);
    }

    /**
     * @param ProductModel $product
     */
    private function createEntity(ProductModel $product)
    {
        if (!empty($product->getCategory())) {
            $category = $this->em->getRepository(Categories::class)->find($product->getCategory());
        } else {
            $category = $this->em->getRepository(Categories::class)->find(rand(1, 6));
        }
        $newEntProduct = new ProductEntity();
        $newEntProduct->setName($product->getName());
        $newEntProduct->setPrice($product->getPrice());
        $newEntProduct->setStock(rand(2, 100));
        $newEntProduct->setImage($product->getImage());
        $newEntProduct->setCategory($category);
        $this->em->persist($newEntProduct);
    }

    /**
     * @param $product
     */
    private function createDocument($product)
    {
        /** @var ProductDocument $product */
        $newDocProduct = new ProductDocument();
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
}