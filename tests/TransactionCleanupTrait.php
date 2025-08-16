<?php

namespace Tests;

trait TransactionCleanupTrait
{
    protected function setUpTransactionCleanup(): void
    {
        // Ensure no active transactions before starting tests
        if (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
    }
    
    protected function tearDownTransactionCleanup(): void
    {
        // Clean up any pending transactions
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
    }
}