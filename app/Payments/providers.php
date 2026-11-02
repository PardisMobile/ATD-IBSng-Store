<?php
declare(strict_types=1);

/**
 * Trusted payment-provider registration hook.
 *
 * Register additional adapter classes here (or require a local provider module
 * that does so). Each adapter must implement PaymentGateway and expose a
 * matching code(). This file is loaded by PaymentManager in web requests.
 *
 * Example:
 * require_once __DIR__ . '/examplepay.php';
 * PaymentManager::registerGateway('examplepay', ExamplePayGateway::class);
 */
