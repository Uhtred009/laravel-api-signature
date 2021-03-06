<?php

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Mitoop\ApiSignature\ClientManager;
use Tests\Utils\TestingSignatureLogger;
use Mitoop\ApiSignature\ClientServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected $testingClient = 'testing';
    protected $testingHeaders = ['X-Foo' => 'Bar'];
    protected $testingBody;

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return string[]
     */
    protected function getPackageProviders($app)
    {
        return [
            ClientServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'ApiClient' => \Mitoop\ApiSignature\Facades\Client::class
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->loadDeferredProvider(ClientServiceProvider::class);

        $this->testingBody = json_encode(['name' => 'mitoop']);

        $app->singleton(ClientManager::class, function ($app) {
            $mock = new MockHandler([
                new Response(200, $this->testingHeaders, $this->testingBody),
                new Response(400, $this->testingHeaders),
                new Response(500, $this->testingHeaders),
            ]);
            $handler = HandlerStack::create($mock);

            return new ClientManager($app, new Client(['handler' => $handler]));
        });

        $app['config']->set('api-signature.default', $this->testingClient);

        $app['config']->set("api-signature.clients.{$this->testingClient}", [
            'app_id'     => 'testing_app_id',
            'app_secret' => 'testing_app_secret',
            'scheme'     => '',
            'host'       => 'api.testing',
            'ip'         => '',
            'port'       => '',
        ]);

        $app['config']->set('api-signature.logger_handler', TestingSignatureLogger::class);

        $app['log-mock'] = collect();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     * @throws \ReflectionException
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
