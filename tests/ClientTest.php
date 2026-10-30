<?php

namespace Alif\LaravelRabbitmq\Tests;

use Alif\LaravelRabbitmq\Client;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionProperty;

class ClientTest extends TestCase
{
    public function test_rpc_accepts_falsey_replies_and_ignores_unrelated_replies(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('is_open')->willReturn(true);
        $channel->method('queue_declare')->willReturn(['callback', 0, 0]);

        $callback = null;
        $correlationId = null;
        $channel->method('basic_consume')->willReturnCallback(
            function ($queue, $tag, $local, $ack, $exclusive, $nowait, $consumer) use (&$callback) {
                $callback = $consumer;
                return 'consumer';
            }
        );
        $channel->method('basic_publish')->willReturnCallback(
            function (AMQPMessage $message) use (&$correlationId) {
                $correlationId = $message->get('correlation_id');
            }
        );

        $values = [[], null, false, 0, '', '0'];
        $deliveries = [];
        foreach ($values as $value) {
            $deliveries[] = ['unrelated', 'wrong-correlation'];
            $deliveries[] = [$value, null];
        }
        $channel->expects($this->exactly(count($deliveries)))->method('wait')->willReturnCallback(
            function () use (&$deliveries, &$callback, &$correlationId) {
                [$value, $id] = array_shift($deliveries);
                $callback(new AMQPMessage(json_encode($value), [
                    'correlation_id' => $id ?? $correlationId,
                ]));
            }
        );
        $channel->expects($this->exactly(count($values)))->method('basic_cancel')->with('consumer');

        $client = new Client();
        (new ReflectionProperty(Client::class, 'channel'))->setValue($client, $channel);
        (new ReflectionProperty(Client::class, 'declaredQueues'))->setValue($client, ['test' => true]);

        foreach ($values as $value) {
            $this->assertSame($value, $client->request('getBonusesOfProducts', [], 'test')->getResult());
        }
    }
}
