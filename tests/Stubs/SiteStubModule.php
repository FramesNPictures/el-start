<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElModule\ElModule;
use Fnp\ElStart\Data\SiteModel;
use Fnp\ElStart\Features\ModuleSite;

class SiteStubModule extends ElModule
{
    use ModuleSite;

    public function defineSite(SiteModel $site): void
    {
        $site->name = 'Example';
        $site->url = 'https://example.test';
        $site->owner = 'Example Ltd';
        $site->social = ['x' => 'https://x.com/example'];
    }
}
