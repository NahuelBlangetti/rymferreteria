<?php

namespace Tests;

use App\Models\PaymentSurchargeSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PaymentSurchargeSetting::forgetCache();
    }
}
