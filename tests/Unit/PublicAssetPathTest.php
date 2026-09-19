<?php

namespace Tests\Unit;

use App\Helpers\Help;
use Tests\TestCase;

class PublicAssetPathTest extends TestCase
{
    protected function tearDown(): void
    {
        Help::flushPublicWebPrefix();
        config(['app.public_web_prefix' => null]);
        parent::tearDown();
    }

    public function test_standard_public_docroot_strips_public_prefix(): void
    {
        config(['app.public_web_prefix' => '']);
        Help::flushPublicWebPrefix();

        $this->assertSame('', Help::publicWebPrefix());
        $this->assertSame(
            '/img/branding/app_logo_1789854876_764b0558.jpg',
            Help::normalizePublicPath('/public/img/branding/app_logo_1789854876_764b0558.jpg')
        );
        $this->assertSame(
            '/img/branding/app_logo_1789854876_764b0558.jpg',
            Help::normalizePublicPath('/img/branding/app_logo_1789854876_764b0558.jpg')
        );
        $this->assertSame(
            'https://mazad.kaml-kamal.intellij-app.com/img/branding/logo.jpg',
            Help::normalizePublicPath('https://mazad.kaml-kamal.intellij-app.com/public/img/branding/logo.jpg')
        );
    }

    public function test_project_root_docroot_keeps_public_prefix(): void
    {
        config(['app.public_web_prefix' => '/public']);
        Help::flushPublicWebPrefix();

        $this->assertSame('/public', Help::publicWebPrefix());
        $this->assertSame(
            '/public/img/branding/logo.jpg',
            Help::normalizePublicPath('/img/branding/logo.jpg')
        );
        $this->assertSame(
            '/public/img/receipt/main.png',
            Help::normalizePublicPath('/public/img/receipt/main.png')
        );
    }

    public function test_detects_public_folder_as_document_root(): void
    {
        config(['app.public_web_prefix' => null]);
        $_SERVER['DOCUMENT_ROOT'] = public_path();
        Help::flushPublicWebPrefix();

        $this->assertSame('', Help::publicWebPrefix());
        $this->assertSame('/img/branding/x.jpg', Help::normalizePublicPath('/public/img/branding/x.jpg'));
    }
}
