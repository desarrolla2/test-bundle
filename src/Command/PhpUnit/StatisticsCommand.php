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
use Desarrolla2\Timer\Formatter\Human;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
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
        $filesystem = new Filesystem();
        $human = new Human();

        $routes = $this->getRoutes();
        $requested = $this->getRequested();
        $classes = $this->getRequested();
        $totalTime = $testedRoutes = $totalRequest = $pendingRoutes = $totalTime = 0;
        $totalRoutes = count($routes);
        $numberOfDigits = strlen((string)$totalRoutes);

        $filesystem->remove($this->getFileForRoutesPerformance());
        $filesystem->touch($this->getFileForRoutesPerformance());

        $filesystem->remove($this->getFileForRoutesTested());
        $filesystem->touch($this->getFileForRoutesTested());

        foreach ($requested as $route) {
            ++$testedRoutes;
            $key = $this->getHash($route['method'], $route['route']);
            if (!array_key_exists($key, $routes)) {
                $totalRoutes++;
                $routes[$key] = ['route' => $route['route'], 'method' => $route['method']];
            }
            $count = count($route['paths']);
            if (!$count) {
                continue;
            }
            $filesystem->appendToFile(
                $this->getFileForRoutesTested(),
                sprintf(
                    '%s. %s %s%c',
                    str_pad($testedRoutes, $numberOfDigits, '0', STR_PAD_LEFT),
                    str_pad($route['method'], 4, ' '),
                    $route['route'],
                    10
                )
            );
            $totalTime += $route['time'];
            $average = round($route['time'] / $count, 3);
            $filesystem->appendToFile(
                $this->getFileForRoutesPerformance(),
                sprintf(
                    '%s. %s %s ~%s%c',
                    str_pad($testedRoutes, $numberOfDigits, '0', STR_PAD_LEFT),
                    $route['method'],
                    $route['route'],
                    number_format($average, 3),
                    10
                )
            );
            foreach ($route['paths'] as $path) {
                ++$totalRequest;
                $filesystem->appendToFile(
                    $this->getFileForRoutesPerformance(),
                    sprintf('   - %s %s%c', $path['path'], number_format($path['time'], 3), 10)
                );
            }
            unset($routes[$key]);
        }

        $filesystem->remove($this->getFileForRoutesPending());
        $filesystem->touch($this->getFileForRoutesPending());
        foreach ($routes as $route) {
            ++$pendingRoutes;
            $filesystem->appendToFile(
                $this->getFileForRoutesPending(),
                sprintf(
                    '%s. %s %s%c',
                    str_pad($pendingRoutes + $testedRoutes, $numberOfDigits, '0', STR_PAD_LEFT),
                    str_pad($route['method'], 4, ' '),
                    $route['route'],
                    10
                )
            );
        }

        $filesystem->remove($this->getFileForClassesProfile());
        $filesystem->touch($this->getFileForClassesProfile());

        $classes = $this->getClasses();
        foreach ($classes as $class) {
            $filesystem->appendToFile(
                $this->getFileForClassesProfile(),
                sprintf(
                    '%s: %s%c',
                    $class['name'],
                    $human->time($class['time']),
                    10
                )
            );
            foreach ($class['tests'] as $case) {
                $filesystem->appendToFile(
                    $this->getFileForClassesProfile(),
                    sprintf(
                        ' - %s: %s%c',
                        $case['name'],
                        $human->time($case['time']),
                        10
                    )
                );
            }
            $totalTime += $class['time'];
        }

        $testedPercentage = 100 * $testedRoutes / $totalRoutes;
        $pendingPercentage = 100 - $testedPercentage;
        $averagePerRequest = $totalRequest ? $totalTime / $totalRequest : 0;
        $color = $this->getColor($testedPercentage);

        $output->writeln(['', '',]);

        $table = new Table($output);
        $table
            ->setHeaders(['name', 'number', 'percentage'])
            ->setRows(
                [
                    ['Total execution time', $human->time($totalTime), ''],
                    ['Total requests', number_format($totalRequest), ''],
                    ['Average time per request', $human->time($averagePerRequest), ''],
                    ['Total routes', number_format($totalRoutes), ''],
                    [
                        'Tested routes',
                        number_format($testedRoutes),
                        sprintf('<fg=white;bg=%s>(%s%%)</>', $color, number_format($testedPercentage, 2)),
                    ],
                    [
                        'Pending routes',
                        number_format($pendingRoutes),
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
    private function getCacheKeyForClasses(): string
    {
        return Key::CLASSES;
    }


    /**
     * @return string
     */
    private function getCacheKeyForRoutes(): string
    {
        return Key::ROUTES;
    }

    /**
     * @return array
     */
    private function getClasses(): array
    {
        $classes = $this->getCache()->get($this->getCacheKeyForClasses());
        if (!$classes) {
            return [];
        }
        usort(
            $classes,
            function ($item1, $item2) {
                if ($item1['time'] == $item2['time']) {
                    return 0;
                }

                return ($item1['time'] < $item2['time']) ? -1 : 1;
            }
        );

        return $classes;
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
     * @return string
     */
    private function getFileForClassesProfile(): string
    {
        return sprintf('%s/desarrolla2.classes.profile.txt', $this->getLogDirectory());
    }

    /**
     * @return string
     */
    private function getFileForRoutesPending(): string
    {
        return sprintf('%s/desarrolla2.routes.pending.txt', $this->getLogDirectory());
    }

    /**
     * @return string
     */
    private function getFileForRoutesPerformance(): string
    {
        return sprintf('%s/desarrolla2.routes.performance.txt', $this->getLogDirectory());
    }

    /**
     * @return string
     */
    private function getFileForRoutesTested(): string
    {
        return sprintf('%s/desarrolla2.routes.tested.txt', $this->getLogDirectory());
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
        return [
            'sonata_admin_[\w\_]',
            'liip_imagine_[\w\_]',
            'fos_user_[\w\_]',
            'fos_js_routing_[\w\_]',
            '_twig_error_test',
            '_async_event.[\w\.]',
        ];
    }

    /**
     * @return string
     */
    private function getLogDirectory(): string
    {
        return $this->get('kernel')->getLogDir();
    }

    /**
     * @return array
     */
    private function getRequested()
    {
        $requested = $this->getCache()->get($this->getCacheKeyForRoutes());
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
                $methods = ['GET'];
                if (substr($routeName, -6) == '_batch') {
                    $methods = ['POST'];
                };
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
