<?php

declare(strict_types=1);

namespace BareMetalPHP\Runtime;

use BareMetalPHP\Application;
use BareMetalPHP\Http\Kernel as HttpKernel;
use BareMetalPHP\Http\Request;
use BareMetalPHP\Http\Response;

final class HttpRuntime implements RuntimeInterface
{
    /**
     * Optional context (env, debug flags, etc.).
     * @param array $context
     */
    public function __construct(
        protected array $context = []
    ) {}

    /**
     * Run the HTTP application for the current request.
     * 
     * @param mixed $app BareMetalPHP\Application
     * @return int
     */
    public function run(mixed $app): int
    {
        $kernel = $this->resolveKernel($app);

        // Build Request from PHP superglobals (Request::fromGlobals exists in your codebase)
        $request = Request::fromGlobals();

        $response = $kernel->handle($request);

        $this->sendResponse($response);

        return 0;
    }

    /**
     * Normalize the "app" into an HttpKernel instance.
     * 
     * @param mixed $app
     * @throws \InvalidArgumentException
     * @return HttpKernel
     */
    protected function resolveKernel(mixed $app): HttpKernel
    {
        if ($app instanceof HttpKernel) {
            return $app;
        }

        if ($app instanceof Application)
        {
            /** @var HttpKernel $kernel */
            $kernel = $app->make(HttpKernel::class);
            return $kernel;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'HttpRuntime expects an Application or Http\\Kernel instance, %s given.',
                is_object($app) ? get_class($app) : gettype($app)
            )
            );
    }

    /**
     * Send the Response via PHP's SAPI functions.
     * @param Response $response
     * @return void
     */
    protected function sendResponse(Response $response)
    {
        // Status code
        http_response_code($response->getStatusCode());
        
        // Headers
        foreach ($response->getHeaders() as $name => $value) {
            if (is_array($value)) {
                // Multiple header values (e.g. Set-Cookie)
                foreach ($value as $v) {
                    header($name . ': ' . $v, false);
                }
            } else {
                header($name .': ' . $value, true);
            }
        }

        // Body
        echo $response->getBody();
    }
}