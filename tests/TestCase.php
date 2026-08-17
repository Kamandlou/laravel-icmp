<?php

namespace Kamandlou\LaravelIcmp\Tests;

use Kamandlou\LaravelIcmp\IcmpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [IcmpServiceProvider::class];
    }
}
