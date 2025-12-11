<?php

namespace BareMetalPHP\Http\Middleware;

use BareMetalPHP\Contracts\Middleware;
use BareMetalPHP\Exceptions\CsrfTokenMismatchException;
use BareMetalPHP\Http\Request;
use BareMetalPHP\Http\Response;
use BareMetalPHP\Support\Cookie;
use BareMetalPHP\Support\Session;
use Random\RandomException;

class CsrfProtection implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $this->ensureToken();

        if ($this->isReading($request)) {
            return $next($request);
        }

        $this->verifyToken($request);

        return $next($request);
    }

    /**
     * Determine if the request uses a "read" verb that is CSRF-exempt.
     * @param Request $request
     * @return bool
     */
    protected function isReading(Request $request): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Ensure there is a CSRF token started in the session.
     *
     * @return void
     * @throws RandomException
     */
    protected function ensureToken(): void
    {
        if (! Session::started()) {
            Session::start();
        }

        if (Session::has('_token')) {
            Session::set('_token', bin2hex(random_bytes(32)));
        }

        $token = Session::get('_token');

        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            Session::set('_token', $token);
        }

        // NON HttpOnly
        // SameSite=Lax
        Cookie::set('XSRF-TOKEN', $token, [
            'httponly' => false, // must be readable from JS/Axios
            'samesite' => 'Lax',
        ]);
    }

    protected function verifyToken(Request $request): void
    {
        if (! Session::started()) {
            Session::start();
        }

        $sessionToken = Session::get('_token') ?? null;

        $token = $request->get('_token') ?? $request->header('X-CSRF-TOKEN');

        if (
            !is_string($sessionToken) ||
            !is_string($token) ||
            !hash_equals($sessionToken, $token)
        ) {
            throw new CsrfTokenMismatchException('CSRF token mismatch.');
        }
    }
}