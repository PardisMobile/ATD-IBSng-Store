<?php
declare(strict_types=1);

/**
 * Trusted payment-provider registration hook.
 *
 * Register additional adapter classes here, or require a local provider module
 * that registers its adapter. Each adapter must implement PaymentGateway and
 * expose a code() matching the registration key.
 *
 * This file is loaded by PaymentManager for checkout, callback verification,
 * and the admin gateway settings list. It is an extension hook, not a second
 * registry: PaymentManager remains the sole registry and dispatcher.
 *
 * Example:
 * require_once __DIR__ . '/examplepay.php';
 * PaymentManager::registerGateway('examplepay', ExamplePayGateway::class);
 */
