<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NativePasskeyIntegrationTest extends TestCase
{
    public function test_digital_asset_links_identifies_the_sgc_app_and_debug_certificate(): void
    {
        $path = dirname(__DIR__, 2).'/public/.well-known/assetlinks.json';
        $statements = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('br.rzin.sgc', $statements[0]['target']['package_name']);
        $this->assertContains(
            'delegate_permission/common.get_login_creds',
            $statements[0]['relation']
        );
        $this->assertContains(
            'delegate_permission/common.handle_all_urls',
            $statements[0]['relation']
        );
        $this->assertContains(
            '05:99:61:05:3F:B9:94:94:69:E8:6F:65:CF:16:C8:F2:0B:90:03:61:1A:46:90:F0:4D:40:BB:1C:AC:FE:2D:A4',
            $statements[0]['target']['sha256_cert_fingerprints']
        );
    }

    public function test_native_bridge_passes_server_and_credential_manager_json_without_base64_reencoding(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2).'/android/app/src/main/java/br/rzin/sgc/NativeAuthPlugin.java'
        );

        $this->assertStringContainsString('new GetPublicKeyCredentialOption(requestJson)', $source);
        $this->assertStringContainsString('getAuthenticationResponseJson()', $source);
        $this->assertStringNotContainsString('Base64.', $source);
    }

    public function test_login_keeps_web_passkeys_and_selects_native_passkeys_only_on_android(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/auth/login.blade.php'
        );

        $this->assertStringContainsString('await nativeAuth.passkeySignIn', $source);
        $this->assertStringContainsString('await window.SgcPasskeys.verify', $source);
        $this->assertStringContainsString("'X-SGC-Platform': 'android'", $source);
    }
}
