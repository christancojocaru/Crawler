<?php


namespace CrawlerBundle\Command;


use CrawlerBundle\Division\Department;
use CrawlerBundle\Division\Segment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command
{
    protected static $defaultName = "app:test";

    const URL = "https://altex.ro/tv-audio-video-foto/cpl/";

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $segment = new Segment(self::URL, 11, 0, 0);
//        $segment->getDivision();

        $department = new Department(self::URL);
        var_dump($department->getSubDivisions());
    }
}