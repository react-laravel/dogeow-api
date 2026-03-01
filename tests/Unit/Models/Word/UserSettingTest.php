<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\UserSetting;
use Tests\TestCase;

class UserSettingTest extends TestCase
{
    public function test_user_setting_has_fillable(): void
    {
        $setting = new UserSetting;

        $this->assertContains('user_id', $setting->getFillable());
    }

    public function test_user_setting_has_casts(): void
    {
        $setting = new UserSetting;

        $this->assertArrayHasKey('id', $setting->getCasts());
    }
}
