<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * @brief Contract tests for employment document PDF preview routes and UI.
 */
final class EmploymentDocumentPdfPreviewTest extends KernelTestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Preview routes must be registered for CV and LM.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testPreviewRoutesAreRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertStringContainsString(
            '/admin/employment/cv-documents/1/locale/fr/preview-pdf',
            $router->generate('admin_employment_cv_documents_preview_pdf', ['id' => 1, 'locale' => 'fr']),
        );
        self::assertStringContainsString(
            '/admin/employment/lm-documents/2/locale/en/preview-pdf',
            $router->generate('admin_employment_lm_documents_preview_pdf', ['id' => 2, 'locale' => 'en']),
        );
    }

    /**
     * @brief Admin controller must expose preview endpoints protected for admins.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testControllerContract(): void
    {
        $source = @file_get_contents(self::projectRoot().'/src/Controller/Admin/EmploymentDocumentAdminController.php') ?: '';

        self::assertStringContainsString("#[IsGranted('ROLE_CV_EDIT')]", $source);
        self::assertStringContainsString("name: 'admin_employment_cv_documents_preview_pdf'", $source);
        self::assertStringContainsString("name: 'admin_employment_lm_documents_preview_pdf'", $source);
        self::assertStringContainsString('DISPOSITION_INLINE', $source);
        self::assertStringContainsString('EmploymentDocumentPdfDeliveryService', $source);
    }

    /**
     * @brief Locale asset template must link to preview route.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testLocaleTemplateContainsPreviewLink(): void
    {
        $template = @file_get_contents(self::projectRoot().'/templates/admin/employment/documents/_locale_asset_fields.html.twig') ?: '';

        self::assertStringContainsString('previewRoute', $template);
        self::assertStringContainsString('employment.documents.locale.preview_pdf', $template);
        self::assertStringContainsString('target="_blank"', $template);
    }
}
