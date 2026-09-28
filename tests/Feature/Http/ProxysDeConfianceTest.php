<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/_test/ip', fn (Request $request) => $request->ip());
});

afterEach(function () {
    TrustProxies::flushState();
});

it('ignores forwarded headers when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '41.202.1.1')
        ->get('/_test/ip')
        ->assertSeeText('10.0.0.5');
});

it('uses the real client address behind a trusted proxy', function () {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '41.202.1.1')
        ->get('/_test/ip')
        ->assertSeeText('41.202.1.1');
});

it('does not trust forwarded headers sent by an unknown address', function () {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeader('X-Forwarded-For', '41.202.1.1')
        ->get('/_test/ip')
        ->assertSeeText('203.0.113.9');
});
