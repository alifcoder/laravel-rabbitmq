<?php

namespace Alif\LaravelRabbitmq\Tests;

use App\Console\Commands\RabbitConsume;
use Alif\LaravelRabbitmq\Facades\Rabbit;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once __DIR__ . '/../examples/UserService.php';
require_once __DIR__ . '/../examples/dto/UserCreateDto.php';
require_once __DIR__ . '/../examples/dto/UserGetDto.php';
require_once __DIR__ . '/../examples/RabbitHandler.php';
require_once __DIR__ . '/../examples/Console/Commands/RabbitConsume.php';

class EndToEndTest extends TestCase
{
    private const QUEUE = 'test_end_to_end';

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pcntl') || !extension_loaded('posix')) {
            $this->markTestSkipped('pcntl/posix extensions are required to fork a consumer process.');
        }

        if (!@fsockopen('127.0.0.1', 5672, timeout: 1)) {
            $this->markTestSkipped('RabbitMQ is not reachable on localhost:5672.');
        }
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('rabbitmq.default_queue', self::QUEUE);
    }

    public function test_publish_is_consumed_and_dispatched_via_the_container(): void
    {
        $pid = $this->forkConsumer();

        usleep(300_000);
        Rabbit::publish(method: 'createUser', params: ['email' => 'a@b.com', 'name' => 'Ada']);
        usleep(500_000);

        $this->stopConsumer($pid);

        $this->assertSame(0, $this->queueMessageCount(), 'Published message was not picked up by the consumer.');
    }

    public function test_rpc_request_round_trips_through_the_container_resolved_handler(): void
    {
        $pid = $this->forkConsumer();
        usleep(300_000);

        try {
            $result = Rabbit::request(method: 'getUser', params: ['id' => '5'])->getResult();
        } finally {
            $this->stopConsumer($pid);
        }

        $this->assertSame(['id' => '5', 'name' => 'John Doe', 'email' => 'test@mail.com'], $result);
    }

    public function test_rabbit_helper_publish_is_consumed(): void
    {
        $pid = $this->forkConsumer();

        usleep(300_000);
        rabbit('createUser', ['email' => 'a@b.com', 'name' => 'Ada'], self::QUEUE)->publish();
        usleep(500_000);

        $this->stopConsumer($pid);

        $this->assertSame(0, $this->queueMessageCount(), 'Published message was not picked up by the consumer.');
    }

    public function test_rabbit_helper_rpc_request_round_trips(): void
    {
        $pid = $this->forkConsumer();
        usleep(300_000);

        try {
            $result = rabbit('getUser', ['id' => '5'], self::QUEUE)->getResult();
        } finally {
            $this->stopConsumer($pid);
        }

        $this->assertSame(['id' => '5', 'name' => 'John Doe', 'email' => 'test@mail.com'], $result);
    }

    private function forkConsumer(): int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Could not fork a consumer process.');
        }

        if ($pid === 0) {
            $this->app->call([new RabbitConsume(), 'handle']);
            exit(0);
        }

        return $pid;
    }

    private function stopConsumer(int $pid): void
    {
        posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
    }

    /**
     * Live "messages ready" count straight from the broker via a passive
     * queue.declare, on a fresh connection separate from the consumer's.
     */
    private function queueMessageCount(): int
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel    = $connection->channel();

        [, $messageCount] = $channel->queue_declare(self::QUEUE, true, true, false, false);

        $channel->close();
        $connection->close();

        return $messageCount;
    }
}
