<?php

namespace App\Contracts;

interface TransactionManagerInterface
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(callable $callback): mixed;
}
