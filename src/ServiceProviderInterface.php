<?php
namespace Fastra;

interface ServiceProviderInterface
{
    /**
     * @param \Fastra\Application $app
     * @return void
     */
    public function register(Application $app);

    /**
     * @param \Fastra\Application $app
     * @return void
     */
    public function boot(Application $app);
}