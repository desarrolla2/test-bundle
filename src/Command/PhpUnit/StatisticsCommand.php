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

use CoreBundle\Command\AbstractCommand;
use Desarrolla2\Cache\Cache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatisticsCommand extends AbstractCommand
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
        $tested = 0;
        $total = count($routes);
        foreach ($requested as $items) {
            foreach ($items as $item) {
                $key = array_search($item['route'], $routes, true);
                if (false !== $key) {
                    unset($routes[$key]);
                    ++$tested;
                }
                $output->writeln(
                    sprintf('%04d. <info>%s</info> %s %s', $tested, $item['method'], $item['route'], $item['path'])
                );
            }
        }
        $output->writeln(['', '<error>Pending routes</error>', '']);
        $pending = 0;
        foreach ($routes as $route) {
            ++$pending;
            $output->writeln(sprintf('%04d. %s', $pending + $tested, $route));
        }
        $testedPercentage = round(100 * $tested / $total, 2);
        $pendingPercentage = 100 - $testedPercentage;
        $color = $this->getColor($testedPercentage);
        $output->writeln(
            [
                '',
                sprintf('Tested:  %d routes <fg=white;bg=%s>(%s%%)</>', $tested, $color, $testedPercentage),
                sprintf('Pending: %d routes <fg=white;bg=%s>(%s%%)</>', $pending, $color, $pendingPercentage),
                '',
            ]
        );
    }

    /**
     * @return Cache
     */
    protected function getCache()
    {
        return $this->get('desarrolla2.cache');
    }

    /**
     * @return string
     */
    protected function getCacheKey(): string
    {
        return 'test_executed_routes';
    }

    /**
     * @param float $testedPercentage
     *
     * @return string
     */
    protected function getColor($testedPercentage)
    {
        if ($testedPercentage > 75) {
            return 'green';
        }
        if ($testedPercentage > 50) {
            return 'yelow';
        }

        return 'red';
    }

    /**
     * @return array
     */
    protected function getRequested()
    {
        $requested = $this->getCache()->get($this->getCacheKey());
        if (!$requested) {
            return [];
        }

        return $requested;
    }

    /**
     * @return array
     */
    protected function getRoutes(): array
    {
        $router = $this->get('router');
        $collection = $router->getRouteCollection();
        $routes = [];
        foreach ($collection as $routeName => $route) {
            $routes[] = $routeName;
        }
        sort($routes);

        return $routes;
    }
}
