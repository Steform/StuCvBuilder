<?php

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

class ThemeBubbleComponentTest extends TestCase
{
    /**
     * @brief Ensure floating actions bar shows globe, theme, and account icons without main grid menu.
     * @return void
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function testFloatingActionsRenderCurrentControls(): void
    {
        $root = dirname(__DIR__, 3);
        $twig = new Environment(new ArrayLoader([
            'components/_floating_actions.html.twig' => file_get_contents($root.'/templates/components/_floating_actions.html.twig') ?: '',
            'test.twig' => "{% include 'components/_floating_actions.html.twig' with { app: app } %}",
        ]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));
        $twig->addFunction(new \Twig\TwigFunction('path', static function (string $route, array $params = []): string {
            if ($route === 'locale_switch') {
                return '/'.$route.'/'.($params['locale'] ?? '');
            }

            if ($route === 'theme_switch') {
                return '/'.$route.'/'.($params['theme'] ?? '');
            }

            return '/'.$route;
        }));
        $twig->addFunction(new \Twig\TwigFunction('is_granted', static fn (string $role): bool => $role === 'ROLE_ADMIN'));
        $twig->addFunction(new \Twig\TwigFunction('csrf_token', static fn (string $id): string => 'csrf-token-'.$id));
        $twig->addGlobal('app_kernel_environment', 'dev');

        $app = new class () {
            public object $request;
            public object $user;
            public function __construct()
            {
                $this->user = (object) ['id' => 1];
                $this->request = new class () {
                    public object $attributes;
                    public string $pathInfo = '/cv';
                    public string $locale = 'fr';
                    public function __construct()
                    {
                        $this->attributes = new class () {
                            public function get(string $key, mixed $default = null): mixed
                            {
                                if ($key === 'app_theme') {
                                    return 'dark';
                                }

                                if ($key === '_route') {
                                    return 'app_cv_show';
                                }

                                if ($key === 'cv_technical_score') {
                                    return 77;
                                }

                                return $default;
                            }
                        };
                    }
                };
            }
        };

        $rendered = $twig->render('test.twig', ['app' => $app]);

        self::assertStringContainsString('floating-actions', $rendered);
        self::assertStringContainsString('floating-actions__bar', $rendered);
        self::assertStringContainsString('role="toolbar"', $rendered);
        self::assertStringContainsString('bi-globe2', $rendered);
        self::assertStringContainsString('bi-sun-fill', $rendered);
        self::assertStringContainsString('bi-bug-fill', $rendered);
        self::assertStringContainsString('bugReportModal', $rendered);
        self::assertStringContainsString('bi-speedometer2', $rendered);
        self::assertStringContainsString('floating-actions__score-dev', $rendered);
        self::assertStringContainsString('profile.bubble.score', $rendered);
        self::assertStringNotContainsString('floating-actions__status', $rendered);
        self::assertStringContainsString('/theme_switch/light', $rendered);
        self::assertStringContainsString('/app_logout', $rendered);
        self::assertStringContainsString('/app_profile_show', $rendered);
        self::assertStringNotContainsString('floating-actions-toggle', $rendered);
        self::assertStringNotContainsString('bi-grid-3x3-gap-fill', $rendered);
    }

    /**
     * @brief Ensure floating actions hide bug report controls for anonymous visitors.
     * @return void
     * @date 2026-05-06
     * @author Stephane H.
     */
    public function testFloatingActionsHideBugControlsWhenUserIsAnonymous(): void
    {
        $root = dirname(__DIR__, 3);
        $twig = new Environment(new ArrayLoader([
            'components/_floating_actions.html.twig' => file_get_contents($root.'/templates/components/_floating_actions.html.twig') ?: '',
            'test.twig' => "{% include 'components/_floating_actions.html.twig' with { app: app } %}",
        ]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $route, array $params = []): string => '/'.$route));
        $twig->addFunction(new \Twig\TwigFunction('csrf_token', static fn (string $id): string => 'csrf-token-'.$id));
        $twig->addFunction(new \Twig\TwigFunction('is_granted', static fn (string $role): bool => false));
        $twig->addGlobal('app_kernel_environment', 'dev');

        $app = new class () {
            public object $request;
            public mixed $user;
            public function __construct()
            {
                $this->user = null;
                $this->request = new class () {
                    public object $attributes;
                    public string $pathInfo = '/cv';
                    public string $locale = 'fr';
                    public string $queryString = '';
                    public function __construct()
                    {
                        $this->attributes = new class () {
                            public function get(string $key, mixed $default = null): mixed
                            {
                                if ($key === 'app_theme') {
                                    return 'light';
                                }

                                return $default;
                            }
                        };
                    }
                };
            }
        };

        $rendered = $twig->render('test.twig', ['app' => $app]);

        self::assertStringNotContainsString('bi-bug-fill', $rendered);
        self::assertStringNotContainsString('bugReportModal', $rendered);
        self::assertStringContainsString('floating-actions__score-dev', $rendered);
    }

    /**
     * @brief Ensure CV technical score is hidden from the floating bar outside dev environment.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testFloatingActionsHideCvScoreOutsideDev(): void
    {
        $root = dirname(__DIR__, 3);
        $twig = new Environment(new ArrayLoader([
            'components/_floating_actions.html.twig' => file_get_contents($root.'/templates/components/_floating_actions.html.twig') ?: '',
            'test.twig' => "{% include 'components/_floating_actions.html.twig' with { app: app } %}",
        ]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $route): string => '/'.$route));
        $twig->addFunction(new \Twig\TwigFunction('is_granted', static fn (string $role): bool => false));
        $twig->addGlobal('app_kernel_environment', 'prod');

        $app = new class () {
            public object $request;
            public mixed $user;
            public function __construct()
            {
                $this->user = null;
                $this->request = new class () {
                    public object $attributes;
                    public string $pathInfo = '/cv';
                    public string $locale = 'fr';
                    public function __construct()
                    {
                        $this->attributes = new class () {
                            public function get(string $key, mixed $default = null): mixed
                            {
                                if ($key === 'cv_technical_score') {
                                    return 42;
                                }

                                return $default;
                            }
                        };
                    }
                };
            }
        };

        $rendered = $twig->render('test.twig', ['app' => $app]);

        self::assertStringNotContainsString('floating-actions__score-dev', $rendered);
    }
}
