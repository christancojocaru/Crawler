<?php


namespace AppBundle\Consumer;



use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class UploadPictureConsumer implements ConsumerInterface
{

    public function execute(AMQPMessage $msg)
    {
        var_dump($msg->getBody());
    }

}