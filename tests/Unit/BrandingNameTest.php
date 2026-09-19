<?php

namespace Tests\Unit;

use App\Support\Branding;
use Tests\TestCase;

class BrandingNameTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'app.name' => 'Laravel',
            'app.product_name' => null,
        ]);
        parent::tearDown();
    }

    public function test_placeholder_laravel_title_uses_product_brand(): void
    {
        config([
            'app.name' => 'Laravel',
            'app.product_name' => 'KAML KAMAL',
        ]);

        $this->assertSame('KAML KAMAL', Branding::name());
        $this->assertSame('KAML KAMAL', Branding::resolveName('Laravel'));
        $this->assertSame('KAML KAMAL', Branding::resolveName(''));
        $this->assertSame('شركة النور', Branding::resolveName('شركة النور'));
    }
}
