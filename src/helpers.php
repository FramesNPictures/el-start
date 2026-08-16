<?php

use Fnp\ElStart\Data\PageModel;
use Fnp\ElStart\Data\SiteModel;
use Fnp\ElStart\Services\TokenService;

if (! function_exists('page')) {
    /**
     * Resolve the shared page model.
     */
    function page(): PageModel
    {
        return app(PageModel::class);
    }
}

if (! function_exists('site')) {
    /**
     * Resolve the shared site model.
     */
    function site(): SiteModel
    {
        return app(SiteModel::class);
    }
}

if (! function_exists('token')) {
    /**
     * Resolve the token service.
     */
    function token(): TokenService
    {
        return app(TokenService::class);
    }
}
