<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserSecurityModelTest extends TestCase
{
    /**
     * @brief Ensure user identifier uses email.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testUserIdentifierUsesEmail(): void
    {
        $user = (new User())->setEmail('admin@example.com');

        self::assertSame('admin@example.com', $user->getUserIdentifier());
    }

    /**
     * @brief Ensure ROLE_USER is always present.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testRoleUserAlwaysPresent(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }
}
