<?php


namespace CrawlerBundle\Command;


use AppBundle\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Goutte\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class WikipediaCrawler extends Command
{
    const URL = 'https://ro.wikipedia.org/wiki/Lista_ora%C8%99elor_din_Rom%C3%A2nia';

    /** @var string */
    protected static $defaultName = 'app:crawler:wiki';
    /** @var EntityManagerInterface $em */
    private $em;
    /** @var object */
    protected $crawler;

    public function __construct()
    {
        parent::__construct();
        $client = new Client();
        $this->crawler = $client->request('GET', self::URL);
    }


    protected function configure()
    {
        $this->setDescription('Crawling');
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ExecutionTime::start();
        $this->extractData();
    }

    public function extractData()
    {
        $names = $this->crawler
            ->filterXPath('//table[contains(@class, "wikitable")]//tbody/tr')
            ->reduce(function (Crawler $node, $i) {
                return ($i != 0 && $i <= 50);
            })
            ->each(function (Crawler $crawler) {
                return $crawler
                    ->filterXPath("//a")
                    ->first()
                    ->text();
            });

        foreach ($names as $eq => $name) {
            $this->createEntity($name, $eq);
        }
        $this->em->flush();
    }

    private function createEntity($name, $eq)
    {
        $image = $this->crawler
            ->filterXPath('//table[contains(@class, "wikitable")]//tbody/tr/td/a/img')
            ->eq($eq)
            ->attr("src");
        var_dump($name, $image);
        $arrayurl = explode("/", $image);
        array_splice($arrayurl, -1);
        array_splice($arrayurl, 5, 1);

        $image = implode("/", $arrayurl);

        $city = new City();
        $city->setName($name);
        $city->setImage($image);
        $this->em->persist($city);
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