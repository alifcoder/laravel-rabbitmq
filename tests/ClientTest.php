<?php

namespace Alif\LaravelRabbitmq\Tests;

use Alif\LaravelRabbitmq\Client;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionProperty;

class ClientTest extends TestCase
{
    public function test_existing_queue_gets_dead_letter_setup_and_failed_messages_are_forwarded(): void
    {
        config()->set('rabbitmq.dead_letter_exchange', 'dlx');
        config()->set('rabbitmq.dead_letter_queue_suffix', '.dlq');

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('is_open')->willReturn(true);
        $declarations = [];
        $channel->expects($this->exactly(2))->method('queue_declare')->willReturnCallback(
            function (...$arguments) use (&$declarations) {
                $declarations[] = array_slice($arguments, 0, 5);
                return [$arguments[0], 0, 0];
            }
        );
        $channel->expects($this->once())->method('exchange_declare')->with('dlx', 'direct', false, true, false);
        $channel->expects($this->once())->method('queue_bind')->with('test.dlq', 'dlx', 'test');

        $consumer = null;
        $channel->method('basic_consume')->willReturnCallback(
            function ($queue, $tag, $local, $ack, $exclusive, $nowait, $callback) use (&$consumer) {
                $consumer = $callback;
                return 'consumer';
            }
        );
        $channel->expects($this->once())->method('basic_publish')->willReturnCallback(
            function (AMQPMessage $message, $exchange, $queue) {
                $this->assertSame('', $exchange);
                $this->assertSame('test.dlq', $queue);
                $payload = json_decode($message->getBody(), true);
                $this->assertSame('createBusinessPartner', $payload['method']);
                $this->assertSame(['id' => 'partner'], $payload['params']);
                $this->assertSame('Handler failed', $payload['error']['message']);
            }
        );

        $client = new Client();
        (new ReflectionProperty(Client::class, 'channel'))->setValue($client, $channel);
        $client->consume('test', function () {
            throw new \RuntimeException('Handler failed');
        });

        $this->assertSame([
            ['test', true, true, false, false],
            ['test.dlq', false, true, false, false],
        ], $declarations);

        $message = $this->getMockBuilder(AMQPMessage::class)
            ->setConstructorArgs([json_encode(['method' => 'createBusinessPartner', 'params' => ['id' => 'partner']])])
            ->onlyMethods(['ack'])
            ->getMock();
        $message->expects($this->once())->method('ack');
        $consumer($message);
    }

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
