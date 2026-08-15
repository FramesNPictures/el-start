<?php

use Fnp\ElStart\Data\PageModel;
use Fnp\ElStart\Data\SiteModel;
use Fnp\ElStart\Services\TokenService;
use Fnp\ElStart\Services\VaultService;

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

if (! function_exists('vault')) {
    /**
     * Resolve the vault service.
     */
    function vault(): VaultService
    {
        return app(VaultService::class);
    }
}
