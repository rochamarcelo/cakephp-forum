<?php

/**
 * Copyright 2010 - 2019, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2018, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */


namespace CakeDC\Forum\Test\App;


use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;

class Application extends \Cake\Http\BaseApplication
{
    /**
     * Setup the middleware queue
     *
     * @param \Cake\Http\MiddlewareQueue $middleware The middleware queue to set in your App Class
     *
     * @return \Cake\Http\MiddlewareQueue
     */

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            ->add(ErrorHandlerMiddleware::class)
            ->add(AssetMiddleware::class)
            ->add(CsrfProtectionMiddleware::class)
            ->add(new RoutingMiddleware($this, null));

        return $middlewareQueue;
    }

    /**
     * {@inheritDoc}
     */
    public function bootstrap(): void
    {
        $this->addPlugin(\CakeDC\Forum\Plugin::class);
    }
}