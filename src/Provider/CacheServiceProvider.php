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

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\Common\Cache\Cache;
use UnexpectedValueException;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Doctrine Cache integration for Silex.
 *
 * @author Axel Etcheverry <axel@etcheverry.biz>
 */
class CacheServiceProvider implements ServiceProviderInterface
{
    /**
     * @param  Pimple\Container $app
     * @return void
     */
    public function register(Container $app)
    {
        $app['cache.options'] = [
            'default' => [
                'driver' => 'array'
            ]
        ];

        $app['cache.factory'] = $app->protect(function($options) {

            return function() use ($options) {

                if (is_callable($options['driver'])) {
                    $cache = $options['driver']();

                    if (!$cache instanceof Cache) {
                        throw new UnexpectedValueException(sprintf(
                            '"%s" does not implement \\Doctrine\\Common\\Cache\\Cache', get_class($cache)
                        ));
                    }

                    return $cache;
                }

                // If the driver name appears to be a fully qualified class name, then use
                // it verbatim as driver class. Otherwise look the driver up in Doctrine's
                // builtin cache providers.
                if (substr($options['driver'], 0, 1) === '\\') {
                    $driverClass = $options['driver'];
                } else {
                    $driverClass = '\\Doctrine\\Common\\Cache\\' . str_replace(
                        ' ',
                        '',
                        ucwords(str_replace('_', ' ', $options['driver']))
                    ) . 'Cache';

                    if (!class_exists($driverClass)) {
                        throw new InvalidArgumentException(sprintf(
                            'Driver "%s" (%s) not found.',
                            $options['driver'],
                            $driverClass
                        ));
                    }
                }

                $class = new ReflectionClass($driverClass);
                $constructor = $class->getConstructor();
                $newInstanceArguments = [];

                if (null !== $constructor) {
                    foreach ($constructor->getParameters() as $parameter) {
                        if (isset($options[$parameter->getName()])) {
                            $value = $options[$parameter->getName()];
                        } else {
                            $value = $parameter->getDefaultValue();
                        }
                        $newInstanceArguments[] = $value;
                    }
                }

                // Workaround for PHP 5.3.3 bug #52854 <https://bugs.php.net/bug.php?id=52854>
                if (count($newInstanceArguments) > 0) {
                    $cache = $class->newInstanceArgs($newInstanceArguments);
                } else {
                    $cache = $class->newInstanceArgs();
                }

                if (!$cache instanceof Cache) {
                    throw new UnexpectedValueException(sprintf(
                        '"%s" does not implement \\Doctrine\\Common\\Cache\\Cache', $driverClass
                    ));
                }

                if (isset($options['namespace']) && is_callable([$cache, 'setNamespace'])) {
                    $cache->setNamespace($options['namespace']);
                }

                return $cache;
            };
        });

        $app['cache'] = function($app) {
            $factory = $app['cache.factory']($app['cache.options']['default']);

            return $factory();
        };

        $app['caches'] = function($app) {
            $caches = new Container;

            foreach ($app['cache.options'] as $cache => $options) {
                $caches[$cache] = $app['cache.factory']($options);
            }

            return $caches;
        };
    }
}
