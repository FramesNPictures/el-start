<?php

namespace Fnp\ElStart\Features;

use Fnp\ElStart\Data\SiteModel;

trait ModuleSite
{
    /**
     * Fill in the shared site model.
     */
    abstract public function defineSite(SiteModel $site): void;

    public function bootModuleSiteFeature(SiteModel $site): void
    {
        $this->defineSite($site);
    }
}
