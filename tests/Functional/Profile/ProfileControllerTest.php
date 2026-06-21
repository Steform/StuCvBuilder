<?php

namespace App\Tests\Functional\Profile;

use App\Controller\ProfileController;
use App\Entity\User;
use App\Service\Profile\ProfileUpdateService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ProfileControllerTest extends TestCase
{
    /**
     * @brief Ensure profile page renders current user email.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testShowRendersProfileTemplate(): void
    {
        $service = $this->createMock(ProfileUpdateService::class);
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $controller = new ProfileController($service, $csrfTokenManager);
        $twig = new Environment(new ArrayLoader([
            'profile/show.html.twig' => '{{ profileUser.email }}',
        ]));
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn((new User())->setEmail('user@example.com'));

        $response = $controller->show($twig, $security);

        self::assertSame('user@example.com', (string) $response->getContent());
    }
}
