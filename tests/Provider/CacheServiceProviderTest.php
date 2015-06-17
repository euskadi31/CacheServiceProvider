<?php
/*
 * This file is part of the CacheServiceProvider.
 *
 * (c) Axel Etcheverry <axel@etcheverry.biz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Euskadi31\Silex\Provider;

use Euskadi31\Silex\Provider\CacheServiceProvider;
use Silex\Application;
use Doctrine\Common\Cache;
use Doctrine\Common\Cache\ArrayCache;

class CacheProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultCache()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider);

        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\ArrayCache', $app['cache']);

        $this->assertEquals($app['cache'], $app['cache']);
    }

    public function testDefaultCacheWithOptions()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => [
                    'driver' => 'array'
                ]
            ]
        ]);

        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\ArrayCache', $app['cache']);

        $this->assertEquals($app['cache'], $app['cache']);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testBadDriverCallable()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => [
                    'driver' => function() {
                        return new \stdClass;
                    }
                ]
            ]
        ]);

        $app['cache'];
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testBadDriverClassName()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => [
                    'driver' => '\\stdClass'
                ]
            ]
        ]);

        $app['cache'];
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testBadDriver()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => [
                    'driver' => 'foo'
                ]
            ]
        ]);

        $app['cache'];
    }

    public function testCacheNamespace()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => [
                    'driver' => 'array',
                    'namespace' => 'foo'
                ]
            ]
        ]);

        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\ArrayCache', $app['cache']);

        $this->assertEquals($app['cache'], $app['cache']);
    }

    public function testMultipleCaches()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => [
                    'driver' => 'array'
                ],
                'foo' => [
                    'driver' => '\\Doctrine\\Common\\Cache\\FilesystemCache',
                    'directory' => '/tmp'
                ]
            ]
        ]);

        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\FilesystemCache', $app['caches']['foo']);
        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\ArrayCache', $app['cache']);
    }

    public function testCacheFactory()
    {
        $app = new Application;

        $app->register(new CacheServiceProvider, [
            'cache.options' => [
                'default' => 'array'
            ]
        ]);

        $app['caches'] = $app->extend('caches', function($caches) use ($app) {
            $caches['foo'] = $app['cache.factory']([
                'driver' => 'array'
            ]);

            $caches['bar'] = $app['cache.factory']([
                'driver' => function() {
                    return new ArrayCache;
                }
            ]);

            return $caches;
        });

        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\ArrayCache', $app['caches']['foo']);
        $this->assertInstanceOf('\\Doctrine\\Common\\Cache\\ArrayCache', $app['caches']['bar']);
    }
}
