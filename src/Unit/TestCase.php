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

namespace Desarrolla2\TestBundle\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Prophecy\Prophet;

class TestCase extends BaseTestCase
{
    /**
     * @var Prophet
     */
    private $prophet;

    /**
     * @return Prophet
     */
    protected function getProphet()
    {
        if (!$this->prophet) {
            $this->prophet = new \Prophecy\Prophet();
        }

        return $this->prophet;
    }

    protected function tearDown(): void
    {
        if ($this->prophet) {
            $this->prophet = new \Prophecy\Prophet();
        }
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }
    }
}
