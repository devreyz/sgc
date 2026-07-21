<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserAvatarSourceTest extends TestCase
{
    #[DataProvider('avatarSources')]
    public function test_it_identifies_only_locally_stored_avatars(?string $avatar, bool $expected): void
    {
        $user = new User(['avatar' => $avatar]);

        $this->assertSame($expected, $user->hasLocallyStoredAvatar());
    }

    public static function avatarSources(): array
    {
        return [
            'empty' => [null, false],
            'google' => ['https://lh3.googleusercontent.com/avatar', false],
            'remote http' => ['http://example.test/avatar.jpg', false],
            'local storage' => ['avatars/member.webp', true],
        ];
    }
}
