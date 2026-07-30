<?php

namespace Alif\LaravelRabbitmq\Tests;

use Alif\LaravelRabbitmq\Facades\Rabbit as RabbitFacade;
use Alif\LaravelRabbitmq\Rabbit;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged_with_defaults(): void
    {
        $this->assertSame('localhost', config('rabbitmq.host'));
        $this->assertSame(5672, config('rabbitmq.port'));
        $this->assertSame('guest', config('rabbitmq.user'));
        $this->assertSame(10, config('rabbitmq.rpc_timeout'));
    }

    public function test_rabbit_is_bound_as_a_singleton(): void
    {
        $this->assertSame($this->app->make(Rabbit::class), $this->app->make(Rabbit::class));
    }

    public function test_facade_resolves_to_the_bound_singleton(): void
    {
        $this->assertSame($this->app->make(Rabbit::class), RabbitFacade::getFacadeRoot());
    }

    public function test_consume_command_is_registered(): void
    {
        $this->artisan('list')->assertSuccessful();
        $this->assertTrue($this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all()['rabbitmq:consume'] !== null);
    }

    public function test_config_publishes_to_the_app_config_path(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
                \Alif\LaravelRabbitmq\RabbitmqServiceProvider::class,
                'rabbitmq-config'
        );

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('config/rabbitmq.php', array_key_first($paths));
    }
}
