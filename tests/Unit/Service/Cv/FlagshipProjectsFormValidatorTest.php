<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\FlagshipProjectsFormValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see FlagshipProjectsFormValidator}.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class FlagshipProjectsFormValidatorTest extends TestCase
{
    private FlagshipProjectsFormValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FlagshipProjectsFormValidator();
    }

    /**
     * @brief Missing default-locale title must produce a dedicated validation message.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testValidateRequestReportsMissingDefaultLocaleTitle(): void
    {
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $request = Request::create('/', 'POST', [
            'flagship_projects' => [
                'entries' => [
                    $projectId => [
                        'sort_order' => 0,
                        'github_url' => '',
                        'demo_url' => 'https://quoicoubeh.fr',
                        'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                        'locales' => [
                            'en' => ['title' => '', 'description' => '', 'tags' => '', 'site_link_label' => '', 'preview_alt' => ''],
                            'fr' => ['title' => 'Mon projet', 'description' => '', 'tags' => '', 'site_link_label' => '', 'preview_alt' => ''],
                        ],
                    ],
                ],
            ],
        ]);

        $errors = $this->validator->validateRequest($request, ['en', 'fr'], 'en');

        self::assertNotEmpty($errors);
        self::assertSame(
            'dashboard.customization_cv.flagship_projects.validation.title_required',
            $errors[0]['message']
        );
        self::assertSame('1', $errors[0]['parameters']['%index%']);
        self::assertSame('EN', $errors[0]['parameters']['%locale%']);
    }

    /**
     * @brief Invalid site URL must produce a dedicated validation message.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testValidateRequestReportsInvalidSiteUrl(): void
    {
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $request = Request::create('/', 'POST', [
            'flagship_projects' => [
                'entries' => [
                    $projectId => [
                        'sort_order' => 0,
                        'github_url' => '',
                        'demo_url' => 'quoicoubeh.fr',
                        'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                        'locales' => [
                            'en' => ['title' => 'Project', 'description' => '', 'tags' => '', 'site_link_label' => '', 'preview_alt' => ''],
                        ],
                    ],
                ],
            ],
        ]);

        $errors = $this->validator->validateRequest($request, ['en'], 'en');
        $messages = array_column($errors, 'message');

        self::assertContains(
            'dashboard.customization_cv.flagship_projects.validation.site_url_invalid',
            $messages
        );
    }

    /**
     * @brief Valid submission must not return validation errors.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testValidateRequestAcceptsValidProject(): void
    {
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $request = Request::create('/', 'POST', [
            'flagship_projects' => [
                'entries' => [
                    $projectId => [
                        'sort_order' => 0,
                        'github_url' => 'https://github.com/example/repo',
                        'demo_url' => 'https://quoicoubeh.fr',
                        'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                        'locales' => [
                            'en' => ['title' => 'Project', 'description' => 'Desc', 'tags' => "PHP\nSymfony", 'site_link_label' => 'Demo', 'preview_alt' => 'Preview'],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame([], $this->validator->validateRequest($request, ['en'], 'en'));
    }

    /**
     * @brief Tag count must not be capped during admin validation.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testValidateRequestAcceptsManyTags(): void
    {
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $tags = implode("\n", [
            'PHP', 'Symfony', 'Docker', 'MySQL', 'Redis', 'JavaScript', 'TypeScript', 'HTML', 'CSS', 'API',
        ]);
        $request = Request::create('/', 'POST', [
            'flagship_projects' => [
                'entries' => [
                    $projectId => [
                        'sort_order' => 0,
                        'github_url' => '',
                        'demo_url' => '',
                        'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                        'locales' => [
                            'fr' => [
                                'title' => 'Projet',
                                'description' => '',
                                'tags' => $tags,
                                'site_link_label' => '',
                                'preview_alt' => '',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame([], $this->validator->validateRequest($request, ['fr'], 'fr'));
    }
}
