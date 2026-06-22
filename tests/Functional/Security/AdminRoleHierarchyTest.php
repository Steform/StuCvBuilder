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
        self::assertTrue($auth->isGranted('ROLE_CV_EDIT'), 'ROLE_ADMIN should imply ROLE_CV_EDIT');
        self::assertTrue($auth->isGranted('ROLE_CV_CONSULT'), 'ROLE_ADMIN should imply ROLE_CV_CONSULT');
        self::assertTrue($auth->isGranted('ROLE_USER'), 'ROLE_ADMIN should imply ROLE_USER');
    }

    /**
     * @brief Assert CV editor token inherits consultant bypass and user role from hierarchy.
     * @param void No input parameter.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function testCvEditorInheritsConsultantRole(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $user = (new User())
            ->setEmail('cv-editor-hierarchy-func@localhost')
            ->setPassword('not-used')
            ->setPseudonym('cv-editor')
            ->setRoles(['ROLE_CV_EDIT']);

        $token = new PostAuthenticationToken($user, 'main', $user->getRoles());
        $container->get(TokenStorageInterface::class)->setToken($token);

        $auth = $container->get(AuthorizationCheckerInterface::class);

        self::assertTrue($auth->isGranted('ROLE_CV_CONSULT'), 'ROLE_CV_EDIT should imply ROLE_CV_CONSULT');
        self::assertTrue($auth->isGranted('ROLE_USER'), 'ROLE_CV_EDIT should imply ROLE_USER');
        self::assertFalse($auth->isGranted('ROLE_ADMIN'), 'ROLE_CV_EDIT must not imply ROLE_ADMIN');
    }
}
