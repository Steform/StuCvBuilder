<?php

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;

/**
 * Sprint 20: profile bubble visibility and no profile link in default component; files index without admin cross-owner shortcut.
 */
class ProfileBubbleSprint20Test extends TestCase
{
    /**
     * @brief Ensure floating actions account dropdown keeps profile entry.
     * @param void No input parameter.
     * @return void
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function testFloatingActionsAccountSectionContainsProfileLink(): void
    {
        $path = dirname(__DIR__, 3).'/templates/components/_floating_actions.html.twig';
        $source = is_readable($path) ? (string) file_get_contents($path) : '';

        self::assertStringContainsString("path('app_profile_show')", $source);
        self::assertStringContainsString('canShowAccountAction', $source);
    }

    /**
     * @brief Ensure floating actions account dropdown exposes logout and drops idle home status.
     * @param void No input parameter.
     * @return void
     * @date 2026-05-03
     * @author Stephane H.
     */
    public function testFloatingActionsAccountSectionContainsLogoutAndOmitsHomeLabel(): void
    {
        $path = dirname(__DIR__, 3).'/templates/components/_floating_actions.html.twig';
        $source = is_readable($path) ? (string) file_get_contents($path) : '';

        self::assertStringContainsString("path('app_logout')", $source);
        self::assertStringContainsString('floating.actions.logout.aria', $source);
        self::assertStringNotContainsString('profile.bubble.home_label', $source);
    }
}
