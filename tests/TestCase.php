<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use TransactionCleanupTrait;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransactionCleanup();
    }
    
    protected function tearDown(): void
    {
        $this->tearDownTransactionCleanup();
        parent::tearDown();
    }
}
