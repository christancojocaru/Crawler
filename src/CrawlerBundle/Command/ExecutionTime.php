<?php


namespace CrawlerBundle\Command;


class ExecutionTime
{
    private static $start;

    public static function start()
    {
       self::$start = microtime(true);
    }

    public static function elapsed()
    {
        return intval(microtime(true) - self::$start);
    }
}