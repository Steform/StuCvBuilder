<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * @brief Create PHPUnit mocks from static factory contexts where createMock is protected.
 */
final class TestCaseMockFactory
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function create(string $class): object
    {
        $factory = new class ('test-case-mock-factory') extends TestCase {
            /**
             * @template T of object
             * @param class-string<T> $class
             * @return T
             */
            public function build(string $class): object
            {
                return $this->createMock($class);
            }

            /**
             * @template T of object
             * @param class-string<T> $class
             * @return T
             */
            public function buildWithoutConstructor(string $class): object
            {
                return $this->getMockBuilder($class)
                    ->disableOriginalConstructor()
                    ->getMock();
            }
        };

        return $factory->build($class);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function createWithoutConstructor(string $class): object
    {
        $factory = new class ('test-case-mock-factory') extends TestCase {
            /**
             * @template T of object
             * @param class-string<T> $class
             * @return T
             */
            public function buildWithoutConstructor(string $class): object
            {
                return $this->getMockBuilder($class)
                    ->disableOriginalConstructor()
                    ->getMock();
            }
        };

        return $factory->buildWithoutConstructor($class);
    }
}
