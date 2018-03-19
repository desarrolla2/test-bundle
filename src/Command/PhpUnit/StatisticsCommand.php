<?php

/*
 * This file is part of the desarrolla2 test bundle package
 *
 * Copyright (c) 2017-2018 Devtia Soluciones
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Daniel González <daniel@devtia.com>
 */

namespace Desarrolla2\TestBundle\Command\PhpUnit;

use Desarrolla2\Cache\Cache;
use Desarrolla2\TestBundle\Model\Key;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Route;

class StatisticsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('phpunit:statistics');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $routes = $this->getRoutes();
        $requested = $this->getRequested();
        $output->writeln(['', '<info>Tested routes</info>', '']);
        $tested = $requests = 0;
        $total = count($routes);
        foreach ($requested as $route) {
            $key = $this->getHash($route['method'], $route['route']);
            if (!array_key_exists($key, $routes)) {
                continue;
            }
            $output->writeln(sprintf('%04d. <info>%s</info> %s', $tested, $route['method'], $route['route']));
            foreach ($route['paths'] as $path) {
                $output->writeln(sprintf('   - %s', $path));
                ++$requests;
            }
            unset($routes[$key]);
            ++$tested;
        }
        $output->writeln(['', '<error>Pending routes</error>', '']);
        $pending = 0;
        foreach ($routes as $route) {
            ++$pending;
            $output->writeln(
                sprintf('%04d. <info>%s</info> %s', $pending + $tested, $route['method'], $route['route'])
            );
        }
        $testedPercentage = 100 * $tested / $total;
        $pendingPercentage = 100 - $testedPercentage;
        $color = $this->getColor($testedPercentage);

        $output->writeln(['', '',]);

        $table = new Table($output);
        $table
            ->setHeaders(['name', 'number', 'percentage'])
            ->setRows(
                [
                    ['Total requests', number_format($requests), ''],
                    [
                        'Tested routes',
                        number_format($tested),
                        sprintf('<fg=white;bg=%s>(%s%%)</>', $color, number_format($testedPercentage, 2)),
                    ],
                    [
                        'Pending routes',
                        number_format($pending),
                        sprintf('<fg=white;bg=%s>(%s%%)</>', $color, number_format($pendingPercentage, 2)),
                    ],
                ]
            );
        $table->render();

        $output->writeln(['', '',]);
    }

    /**
     * @param $serviceName
     *
     * @return object
     */
    private function get($serviceName)
    {
        return $this->getContainer()->get($serviceName);
    }

    /**
     * @return Cache
     */
    private function getCache()
    {
        return $this->get('desarrolla2.cache');
    }

    /**
     * @return string
     */
    private function getCacheKey(): string
    {
        return Key::CACHE;
    }

    /**
     * @param float $testedPercentage
     *
     * @return string
     */
    private function getColor($testedPercentage)
    {
        if ($testedPercentage > 75) {
            return 'green';
        }
        if ($testedPercentage > 50) {
            return 'yellow';
        }

        return 'red';
    }

    /**
     * @param string $method
     * @param string $routeName
     * @return string
     */
    private function getHash(string $method, string $routeName): string
    {
        return sprintf('%s%s', $routeName, $method);
    }

    /**
     * @return array
     */
    private function getIgnoredRoutePatterns()
    {
        return ['sonata_admin_[\w\_]', 'liip_imagine_[\w\_]', 'fos_user_[\w\_]', '_twig_error_test'];
    }

    /**
     * @return array
     */
    private function getRequested()
    {
        $requested = $this->getCache()->get($this->getCacheKey());
        if (!$requested) {
            return [];
        }

        ksort($requested);

        return $requested;
    }

    /**
     * @return array
     */
    private function getRoutes(): array
    {
        $router = $this->get('router');
        $collection = $router->getRouteCollection();
        $routes = [];
        /**
         * @var string $routeName
         * @var Route  $route
         */
        foreach ($collection as $routeName => $route) {
            if ($this->shouldRouteNameBeIgnored($routeName)) {
                continue;
            }
            $methods = $route->getMethods();
            if (!count($methods)) {
                $methods = ['GET', 'POST'];
            }
            foreach ($methods as $method) {
                $routes[$this->getHash($method, $routeName)] = ['route' => $routeName, 'method' => $method];
            }
        }

        ksort($routes);

        return $routes;
    }

    /**
     * @param $routeName
     * @return bool
     */
    private function shouldRouteNameBeIgnored($routeName): bool
    {
        $ignoredRoutePatterns = $this->getIgnoredRoutePatterns();
        foreach ($ignoredRoutePatterns as $pattern) {
            if (preg_match(sprintf('#%s#', $pattern), $routeName)) {
                return true;
            }
        }

        return false;
    }
}
