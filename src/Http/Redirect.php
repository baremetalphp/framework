<?php

namespace BareMetalPHP\Http;

use BareMetalPHP\Application;
use BareMetalPHP\Routing\Router;
use BareMetalPHP\Support\Session;
class Redirect extends Response
{
    /**
     * Create a Redirect
     * @param string $url
     * @param int $status
     * @param array $headers
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        // Ensure Location header is always set
        $headers['Location'] = $uri;

        // Empty body for redirects
        parent::__construct('', $status, $headers);
    }

    /**
     * Redirect to a given URL
     * @param string $url
     * @param int $status
     * @param array $headers
     * @return Redirect
     */
    public static function to(string $url, int $status = 302, array $headers = []): static
    {
        return new static($url, $status, $headers);
    }

    /**
     * Redirect back to the previous URL (Referer header / HTTP_REFERER)
     * 
     * @param Request|null $request
     * @param int $status
     * @param array $headers
     * @return Redirect
     */
    public static function back(?Request $request = null, int $status = 302, array $headers = []): static
    {
        $referer = null;

        if ($request !== null) {
            $referer = $request->header('Referer');
        }

        if ($referer === null) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        }

        return new static($referer, $status, $headers);
    }

    /**
     * Redirect to a named route.
     * @param string $name
     * @param array $params
     * @param int $status
     * @param array $headers
     * @throws \RuntimeException
     * @return Redirect
     */
    public static function route(string $name, array $params = [], int $status = 302, array $headers = []): static
    {
        $app = Application::getInstance();

        if (!$app) {
            throw new \RuntimeException('Application instance not available when generating redirect route URL.');
        }

        /** @var Router $router */
        $router = $app->make(Router::class);

        $url = $router->route($name, $params);

        return new static($url, $status, $headers);
    }

    /**
     * Flash data to the session for the next request.
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function with(string $key, mixed $value): static
    {
        Session::flash($key, $value);

        return $this;
    }

    /**
     * Flash validation / error messages.
     * @param array $errors
     * @param string $key
     * @return Redirect
     */
    public function withErrors(array $errors, string $key = 'errors'): static
    {
        Session::flash($key, $errors);

        return $this;
    }
}