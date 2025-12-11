<?php

namespace BareMetalPHP\Contracts;

use BareMetalPHP\Http\Request;
use BareMetalPHP\Http\Response;

interface Middleware
{
    /**
     * Handle an incoming HTTP request.
     *
     * @param Request $request
     * @param callable $next (Request)
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}