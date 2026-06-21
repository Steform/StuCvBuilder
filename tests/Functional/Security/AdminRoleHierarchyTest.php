<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Ensures ROLE_ADMIN implies CV admin roles via security.role_hierarchy.
 */
final class AdminRoleHierarchyTest extends KernelTestCase
{
    /**
     * @brief Assert administrator token is granted tile manager role from hierarchy.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-27
     * @author Stephane H.
     */
    public function testAdministratorInheritsTileManagerRole(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $user = (new User())
            ->setEmail('admin-hierarchy-func@localhost')
            ->setPassword('not-used')
            ->setPseudonym('hierarchy')
            ->setRoles(['ROLE_ADMIN']);

        $token = new PostAuthenticationToken($user, 'main', $user->getRoles());
        $container->get(TokenStorageInterface::class)->setToken($token);

        $auth = $container->get(AuthorizationCheckerInterface::class);

        self::assertTrue($auth->isGranted('ROLE_TUILE'), 'ROLE_ADMIN should imply ROLE_TUILE');
        self::assertTrue($auth->isGranted('ROLE_USER'), 'ROLE_ADMIN should imply ROLE_USER');
    }
}
