<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Service\Cv\FlagshipProjectsContract;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @brief Contract checks for admin CV customization tabs template and i18n keys.
 * @date 2026-05-10
 * @author Stephane H.
 */
final class CvCustomizationComplianceTest extends KernelTestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Template must include every CV section partial under components/cv.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testAdminCvIndexIncludesAllSectionPartials(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/admin/cv/index.html.twig') ?: '';
        $includes = [
            "components/cv/admin/_cv_data_customization.html.twig",
            "components/cv/admin/_about_customization.html.twig",
            "components/cv/admin/_experience_customization.html.twig",
            "components/cv/admin/_education_customization.html.twig",
            "components/cv/admin/_certification_customization.html.twig",
            "components/cv/admin/_languages_customization.html.twig",
            "components/cv/admin/_interests_customization.html.twig",
            "components/cv/admin/_web_profiles_customization.html.twig",
            "components/cv/admin/_references_customization.html.twig",
        ];
        foreach ($includes as $path) {
            self::assertStringContainsString($path, $twig, 'Missing include: '.$path);
        }
        self::assertStringContainsString('_situation_customization.html.twig', @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_about_customization.html.twig') ?: '');

        self::assertStringContainsString("vendor/ckeditor5/41.4.2-cv/ckeditor.js", $twig);
        self::assertStringContainsString('ckeditor-init.js', $twig);
        self::assertStringNotContainsString('cv-about-atmosphere-admin.js', $twig);
        self::assertStringNotContainsString('cv-about-bg-decor-admin.js', $twig);
        self::assertStringContainsString('cv-experience-admin.js', $twig);
        self::assertStringContainsString('cv-education-admin.js', $twig);
        self::assertStringContainsString('cv-certification-admin.js', $twig);
        self::assertStringContainsString('cv-languages-admin.js', $twig);
        self::assertStringContainsString('cv-bootstrap-icon-browser.js', $twig);
        self::assertStringContainsString('cv-interests-admin.js', $twig);
        self::assertStringContainsString('cv-web-profiles-admin.js', $twig);
        self::assertStringContainsString('cv-references-admin.js', $twig);
        self::assertStringContainsString('data-cv-tab="languages"', $twig);
        self::assertStringContainsString('data-cv-tab="references"', $twig);
        self::assertStringContainsString('cv-flagship-projects-admin.js', $twig);
        self::assertStringContainsString('data-cv-tab="cv_data"', $twig);
        self::assertStringContainsString('_cv_data_customization.html.twig', $twig);
        self::assertStringContainsString('_cv_data_customization.html.twig', $twig);
        self::assertStringContainsString('_cv_data_customization.html.twig', $twig);
        self::assertStringContainsString('data-cv-tab="about"', $twig);
        self::assertStringContainsString('data-cv-tab="flagship_projects"', $twig);
        self::assertStringContainsString('_flagship_project_customization.html.twig', $twig);
        $flagshipForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_flagship_project_customization.html.twig') ?: '';
        self::assertStringContainsString('name="form_scope"', $flagshipForm);
        self::assertStringContainsString("cvFlagshipProjectsFormScope|default('flagship_projects')", $flagshipForm);
        self::assertStringContainsString('flagship_projects_section_enabled', $flagshipForm);
        self::assertStringContainsString('enctype="multipart/form-data"', $flagshipForm);
        self::assertStringContainsString('data-cv-flagship-project-add', $flagshipForm);
        self::assertStringContainsString('data-cv-flagship-projects-admin', $flagshipForm);
        $flagshipEntryFields = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_flagship_project_entry_fields.html.twig') ?: '';
        self::assertStringContainsString('data-cv-flagship-project-entry', $flagshipEntryFields);
        self::assertStringContainsString('flagship_projects[entries]', $flagshipEntryFields);
        self::assertStringContainsString('flagship_project_preview', $flagshipEntryFields);
        self::assertStringContainsString('project-default.webp', $flagshipEntryFields);
        self::assertStringContainsString('form-check form-switch', $flagshipForm);
        self::assertStringContainsString('cvFlagshipProjectsSectionEnabled ?? true', $flagshipForm);
        self::assertStringNotContainsString('cvFlagshipProjectsSectionEnabled|default(true)', $flagshipForm);
        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('flagshipProjectsSectionEnabled ?? true', $showTwig);
        self::assertStringNotContainsString('flagshipProjectsSectionEnabled|default(true)', $showTwig);
        $navTwig = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_nav_links.html.twig') ?: '';
        self::assertStringContainsString('cv.menu.section_contact', $navTwig);

        $aboutCustomization = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_about_customization.html.twig') ?: '';
        $aboutPatternCustomization = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_about_section_customization_color.html.twig') ?: '';
        $aboutTypography = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_about_presentation_typography.html.twig') ?: '';
        $aboutCustomizationScope = $aboutCustomization.$aboutPatternCustomization.$aboutTypography;
        self::assertStringContainsString('data-customization-panel="section"', $aboutCustomization);
        self::assertStringContainsString('about_accordion.section_customization_title', $aboutCustomization);
        self::assertStringContainsString('_about_section_customization_color.html.twig', $aboutCustomization);
        self::assertStringNotContainsString('about_section_customization_color', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_pattern_tone_mix_percent', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_pattern_template_left', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_pattern_template_right', $aboutPatternCustomization);
        self::assertStringContainsString('type="radio"', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_pattern_svg', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_pattern_svg_name', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_pattern_delete', $aboutPatternCustomization);
        self::assertStringContainsString('about_section_customization.palette_reminder', $aboutPatternCustomization);
        self::assertStringNotContainsString('about_section_theme_colors', $aboutCustomization);
        self::assertStringNotContainsString('about_section_atmosphere_style', $aboutCustomization);
        self::assertStringNotContainsString('_about_background_decoration.html.twig', $aboutCustomization);
        self::assertStringNotContainsString('about_portrait_frame', $aboutCustomization);
        self::assertStringNotContainsString('about_disk_enabled', $aboutCustomization);
        self::assertStringNotContainsString('about_background_primary', $aboutCustomization);
        self::assertStringContainsString('about_profile_photo', $aboutCustomization);
        self::assertStringContainsString('about_profile_photo.composition_tip', $aboutCustomization);
        self::assertStringNotContainsString('about_profile_photo_x', $aboutCustomization);
        self::assertStringNotContainsString('about_profile_photo_width_px', $aboutCustomization);
        self::assertStringNotContainsString('about_profile_photo_shadow_enabled', $aboutCustomization);
        self::assertStringContainsString('about_presentation_html[', $aboutCustomizationScope);
        self::assertStringContainsString('about_presentation_typography[', $aboutCustomizationScope);
        self::assertStringContainsString('_about_presentation_typography.html.twig', $aboutCustomization);
        self::assertStringContainsString('cvAboutPresentationTypographyForm', $aboutCustomizationScope);
        self::assertStringContainsString('cvAboutPresentationLocaleTabs', $aboutCustomization);
        self::assertStringContainsString('ckeditor-cv-rich', $aboutCustomizationScope);
        self::assertStringNotContainsString('about_presentation_desktop_width_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_mobile_width_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_desktop_left_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_mobile_left_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_desktop_padding_top_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_desktop_padding_bottom_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_desktop_top_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_desktop_height_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_desktop_z_index', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_mobile_padding_top_value', $aboutCustomization);
        self::assertStringNotContainsString('about_presentation_mobile_padding_bottom_value', $aboutCustomization);
        self::assertStringContainsString('cvAboutTabRootAccordion', $aboutCustomization);
        self::assertStringContainsString('cvAboutCustomizationAccordion', $aboutCustomization);
        self::assertStringContainsString('data-cv-about-preview', $aboutCustomization);
        self::assertStringContainsString('data-customization-panel="situation_content"', $aboutCustomization);
        self::assertStringContainsString('accordion-item', $aboutCustomization);
        self::assertStringNotContainsString('cv-about-customization__pickr-host', $aboutCustomization);
        self::assertStringContainsString('data-cv-placeholder-ui', $aboutCustomization);
        self::assertStringNotContainsString('about_image_desktop', $aboutCustomization);
        self::assertStringNotContainsString('about_image_mobile', $aboutCustomization);
    }

    /**
     * @brief Tab label translation keys must exist in all five locales.
     * @return void
     * @date 2026-05-09
     * @author Stephane H.
     */
    public function testCustomizationCvTabKeysExistInAllLocales(): void
    {
        $tabKeys = ['cv_data', 'about', 'skills', 'flagship_projects', 'experience', 'education', 'certification', 'languages', 'interests', 'web_profiles', 'references'];
        $locales = ['fr', 'en', 'de', 'lt', 'no'];
        foreach ($locales as $locale) {
            $path = self::projectRoot().'/translations/messages.'.$locale.'.yaml';
            $data = Yaml::parseFile($path);
            self::assertIsArray($data);
            $cv = $data['dashboard']['customization_cv'] ?? null;
            self::assertIsArray($cv, 'dashboard.customization_cv missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('meta', $cv);
            self::assertArrayHasKey('title', $cv);
            self::assertArrayHasKey('description', $cv);
            self::assertArrayHasKey('page_title', $cv);
            self::assertArrayHasKey('about_profile_photo', $cv);
            self::assertArrayHasKey('flash', $cv);
            self::assertArrayHasKey('about_visual_invalid', $cv['flash'], 'Missing flash.about_visual_invalid in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('about_visual_saved', $cv['flash'], 'Missing flash.about_visual_saved in messages.'.$locale.'.yaml');
            $about = $cv['about'] ?? null;
            self::assertIsArray($about);
            foreach ([
                'section_title',
                'section_help',
                'background_primary_label',
                'background_secondary_label',
                'halo_strength_label',
                'halo_strength_help',
                'portrait_frame_legend',
                'portrait_frame_help',
                'portrait_frame_legacy_halo',
                'portrait_frame_editorial_ring',
                'portrait_frame_squircle',
                'portrait_frame_glass_rim',
                'disk_enabled_label',
                'disk_enabled_help',
                'disk_scale_label',
                'disk_opacity_label',
                'disk_border_opacity_label',
                'disk_border_thickness_label',
                'disk_glow_outer_opacity_label',
                'disk_glow_inner_opacity_label',
                'disk_glow_outer_blur_label',
                'disk_glow_inner_blur_label',
                'disk_subject_x_label',
                'disk_subject_y_label',
                'save',
            ] as $aboutKey) {
                self::assertArrayHasKey($aboutKey, $about, 'Missing about.'.$aboutKey.' in messages.'.$locale.'.yaml');
            }
            $aboutPhoto = $cv['about_profile_photo'];
            self::assertIsArray($aboutPhoto);
            foreach ([
                'section_title',
                'upload_label',
                'upload_help',
                'preview_alt',
                'composition_tip',
            ] as $photoKey) {
                self::assertArrayHasKey($photoKey, $aboutPhoto, 'Missing about_profile_photo.'.$photoKey.' in messages.'.$locale.'.yaml');
            }
            $aboutPresentation = $cv['about_presentation'] ?? null;
            self::assertIsArray($aboutPresentation, 'dashboard.customization_cv.about_presentation missing in messages.'.$locale.'.yaml');
            foreach ([
                'section_title',
                'section_help',
                'body_label',
                'locale_tab_label',
                'body_help',
                'h1_warning',
                'typography_title',
                'typography_help',
                'typography_unit_aria',
                'typography_h1',
                'typography_h2',
                'typography_h3',
                'typography_h4',
                'typography_h5',
                'typography_h6',
                'typography_p',
            ] as $presentationKey) {
                self::assertArrayHasKey($presentationKey, $aboutPresentation, 'Missing about_presentation.'.$presentationKey.' in messages.'.$locale.'.yaml');
            }
            $aboutAccordion = $cv['about_accordion'] ?? null;
            self::assertIsArray($aboutAccordion, 'dashboard.customization_cv.about_accordion missing in messages.'.$locale.'.yaml');
            $aboutSectionCustomization = $cv['about_section_customization'] ?? null;
            self::assertIsArray($aboutSectionCustomization, 'about_section_customization missing in messages.'.$locale.'.yaml');
            foreach ([
                'help',
                'template_label',
                'template_help',
                'template_upload_label',
                'template_upload_help',
                'palette_reminder',
                'pattern_warning_missing',
                'pattern_warning_palette',
                'pattern_upload_invalid_type',
                'pattern_upload_invalid_palette',
                'color_label',
                'tone_mix_legend',
                'tone_mix_help',
                'tone1_label',
                'tone2_label',
                'tone3_label',
                'tone4_label',
                'preview_label',
                'surface_mix_label',
                'surface_mix_help',
                'dark_surface_darken_label',
                'dark_surface_darken_help',
                'dark_surface_darken_value_label',
            ] as $customizationKey) {
                self::assertArrayHasKey($customizationKey, $aboutSectionCustomization, 'Missing about_section_customization.'.$customizationKey.' in messages.'.$locale.'.yaml');
            }
            foreach (['section_customization_title', 'photo_title', 'presentation_title'] as $accordionKey) {
                self::assertArrayHasKey($accordionKey, $aboutAccordion, 'Missing about_accordion.'.$accordionKey.' in messages.'.$locale.'.yaml');
            }
            $colorPicker = $cv['color_picker'] ?? null;
            self::assertIsArray($colorPicker, 'dashboard.customization_cv.color_picker missing in messages.'.$locale.'.yaml');
            foreach (['save', 'clear', 'cancel'] as $pickerKey) {
                self::assertArrayHasKey($pickerKey, $colorPicker, 'Missing color_picker.'.$pickerKey.' in messages.'.$locale.'.yaml');
            }
            $appNav = $data['app']['nav'] ?? null;
            self::assertIsArray($appNav, 'app.nav missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('cv_public_identity', $appNav, 'Missing app.nav.cv_public_identity in messages.'.$locale.'.yaml');
            $cvIdentity = $data['dashboard']['cv_public_identity'] ?? null;
            self::assertIsArray($cvIdentity, 'dashboard.cv_public_identity missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('title', $cvIdentity);
            self::assertArrayHasKey('description', $cvIdentity);
            self::assertArrayHasKey('link_back', $cvIdentity);
            foreach ([
                'display_name_label',
                'birth_date_label',
                'birth_date_help',
                'birth_date_placeholder',
                'city_label',
                'region_label',
                'country_label',
                'country_help',
                'sought_position_label',
                'sought_position_help',
                'status_label',
                'status_help',
                'career_start_year_label',
                'career_start_year_help',
                'tagline_label',
                'tagline_richtext_help',
                'tagline_h1_warning',
                'locale_tab_label',
                'locale_tabs_aria',
                'locale_fields_title',
                'locale_fields_help',
                'global_fields_title',
                'placeholders_title',
                'placeholder_display_name',
                'placeholder_age_years',
                'placeholder_city',
                'placeholder_region',
                'placeholder_country',
                'placeholder_sought_position',
                'placeholder_status',
                'placeholder_career_start_year',
                'placeholder_experience_years',
                'placeholder_tagline',
                'placeholder_document_year',
                'placeholder_date_now',
                'save',
            ] as $identityKey) {
                self::assertArrayHasKey($identityKey, $cvIdentity, 'Missing cv_public_identity.'.$identityKey.' in messages.'.$locale.'.yaml');
            }
            $cvIdFlash = $cvIdentity['flash'] ?? null;
            self::assertIsArray($cvIdFlash, 'dashboard.cv_public_identity.flash missing in messages.'.$locale.'.yaml');
            foreach (['invalid_csrf', 'invalid', 'saved'] as $flashKey) {
                self::assertArrayHasKey($flashKey, $cvIdFlash, 'Missing cv_public_identity.flash.'.$flashKey.' in messages.'.$locale.'.yaml');
            }
            $cvIdCk = $cvIdentity['ckeditor'] ?? null;
            self::assertIsArray($cvIdCk, 'dashboard.cv_public_identity.ckeditor missing in messages.'.$locale.'.yaml');
            foreach (['picker_label', 'menu_aria', 'insert_token'] as $ckKey) {
                self::assertArrayHasKey($ckKey, $cvIdCk, 'Missing cv_public_identity.ckeditor.'.$ckKey.' in messages.'.$locale.'.yaml');
            }
            foreach (\App\Service\Cv\CvPublicIdentityContract::PLACEHOLDER_TOKEN_NAMES as $tokenName) {
                $tokenLabelKey = 'token_'.$tokenName;
                self::assertArrayHasKey(
                    $tokenLabelKey,
                    $cvIdCk,
                    'Missing cv_public_identity.ckeditor.'.$tokenLabelKey.' in messages.'.$locale.'.yaml'
                );
            }
            $tab = $cv['tab'] ?? null;
            self::assertIsArray($tab, 'dashboard.customization_cv.tab missing in messages.'.$locale.'.yaml');
            foreach ($tabKeys as $key) {
                self::assertArrayHasKey($key, $tab, 'Missing tab.'.$key.' in messages.'.$locale.'.yaml');
                self::assertNotSame('', trim((string) $tab[$key]), 'Empty tab.'.$key.' in messages.'.$locale.'.yaml');
            }
        }
    }

    /**
     * @brief Admin CV route must stay registered for dashboard navigation.
     * @return void
     * @date 2026-05-10
     * @author Stephane H.
     */
    public function testAdminCvIndexRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertNotSame('', $router->generate('admin_cv_index'));
        self::assertNotSame('', $router->generate('admin_cv_public_identity'));
        self::assertStringContainsString('tab=about', $router->generate('admin_cv_index', ['tab' => 'about']));
        self::assertNotSame('', $router->generate('app_cv_about_profile_css'));
        self::assertNotSame('', $router->generate('app_cv_about_pattern_css'));
    }

    /**
     * @brief Public identity admin controller must stay admin-only with stable route name.
     * @return void
     * @date 2026-05-10
     * @author Stephane H.
     */
    public function testAdminCvPublicIdentityControllerContract(): void
    {
        $php = @file_get_contents(self::projectRoot().'/src/Controller/Admin/CvPublicIdentityController.php') ?: '';
        self::assertStringContainsString("#[IsGranted('ROLE_CV_EDIT')]", $php);
        self::assertStringContainsString("name: 'admin_cv_public_identity'", $php);
        self::assertStringContainsString("/admin/cv/public-identity", $php);
        self::assertStringContainsString("redirectToRoute('admin_cv_index'", $php);
        self::assertStringContainsString("'tab' => 'cv_data'", $php);
        $cvDataTwig = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_cv_data_customization.html.twig') ?: '';
        self::assertStringContainsString('cv_public_identity_country[', $cvDataTwig);
        self::assertStringContainsString('cv_public_identity_sought_position[', $cvDataTwig);
        self::assertStringContainsString('cv_public_identity_tagline[', $cvDataTwig);
        self::assertStringContainsString('page_title[', $cvDataTwig);
        self::assertStringContainsString('[[cv.country]]', $cvDataTwig);
        self::assertStringContainsString("form_scope\" value=\"cv_data\"", $cvDataTwig);
        self::assertStringContainsString('cv_pencil_decoration_enabled', $cvDataTwig);
        self::assertStringContainsString('cv_pencil_light_tone_mix_percent', $cvDataTwig);
        self::assertStringContainsString('cv_pencil_dark_tone_mix_percent', $cvDataTwig);
        self::assertStringContainsString('form-check form-switch', $cvDataTwig);
        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('_cv_pencil_decor.html.twig', $showTwig);
        self::assertStringContainsString('cv-pencil-decor.js', $showTwig);
        $pencilTwig = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_pencil_decor.html.twig') ?: '';
        self::assertStringContainsString('cvPencilDecorationEnabled ?? true', $pencilTwig);
        self::assertStringContainsString('data-cv-pencil-decor', $pencilTwig);
        $persistencePhp = @file_get_contents(self::projectRoot().'/src/Cv/CvProfilePersistenceScope.php') ?: '';
        self::assertStringContainsString('CvPencilDecorationContract::KEY', $persistencePhp);
        $servicePhp = @file_get_contents(self::projectRoot().'/src/Service/Cv/CvPublicIdentityAdminService.php') ?: '';
        self::assertStringContainsString('parseFromCvDataRequest', $servicePhp);
        self::assertStringContainsString('FIELD_SOUGHT_POSITION_BY_LOCALE', $servicePhp);
        $profilePhp = @file_get_contents(self::projectRoot().'/src/Controller/Admin/CvProfileController.php') ?: '';
        self::assertStringContainsString('handleCvDataUpdate', $profilePhp);
        self::assertStringContainsString('CvPencilDecorationContract::mergeSubmittedFromCvDataRequest', $profilePhp);
        self::assertStringContainsString('CvPublicIdentityAdminService', $profilePhp);
        $contractPhp = @file_get_contents(self::projectRoot().'/src/Service/Cv/CvPublicIdentityContract.php') ?: '';
        self::assertStringContainsString("'country'", $contractPhp);
        self::assertStringContainsString('FIELD_SOUGHT_POSITION_BY_LOCALE', $contractPhp);
        self::assertStringContainsString('SOUGHT_POSITION_MAX_LENGTH', $contractPhp);
        self::assertStringContainsString('aboutHeaderLocationLine', @file_get_contents(self::projectRoot().'/src/Service/Cv/CvResolverService.php') ?: '');
        self::assertFileExists(self::projectRoot().'/templates/components/cv/_about.html.twig');
    }

    /**
     * @brief Public CV sidebar brand exposes favicon and site home control.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testCvPublicHomeLinkRespectsSiteTheme(): void
    {
        $brand = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_nav_brand.html.twig') ?: '';
        self::assertStringContainsString("path('app_home')", $brand);
        self::assertStringContainsString('bi-house-fill', $brand);
        self::assertStringContainsString('cv.menu.home_aria_label', $brand);
        self::assertFileDoesNotExist(self::projectRoot().'/templates/components/cv/_cv_home_link.html.twig');

        $layoutCss = @file_get_contents(self::projectRoot().'/public/css/cv-public-layout.css') ?: '';
        self::assertStringContainsString('#17283c', $layoutCss);
        self::assertStringContainsString('.cv-public-sidebar', $layoutCss);
        self::assertStringContainsString('.cv-public-nav-brand__favicon', $layoutCss);
        self::assertStringNotContainsString('.cv-public-header', $layoutCss);

        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString("path('app_cv_public_sidebar_css'", $showTwig);
        self::assertStringNotContainsString('css/cv-home-link.css', $showTwig);
        self::assertStringNotContainsString('_cv_home_link.html.twig', $showTwig);
    }

    /**
     * @brief Customization dropdown links CV customization but not a separate cv_data entry.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testAdminDashboardMenuLinksCvCustomizationWithoutCvDataEntry(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/components/_admin_dashboard_menu.html.twig') ?: '';
        self::assertStringContainsString("href=\"{{ path('admin_cv_index') }}\">{{ 'app.nav.employment_cv'|trans", $twig);
        self::assertStringNotContainsString("path('admin_cv_index', { tab: 'cv_data' })", $twig);
        self::assertStringNotContainsString("'app.nav.cv_public_identity'|trans", $twig);
        self::assertStringNotContainsString(
            'btn btn-outline-light btn-sm" href="{{ path(\'admin_cv_public_identity\') }}"',
            $twig
        );
    }

    /**
     * @brief Controller redirects must preserve `tab` query via redirect helper for customization flows.
     * @return void
     * @date 2026-05-10
     * @author Stephane H.
     */
    public function testCvProfileControllerRedirectsPreserveTabQuery(): void
    {
        $php = @file_get_contents(self::projectRoot().'/src/Controller/Admin/CvProfileController.php') ?: '';
        self::assertStringContainsString('redirectToCvCustomizationIndex', $php);
        self::assertStringContainsString('CvProfilePersistenceScope', $php);
        self::assertStringNotContainsString("'cvAboutAtmosphereStyle' =>", $php);
    }

    /**
     * @brief Public About section uses hero layout, SVG pattern background, and dedicated public CSS.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testPublicAboutSectionUsesHeroLayoutAndPatternBackground(): void
    {
        self::assertFileExists(self::projectRoot().'/templates/components/cv/_about.html.twig');
        self::assertFileDoesNotExist(self::projectRoot().'/templates/components/cv/_about_archive.html.twig');
        $aboutTwig = @file_get_contents(self::projectRoot().'/templates/components/cv/_about.html.twig') ?: '';
        self::assertStringContainsString('id="about"', $aboutTwig);
        self::assertStringContainsString('cv-about__backdrop', $aboutTwig);
        self::assertStringContainsString('cv-about__pattern', $aboutTwig);
        self::assertStringContainsString('aboutPatternLeftSvgMarkup', $aboutTwig);
        self::assertStringContainsString('aboutPatternRightSvgMarkup', $aboutTwig);
        self::assertStringContainsString('aboutPresentationHtml', $aboutTwig);
        self::assertStringContainsString('aboutProfilePhotoDisplayPath', $aboutTwig);
        self::assertStringContainsString('cv-about__photo', $aboutTwig);

        $aboutCss = @file_get_contents(self::projectRoot().'/public/css/cv-about-public.css') ?: '';
        self::assertStringNotContainsString('fond-about.svg', $aboutCss);
        self::assertStringContainsString('--cv-about-pattern-tone-1', $aboutCss);
        self::assertStringContainsString('color-mix', $aboutCss);
        self::assertStringContainsString('--cv-about-pattern-surface-mix', $aboutCss);

        $patternSvg = @file_get_contents(self::projectRoot().'/templates/components/cv/_about_pattern_svg.html.twig') ?: '';
        self::assertStringContainsString('var(--cv-about-pattern-tone-1)', $patternSvg);
        self::assertStringContainsString('var(--cv-about-pattern-tone-4)', $patternSvg);

        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('css/cv-public-static-bundle.css', $showTwig);
        self::assertStringContainsString("path('app_cv_about_pattern_css'", $showTwig);
        self::assertStringContainsString('components/cv/_about.html.twig', $showTwig);
    }

    /**
     * @brief Public CV show must not load legacy About styles; admin preview still does.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testCvShowLinksAboutProfileStylesheet(): void
    {
        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString("path('app_cv_about_profile_css', { v: aboutProfileCssVersion })", $showTwig);
        self::assertStringContainsString('cv.aboutProfileCssCacheSuffix', $showTwig);
        self::assertStringContainsString('css/cv-public-static-bundle.css', $showTwig);
        self::assertStringContainsString("path('app_cv_public_sidebar_css'", $showTwig);
        self::assertStringContainsString("path('app_cv_about_pattern_css'", $showTwig);
        self::assertStringContainsString('components/cv/_experience.html.twig', $showTwig);
        self::assertStringContainsString('components/cv/_education.html.twig', $showTwig);
        self::assertStringContainsString('components/cv/_certification.html.twig', $showTwig);
        self::assertStringContainsString('components/cv/_skills.html.twig', $showTwig);
        self::assertStringContainsString('components/cv/_projects.html.twig', $showTwig);

        $adminTwig = @file_get_contents(self::projectRoot().'/templates/admin/cv/index.html.twig') ?: '';
        self::assertStringContainsString('css/cv-about.css', $adminTwig);
        self::assertStringContainsString('css/cv-about-public.css', $adminTwig);
        self::assertStringContainsString("path('app_cv_about_profile_css', { v: aboutCssVersion })", $adminTwig);
        self::assertStringContainsString("path('app_cv_about_pattern_css'", $adminTwig);
    }

    /**
     * @brief Public situation partials removed; experience preview partial kept for admin only.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testSituationAndExperienceSectionsAreSplit(): void
    {
        self::assertFileDoesNotExist(self::projectRoot().'/templates/components/cv/_situation_modal.html.twig');
        self::assertFileExists(self::projectRoot().'/templates/components/cv/_situation_content.html.twig');
        self::assertFileDoesNotExist(self::projectRoot().'/public/js/cv-situation-modal.js');

        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('_cv_public_shell.html.twig', $showTwig);
        self::assertStringContainsString('_contact_modal.html.twig', $showTwig);
        self::assertStringNotContainsString('_situation_modal.html.twig', $showTwig);

        $situationForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_situation_customization.html.twig') ?: '';
        self::assertStringContainsString('admin/_situation_content_fields.html.twig', $situationForm);

        $controller = @file_get_contents(self::projectRoot().'/src/Controller/CvController.php') ?: '';
        self::assertStringContainsString("name: 'cv_situation'", $controller);
        self::assertStringContainsString('situation_full.html.twig', $controller);

        $situationFull = @file_get_contents(self::projectRoot().'/templates/cv/situation_full.html.twig') ?: '';
        self::assertStringContainsString('_situation_content.html.twig', $situationFull);

        $situationContent = @file_get_contents(self::projectRoot().'/templates/components/cv/_situation_content.html.twig') ?: '';
        self::assertStringContainsString('id="situation"', $situationContent);
        self::assertStringContainsString('situationContent', $situationContent);

        $experience = @file_get_contents(self::projectRoot().'/templates/components/cv/_experience.html.twig') ?: '';
        self::assertStringContainsString('id="experience"', $experience);
        self::assertStringContainsString('_experience_timeline.html.twig', $experience);
        self::assertStringContainsString('cv-about-accent-btn--outline', $experience);
        self::assertStringContainsString('see_more_aria_label', $experience);

        $timeline = @file_get_contents(self::projectRoot().'/templates/components/cv/_experience_timeline.html.twig') ?: '';
        self::assertStringContainsString('cv-experience__timeline', $timeline);
    }

    /**
     * @brief cv.situation (split editorial + mobility chips) and cv.experience translation trees must exist in all five locales.
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testCvSituationAndExperienceI18nExistInAllLocales(): void
    {
        $situationKeys = [
            'title',
            'full_page_title',
            'full_page_meta_title',
            'full_page_intro',
            'back_to_cv',
            'status_label',
            'status_available',
            'location_label',
            'location_empty',
            'search_where_label',
            'search_mode_label',
            'search_focus_label',
            'last_role_label',
            'last_role_empty',
            'chips_geo_aria',
            'chips_mode_aria',
        ];
        $experienceKeys = [
            'title',
            'timeline_aria_label',
            'see_more',
            'see_more_aria_label',
            'full_page_title',
            'full_page_meta_title',
            'period_range',
            'period_current',
            'empty',
        ];
        $locales = ['fr', 'en', 'de', 'lt', 'no'];
        foreach ($locales as $locale) {
            $data = Yaml::parseFile(self::projectRoot().'/translations/messages.'.$locale.'.yaml');
            self::assertIsArray($data);
            $cv = $data['cv'] ?? null;
            self::assertIsArray($cv, 'cv missing in messages.'.$locale.'.yaml');
            $situation = $cv['situation'] ?? null;
            self::assertIsArray($situation, 'cv.situation missing in messages.'.$locale.'.yaml');
            foreach ($situationKeys as $key) {
                self::assertArrayHasKey($key, $situation, 'Missing cv.situation.'.$key.' in messages.'.$locale.'.yaml');
            }
            $placeholder = $cv['placeholder'] ?? null;
            self::assertIsArray($placeholder, 'cv.placeholder missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('situation', $placeholder, 'Missing cv.placeholder.situation in messages.'.$locale.'.yaml');
            self::assertNotSame('', trim((string) ($placeholder['situation'] ?? '')), 'cv.placeholder.situation must not be empty in messages.'.$locale.'.yaml');
            $about = $cv['about'] ?? null;
            self::assertIsArray($about, 'cv.about missing in messages.'.$locale.'.yaml');
            $presentationDefault = $about['presentation_default'] ?? null;
            self::assertIsArray($presentationDefault, 'cv.about.presentation_default missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey(
                'fallback_display_name',
                $presentationDefault,
                'Missing cv.about.presentation_default.fallback_display_name in messages.'.$locale.'.yaml'
            );
            $skills = $cv['skills'] ?? null;
            self::assertIsArray($skills, 'cv.skills missing in messages.'.$locale.'.yaml');
            foreach (['title', 'see_more', 'see_more_aria_label', 'full_page_highlight_legend', 'empty', 'full_page_title', 'full_page_meta_title', 'back_to_cv', 'secondary_empty'] as $skillsKey) {
                self::assertArrayHasKey($skillsKey, $skills, 'Missing cv.skills.'.$skillsKey.' in messages.'.$locale.'.yaml');
            }
            $customizationCv = $data['dashboard']['customization_cv'] ?? null;
            self::assertIsArray($customizationCv, 'dashboard.customization_cv missing in messages.'.$locale.'.yaml');
            $skillsAdmin = $customizationCv['skills'] ?? null;
            self::assertIsArray($skillsAdmin, 'dashboard.customization_cv.skills missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('catalog', $skillsAdmin, 'Missing dashboard.customization_cv.skills.catalog in messages.'.$locale.'.yaml');
            $skillsAccordion = $customizationCv['skills_accordion'] ?? null;
            self::assertIsArray($skillsAccordion, 'dashboard.customization_cv.skills_accordion missing in messages.'.$locale.'.yaml');
            foreach (['catalog_title', 'catalog_help', 'catalog_save'] as $skillsAccordionKey) {
                self::assertArrayHasKey(
                    $skillsAccordionKey,
                    $skillsAccordion,
                    'Missing dashboard.customization_cv.skills_accordion.'.$skillsAccordionKey.' in messages.'.$locale.'.yaml'
                );
            }
            $projects = $cv['projects'] ?? null;
            self::assertIsArray($projects, 'cv.projects missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('title', $projects, 'Missing cv.projects.title in messages.'.$locale.'.yaml');
            $experience = $cv['experience'] ?? null;
            self::assertIsArray($experience, 'cv.experience missing in messages.'.$locale.'.yaml');
            foreach ($experienceKeys as $key) {
                self::assertArrayHasKey($key, $experience, 'Missing cv.experience.'.$key.' in messages.'.$locale.'.yaml');
            }
            $placeholder = $cv['placeholder'] ?? null;
            self::assertIsArray($placeholder, 'cv.placeholder missing in messages.'.$locale.'.yaml');
            $experiencePlaceholder = $placeholder['experience'] ?? null;
            self::assertIsArray($experiencePlaceholder, 'Missing cv.placeholder.experience in messages.'.$locale.'.yaml');
            foreach (['section', 'title', 'company', 'period', 'description'] as $experiencePlaceholderKey) {
                self::assertArrayHasKey(
                    $experiencePlaceholderKey,
                    $experiencePlaceholder,
                    'Missing cv.placeholder.experience.'.$experiencePlaceholderKey.' in messages.'.$locale.'.yaml'
                );
            }
            $educationPlaceholder = $placeholder['education'] ?? null;
            self::assertIsArray($educationPlaceholder, 'Missing cv.placeholder.education in messages.'.$locale.'.yaml');
            foreach (['section', 'title', 'institution', 'period', 'description'] as $educationPlaceholderKey) {
                self::assertArrayHasKey(
                    $educationPlaceholderKey,
                    $educationPlaceholder,
                    'Missing cv.placeholder.education.'.$educationPlaceholderKey.' in messages.'.$locale.'.yaml'
                );
            }
        }
    }

    public function testCvExperienceFullRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertNotSame('', $router->generate('cv_experience_full'));
    }

    public function testCvEducationFullRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertNotSame('', $router->generate('cv_education_full'));
    }

    /**
     * @brief Full certifications page route must be registered for the « see more » link.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testCvCertificationsFullRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertNotSame('', $router->generate('cv_certifications_full'));
    }

    /**
     * @brief Public certification list, full page, controller, and admin markup must stay wired.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testCertificationPublicListFullPageAndAdminMarkup(): void
    {
        $controller = @file_get_contents(self::projectRoot().'/src/Controller/CvController.php') ?: '';
        self::assertStringContainsString('certificationsFull', $controller);
        self::assertStringContainsString("name: 'cv_certifications_full'", $controller);
        self::assertStringContainsString('certificationHasSecondaryVisible', $controller);
        self::assertStringContainsString('certifications_full.html.twig', $controller);
        self::assertStringContainsString("'_fragment' => 'certification'", $controller);

        $section = @file_get_contents(self::projectRoot().'/templates/components/cv/_certification.html.twig') ?: '';
        $list = @file_get_contents(self::projectRoot().'/templates/components/cv/_certification_list.html.twig') ?: '';
        $fullPage = @file_get_contents(self::projectRoot().'/templates/cv/certifications_full.html.twig') ?: '';
        $adminForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_certification_customization.html.twig') ?: '';
        $entryFields = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_certification_entry_modal.html.twig') ?: '';
        $adminJs = @file_get_contents(self::projectRoot().'/public/js/cv-certification-admin.js') ?: '';
        $publicCss = @file_get_contents(self::projectRoot().'/public/css/cv-certification.css') ?: '';

        self::assertStringContainsString('id="certification"', $section);
        self::assertStringContainsString('cv_certifications_full', $section);
        self::assertStringContainsString('certificationHasSecondaryVisible', $section);
        self::assertStringContainsString('see_more_aria_label', $section);
        self::assertStringContainsString('cv-about-accent-btn--outline', $section);
        self::assertStringContainsString('certificationFilter: \'primary\'', $section);

        self::assertStringContainsString('cv-certification__list', $list);
        self::assertStringContainsString('cv-certification__list-period', $list);
        self::assertStringContainsString('providerName', $list);
        self::assertStringContainsString('providerWebsiteUrl', $list);
        self::assertStringContainsString('cv-certification__list-summary', $list);
        self::assertStringContainsString('cv-certification__highlights', $list);
        self::assertStringContainsString('cv-certification__list-item--hidden-on-primary', $list);

        self::assertStringContainsString('proofPdfPath', $list);
        self::assertStringContainsString('proofUrl', $list);
        self::assertStringContainsString('cv-certification__proof-link', $list);
        self::assertStringContainsString('proof_pdf_link', $list);
        self::assertStringContainsString('proof_url_link', $list);

        self::assertFileExists(self::projectRoot().'/templates/cv/certifications_full.html.twig');
        self::assertStringContainsString('certificationEntriesFull', $fullPage);
        self::assertStringContainsString('highlightHiddenCertifications: true', $fullPage);
        self::assertStringContainsString('certificationFilter: \'all\'', $fullPage);
        self::assertStringContainsString('full_page_highlight_legend', $fullPage);
        self::assertStringContainsString('#certification', $fullPage);
        self::assertStringContainsString('_contact_modal.html.twig', $fullPage);
        self::assertStringContainsString('css/cv-certification.css', $fullPage);

        self::assertStringContainsString('cvCertificationCustomizationAccordion', $adminForm);
        self::assertStringContainsString('form_scope" value="certification_background"', $adminForm);
        self::assertStringContainsString('form_scope" value="{{ certificationFormScope }}"', $adminForm);
        self::assertStringContainsString('data-cv-certification-entry-provider-name', $entryFields);
        self::assertStringContainsString('data-cv-certification-entry-proof-file', $entryFields);
        self::assertStringContainsString('data-cv-certification-entry-proof-url', $entryFields);
        self::assertStringContainsString('data-cv-certification-entry-remove-proof', $entryFields);
        self::assertStringContainsString('field_proof_pdf', $entryFields);
        self::assertStringContainsString('field_proof_url', $entryFields);
        self::assertStringContainsString('enctype="multipart/form-data"', $adminForm);
        self::assertStringNotContainsString('providerLogo', $entryFields);
        self::assertStringContainsString('data-cv-certification-preview', $adminForm);
        self::assertStringContainsString('data-cv-certification-admin', $adminJs);

        self::assertStringContainsString('cv-certification__list-item--hidden-on-primary', $publicCss);

        $navLinks = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_nav_links.html.twig') ?: '';
        self::assertStringContainsString('publicNavVisibility', $navLinks);
        self::assertStringContainsString('navVisibility.certification', $navLinks);
    }

    /**
     * @brief Certification UI strings and placeholder keys must exist in all five locales.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testCertificationFallbackI18nExistsInAllLocales(): void
    {
        $locales = ['fr', 'en', 'de', 'lt', 'no'];
        $placeholderKeys = ['title', 'provider', 'period', 'description', 'section'];

        foreach ($locales as $locale) {
            $path = self::projectRoot().'/translations/messages.'.$locale.'.yaml';
            $data = Yaml::parseFile($path);
            self::assertIsArray($data);
            $certification = $data['cv']['certification'] ?? null;
            self::assertIsArray($certification, 'cv.certification missing in messages.'.$locale.'.yaml');
            $placeholder = $data['cv']['placeholder']['certification'] ?? null;
            self::assertIsArray($placeholder, 'cv.placeholder.certification missing in messages.'.$locale.'.yaml');
            foreach ($placeholderKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $placeholder,
                    'Missing cv.placeholder.certification.'.$key.' in messages.'.$locale.'.yaml'
                );
            }

            foreach (['title', 'see_more', 'full_page_title', 'full_page_highlight_legend', 'back_to_cv', 'list_aria_label', 'proof_pdf_link', 'proof_pdf_aria_label', 'proof_url_link', 'proof_url_aria_label'] as $key) {
                self::assertArrayHasKey($key, $certification, 'Missing cv.certification.'.$key.' in messages.'.$locale.'.yaml');
            }

            $customization = $data['dashboard']['customization_cv'] ?? null;
            self::assertIsArray($customization);
            self::assertArrayHasKey('certification', $customization);
            self::assertArrayHasKey('certification_accordion', $customization);
            self::assertArrayHasKey('certification_saved', $customization['flash']);
            self::assertArrayHasKey('certification_invalid', $customization['flash']);
            self::assertArrayHasKey('certification_invalid_pdf', $customization['flash']);
            self::assertArrayHasKey('field_proof_pdf', $customization['certification']);
            self::assertArrayHasKey('field_proof_pdf_help', $customization['certification']);
            self::assertArrayHasKey('field_proof_pdf_current', $customization['certification']);
            self::assertArrayHasKey('field_remove_proof_pdf', $customization['certification']);
            self::assertArrayHasKey('field_proof_url', $customization['certification']);
            self::assertArrayHasKey('field_proof_url_help', $customization['certification']);
        }
    }

    /**
     * @brief Full skills page route must be registered for the « see more » link.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testCvSkillsFullRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertNotSame('', $router->generate('cv_skills_full'));
    }

    /**
     * @brief Full projects page route must be registered for the « see more » link.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testCvProjectsFullRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertNotSame('', $router->generate('cv_projects_full'));
    }

    /**
     * @brief Skills catalog JSON admin routes must be registered for modal CRUD.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testCvSkillsCatalogAdminRoutesAreRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertNotSame('', $router->generate('admin_cv_skills_catalog_category_save'));
        self::assertNotSame('', $router->generate('admin_cv_skills_catalog_skill_save'));
    }

    public function testCvProfileControllerRegistersExperienceFormScope(): void
    {
        $php = @file_get_contents(self::projectRoot().'/src/Controller/Admin/CvProfileController.php') ?: '';
        self::assertStringContainsString("$formScope === 'experience'", $php);
        self::assertStringContainsString("$formScope === 'education'", $php);
        self::assertStringContainsString("$formScope === 'certification'", $php);
        self::assertStringContainsString("$formScope === 'situation_content'", $php);
        self::assertStringContainsString('handleExperienceUpdate', $php);
        self::assertStringContainsString('handleEducationUpdate', $php);
        self::assertStringContainsString('handleCertificationUpdate', $php);
        self::assertStringContainsString('CvCertificationAdminUpdateService', $php);
        self::assertStringContainsString('handleSituationContentUpdate', $php);
        self::assertStringContainsString('CvFlagshipProjectsAdminUpdateService', $php);
        self::assertStringContainsString('flashStructuredValidationErrors', $php);
        self::assertStringContainsString('cvSkillsCatalog', $php);
        self::assertFileExists(self::projectRoot().'/src/Controller/Admin/CvSkillsCatalogAdminController.php');
        self::assertStringContainsString('FlagshipProjectsContract', $php);
        self::assertStringContainsString('cvFlagshipProjectsSectionEnabled', $php);
        self::assertStringContainsString('SituationContentContract', $php);
        self::assertStringContainsString('cvSituationContentByLocale', $php);
        self::assertStringContainsString('handleSectionBackgroundUpdate', $php);
        self::assertStringNotContainsString("$formScope === 'situation_background'", $php);
        self::assertStringContainsString('SectionBackgroundContract', $php);
        self::assertStringContainsString('CvProfilePersistenceScope::sanitizeForPersistence', $php);
        self::assertStringContainsString('persistProfilePayload', $php);
        self::assertStringNotContainsString('handleSectionTransitionUpdate', $php);
        self::assertStringContainsString("trim(\$tabQuery) === 'experience'", $php);
        self::assertStringContainsString("trim(\$tabQuery) === 'certification'", $php);
        self::assertStringContainsString('CvExperienceAdminUpdateService', $php);
        self::assertStringContainsString('buildAdminPreviewPayloadByLocale', $php);
        self::assertStringContainsString('cvSituationPreviewByLocale', $php);
        self::assertStringContainsString("trim(\$tabQuery) === 'situation'", $php);
        self::assertStringContainsString('cvCertificationPreviewByLocale', $php);
        self::assertStringContainsString('cvCertificationEntries', $php);
    }

    /**
     * @brief Experience admin form supports logo upload; timeline renders brand block.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testExperienceLogoCustomizationAndTimelineMarkup(): void
    {
        $experienceForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_experience_customization.html.twig') ?: '';
        $entryAccordion = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_experience_entry_accordion.html.twig') ?: '';
        $entryLocaleFields = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_experience_entry_locale_fields.html.twig') ?: '';
        $entrySharedFields = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_experience_entry_shared_fields.html.twig') ?: '';
        $timeline = @file_get_contents(self::projectRoot().'/templates/components/cv/_experience_timeline.html.twig') ?: '';
        $adminJs = @file_get_contents(self::projectRoot().'/public/js/cv-experience-admin.js') ?: '';
        $experienceCss = @file_get_contents(self::projectRoot().'/public/css/cv-experience.css') ?: '';
        $adminCss = @file_get_contents(self::projectRoot().'/public/css/cv-experience-admin.css') ?: '';

        self::assertStringContainsString('cvExperienceCustomizationAccordion', $experienceForm);
        self::assertStringContainsString('data-customization-panel="section"', $experienceForm);
        self::assertStringContainsString('experience_accordion.section_customization_title', $experienceForm);
        self::assertStringContainsString('_experience_section_tone_fields.html.twig', $experienceForm);
        self::assertStringContainsString('form_scope" value="experience_background"', $experienceForm);
        $experienceToneFields = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_experience_section_tone_fields.html.twig') ?: '';
        self::assertStringContainsString('section_backgrounds[experience][aboutColorAdjustPercent]', $experienceToneFields);
        self::assertStringContainsString('data-cv-experience-tone-range', $experienceToneFields);
        self::assertStringContainsString('data-cv-experience-tone-range', $adminJs);
        self::assertStringContainsString('syncExperienceToneLabel', $adminJs);
        self::assertStringContainsString('data-customization-panel="professional_entries"', $experienceForm);
        self::assertStringContainsString('panel: \'professional_entries\'', $experienceForm);
        self::assertStringContainsString('data-active-locales', $experienceForm);
        self::assertStringContainsString('data-dashboard-locale', $experienceForm);
        self::assertStringContainsString('_experience_add_modal.html.twig', $experienceForm);
        self::assertStringContainsString('commitModalEntry', $adminJs);
        self::assertStringContainsString('collectValidatedModalPayload', $adminJs);
        self::assertStringContainsString('appendEntryAccordion', $adminJs);
        self::assertStringContainsString('syncSharedFieldsToAllLocalePanes', $adminJs);
        self::assertStringContainsString('readEntryTitleForDashboardLocale', $adminJs);
        self::assertStringContainsString('syncActiveExperienceDeepLinkFromDom', $adminJs);
        self::assertStringContainsString('bootstrapExperienceDeepLinkFromUrl', $adminJs);
        self::assertStringContainsString('data-cv-experience-is-primary', $entrySharedFields);
        self::assertStringContainsString('data-cv-experience-website', $entrySharedFields);
        self::assertStringContainsString('data-cv-experience-detail-html', $entryLocaleFields);
        self::assertStringContainsString('ckeditor-cv-rich', $entryLocaleFields);
        self::assertStringContainsString('[detailHtml]', $entryLocaleFields);
        self::assertStringContainsString('cv-experience__detail', $timeline);
        self::assertStringNotContainsString('data-cv-experience-highlights', $entryLocaleFields);
        self::assertStringNotContainsString('cv-experience-modal-highlight-template', $experienceForm);
        self::assertStringContainsString('detailHtml', $adminJs);
        self::assertStringContainsString('data-cv-experience-move', $entryAccordion);
        self::assertStringContainsString('data-cv-experience-remove', $entryAccordion);
        self::assertStringNotContainsString('<fieldset', $entryAccordion);
        self::assertStringContainsString('data-cv-experience-form-validation', $experienceForm);
        self::assertStringContainsString('cvExperienceEntriesAccordion', $experienceForm);
        self::assertStringContainsString('data-cv-experience-entry-toggle', $entryAccordion);
        self::assertStringContainsString('entriesByLocale[dashboardLocale]', $entryAccordion);
        self::assertStringContainsString('data-cv-experience-entry-collapse', $entryAccordion);
        self::assertStringContainsString('data-cv-experience-entry-locale-tab', $entryAccordion);
        self::assertStringNotContainsString('cvExperienceLocaleTabs', $experienceForm);
        self::assertStringContainsString('customization_entry', $experienceForm);
        $uiStateJs = @file_get_contents(self::projectRoot().'/public/js/customization-ui-state.js') ?: '';
        self::assertStringContainsString('bindExperienceEntryAccordions', $uiStateJs);
        self::assertStringContainsString('data-cv-experience-entry-collapse', $uiStateJs);
        $ckeditorInit = @file_get_contents(self::projectRoot().'/public/js/ckeditor-init.js') ?: '';
        self::assertStringContainsString('CvCkeditorBridge', $ckeditorInit);
        self::assertStringContainsString('data-cv-experience-entry-locale-tab', $ckeditorInit);
        self::assertStringContainsString('bindExperienceEntryLocaleEditors', $ckeditorInit);
        self::assertStringContainsString('bindExperienceEntryAccordionsShown', $ckeditorInit);
        self::assertStringContainsString('data-cv-experience-detail-html', $ckeditorInit);
        self::assertStringNotContainsString('data-cv-experience-add data-locale', $experienceForm);
        self::assertStringNotContainsString('data-customization-panel="section_background"', $experienceForm);
        self::assertStringNotContainsString('data-customization-panel-collapse="section_transition"', $experienceForm);
        $situationForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_situation_customization.html.twig') ?: '';
        self::assertStringNotContainsString('_cv_section_customization_accordion_items.html.twig', $situationForm);
        self::assertStringNotContainsString('_section_background_fields.html.twig', $situationForm);
        self::assertStringNotContainsString('section_backgrounds[', $situationForm);
        self::assertStringNotContainsString('data-customization-panel="section_background"', $situationForm);
        self::assertStringNotContainsString('data-customization-panel-collapse="section_transition"', $situationForm);
        self::assertStringNotContainsString('cvSituationCustomizationAccordion', $situationForm);
        self::assertStringContainsString('form_scope" value="{{ situationFormScope }}"', $situationForm);
        self::assertStringContainsString('name="customization_tab" value="about"', $situationForm);
        self::assertStringContainsString('data-cv-situation-preview', $situationForm);
        self::assertStringContainsString('_situation_content.html.twig', $situationForm);
        self::assertStringContainsString('data-customization-locale-tab="{{ locale }}"', $situationForm);
        self::assertStringContainsString('_situation_content_fields.html.twig', $situationForm);
        self::assertStringNotContainsString('fieldset_block_labels', $situationForm);
        self::assertStringContainsString('cv-situation-customization__form', $situationForm);
        $skillsForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_skills_customization.html.twig') ?: '';
        self::assertStringContainsString('data-customization-panel="skills_catalog"', $skillsForm);
        self::assertStringNotContainsString('_cv_section_customization_accordion_items.html.twig', $skillsForm);
        self::assertStringNotContainsString('_section_background_fields.html.twig', $skillsForm);
        self::assertStringNotContainsString('section_backgrounds[', $skillsForm);
        self::assertStringNotContainsString('data-customization-panel="section_background"', $skillsForm);
        self::assertStringContainsString('data-cv-skills-admin', $skillsForm);
        self::assertStringContainsString('_skills_admin_tree.html.twig', $skillsForm);
        self::assertStringContainsString('_skills_admin_modals.html.twig', $skillsForm);
        self::assertStringContainsString('skills_accordion.catalog_title', $skillsForm);
        self::assertFileExists(self::projectRoot().'/public/js/cv-skills-admin.js');
        $skillsModals = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_skills_admin_modals.html.twig') ?: '';
        self::assertStringContainsString('_bootstrap_icon_browser_modal.html.twig', $skillsModals);
        self::assertStringContainsString('data-cv-skills-bootstrap-icon-browse', $skillsModals);
        self::assertStringContainsString('cvSkillsSkillBootstrapIcon', $skillsModals);
        self::assertStringContainsString('data-bootstrap-icons-manifest-url', $skillsForm);
        $skillsAdminJs = @file_get_contents(self::projectRoot().'/public/js/cv-skills-admin.js') ?: '';
        self::assertStringContainsString('CvBootstrapIconBrowser', $skillsAdminJs);
        self::assertFileExists(self::projectRoot().'/public/js/cv-bootstrap-icon-browser.js');
        $interestsForm = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_interests_customization.html.twig') ?: '';
        self::assertStringContainsString('data-cv-interests-admin', $interestsForm);
        self::assertStringContainsString('_interests_entry_modal.html.twig', $interestsForm);
        self::assertStringContainsString('cvInterestsBootstrapIconBrowserModal', $interestsForm);
        $interestsModal = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_interests_entry_modal.html.twig') ?: '';
        self::assertStringContainsString('data-cv-interests-entry-icon-file', $interestsModal);
        self::assertStringContainsString('interests.modal.icon_upload', $interestsModal);
        self::assertFileExists(self::projectRoot().'/src/Service/Cv/CvInterestsIconUploadService.php');
        self::assertStringContainsString('data-cv-interests-add', $interestsForm);
        self::assertStringNotContainsString('_interests_entry_fields.html.twig', $interestsForm);
        $interestsAdminJs = @file_get_contents(self::projectRoot().'/public/js/cv-interests-admin.js') ?: '';
        self::assertStringContainsString('CvBootstrapIconBrowser', $interestsAdminJs);
        $skillsPublic = @file_get_contents(self::projectRoot().'/templates/components/cv/_skills.html.twig') ?: '';
        self::assertStringContainsString('skillsTreePrimary', $skillsPublic);
        self::assertStringContainsString('cv_skills_full', $skillsPublic);
        self::assertStringContainsString('skillsHasSecondaryVisible', $skillsPublic);
        self::assertStringContainsString('see_more_aria_label', $skillsPublic);
        self::assertStringContainsString('cv-about-accent-btn--outline', $skillsPublic);
        self::assertFileExists(self::projectRoot().'/templates/cv/skills_full.html.twig');
        self::assertFileExists(self::projectRoot().'/templates/cv/projects_full.html.twig');
        $projectsFullTwig = @file_get_contents(self::projectRoot().'/templates/cv/projects_full.html.twig') ?: '';
        self::assertStringContainsString('flagshipProjectsFull', $projectsFullTwig);
        self::assertStringContainsString('highlightHiddenProjects', $projectsFullTwig);
        self::assertStringContainsString('full_page_highlight_legend', $projectsFullTwig);
        $skillsFullTwig = @file_get_contents(self::projectRoot().'/templates/cv/skills_full.html.twig') ?: '';
        self::assertStringContainsString('_contact_modal.html.twig', $skillsFullTwig);
        self::assertStringContainsString('skillsTreeFull', $skillsFullTwig);
        self::assertStringContainsString('skillsTreeFull: skillsTreeFull', $skillsFullTwig);
        self::assertStringContainsString('highlightHiddenSkills', $skillsFullTwig);
        self::assertStringContainsString('full_page_highlight_legend', $skillsFullTwig);
        self::assertFileExists(self::projectRoot().'/templates/components/cv/_skills.html.twig');
        self::assertFileExists(self::projectRoot().'/public/js/cv-flagship-projects-admin.js');
        self::assertFileExists(self::projectRoot().'/templates/components/cv/admin/_flagship_project_entry_fields.html.twig');
        $projectsPublic = @file_get_contents(self::projectRoot().'/templates/components/cv/_projects.html.twig') ?: '';
        self::assertStringContainsString('flagshipProjects', $projectsPublic);
        self::assertStringContainsString('cv_projects_full', $projectsPublic);
        self::assertStringContainsString('flagshipProjectsHasSecondaryVisible', $projectsPublic);
        self::assertStringContainsString('see_more_aria_label', $projectsPublic);
        self::assertStringContainsString('_flagship_project_card.html.twig', $projectsPublic);
        $projectCard = @file_get_contents(self::projectRoot().'/templates/components/cv/_flagship_project_card.html.twig') ?: '';
        self::assertStringContainsString('project.previewImagePath', $projectCard);
        self::assertStringContainsString('project.codeLinkIsGithub', $projectCard);
        self::assertStringContainsString('cv.projects.link_code', $projectCard);
        self::assertStringContainsString('project.siteLinkLabel', $projectCard);
        self::assertStringContainsString('required_fields_legend', @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_flagship_project_customization.html.twig') ?: '');
        $flagshipEntryFields = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_flagship_project_entry_fields.html.twig') ?: '';
        self::assertStringContainsString('_required_field_marker.html.twig', $flagshipEntryFields);
        self::assertStringContainsString('site_link_label', $flagshipEntryFields);
        self::assertStringNotContainsString('stuslider-demo', $projectsPublic);
        self::assertStringNotContainsString('Steform', $projectsPublic);
        self::assertStringContainsString('cv.projects.empty', $projectsPublic);
        self::assertFileExists(self::projectRoot().'/public/'.FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH);
        $servicePhp = @file_get_contents(self::projectRoot().'/src/Service/Cv/CvAboutProfileSettingsService.php') ?: '';
        self::assertStringContainsString('buildSectionBackgroundVariablesCss', $servicePhp);
        self::assertStringContainsString('buildAllSectionTextureLayerCss', $servicePhp);
        self::assertFileDoesNotExist(self::projectRoot().'/public/css/cv-section-backgrounds.css');
        $showTwig = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('css/cv-public-static-bundle.css', $showTwig);
        self::assertStringContainsString('cv-contact-modal.js', $showTwig);
        self::assertStringContainsString('enctype="multipart/form-data"', $experienceForm);
        self::assertStringContainsString('experience_company_logo[', $entrySharedFields);
        self::assertStringContainsString('experience_remove_company_logo[', $entrySharedFields);
        self::assertStringContainsString('[hideCompanyName]', $entrySharedFields);
        self::assertStringContainsString('data-cv-experience-company-name', $entrySharedFields);
        self::assertStringContainsString('syncCompanyNameRequirement', $adminJs);
        self::assertStringContainsString('cv-experience__timeline-item', $timeline);
        $timelineHeading = @file_get_contents(self::projectRoot().'/templates/components/cv/_experience_timeline_entry_heading.html.twig') ?: '';
        self::assertStringContainsString('cv-experience__company-brand', $timelineHeading);
        self::assertStringContainsString('cv-experience__company-brand', $experienceCss);
        self::assertStringContainsString('cv-experience-customization__logo-preview', $adminCss);
        self::assertStringContainsString('data-cv-experience-preview', $experienceForm);
        self::assertStringContainsString('data-cv-experience-preview-locale', $experienceForm);
        self::assertStringContainsString('cvExperiencePreviewByLocale', $experienceForm);
        self::assertStringContainsString('shown.bs.tab', $adminJs);
        self::assertStringContainsString('showPreviewForLocale', $adminJs);

        $experienceAccordionKeys = [
            'section_customization_title',
            'section_customization_help',
            'about_color_adjust_legend',
            'about_color_adjust_help',
            'about_color_adjust_dark_label',
            'about_color_adjust_light_label',
            'about_color_adjust_value_neutral',
            'section_background_title',
            'section_background_help',
            'professional_entries_title',
            'professional_entries_help',
        ];
        $sectionOnlyAccordionKeys = ['section_customization_title'];
        $situationContentKeys = [
            'locale_tab_label',
            'locale_tabs_aria',
            'save',
            'flash_saved',
            'flash_invalid',
            'flash_invalid_csrf',
            'field_status_label',
            'field_intro_lead',
            'field_contract_chip',
            'field_geo_chips_dsl',
            'field_geo_chips_dsl_help',
            'field_mode_chips_dsl',
            'field_focus_chips_dsl',
        ];
        $experienceTextureKeys = ['legend', 'texture_1', 'texture_2', 'texture_3', 'texture_4', 'texture_5', 'texture_6'];
        $experienceBackgroundKeys = ['save', 'flash_saved', 'flash_invalid_csrf'];
        $logoKeys = [
            'field_company_logo',
            'field_company_logo_help',
            'field_company_logo_preview_alt',
            'field_remove_company_logo',
            'field_company_optional_with_logo',
            'field_hide_company_name',
            'field_hide_company_name_help',
            'preview_locale_help',
            'field_detail_html',
            'field_detail_html_help',
            'field_detail_html_h1_warning',
            'validation_overlap',
        ];
        $experienceModalKeys = [
            'title_add',
            'help',
            'shared_section_title',
            'localized_section_title',
            'confirm_add',
            'cancel',
            'close_aria',
            'validation_required',
            'validation_title_locale',
            'validation_company_or_logo',
        ];
        foreach (['fr', 'en', 'de', 'lt', 'no'] as $locale) {
            $messages = Yaml::parseFile(self::projectRoot().'/translations/messages.'.$locale.'.yaml');
            $customizationCv = $messages['dashboard']['customization_cv'] ?? null;
            self::assertIsArray($customizationCv, 'dashboard.customization_cv missing in messages.'.$locale.'.yaml');
            $experienceAccordion = $customizationCv['experience_accordion'] ?? null;
            self::assertIsArray($experienceAccordion, 'dashboard.customization_cv.experience_accordion missing in messages.'.$locale.'.yaml');
            foreach ($experienceAccordionKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $experienceAccordion,
                    'Missing dashboard.customization_cv.experience_accordion.'.$key.' in messages.'.$locale.'.yaml'
                );
            }
            $situationContent = $customizationCv['situation_content'] ?? null;
            self::assertIsArray($situationContent, 'dashboard.customization_cv.situation_content missing in messages.'.$locale.'.yaml');
            foreach ($situationContentKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $situationContent,
                    'Missing dashboard.customization_cv.situation_content.'.$key.' in messages.'.$locale.'.yaml'
                );
            }
            $experienceTexture = $customizationCv['experience_texture'] ?? null;
            self::assertIsArray($experienceTexture, 'dashboard.customization_cv.experience_texture missing in messages.'.$locale.'.yaml');
            foreach ($experienceTextureKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $experienceTexture,
                    'Missing dashboard.customization_cv.experience_texture.'.$key.' in messages.'.$locale.'.yaml'
                );
            }
            $experienceBackground = $customizationCv['experience_background'] ?? null;
            self::assertIsArray($experienceBackground, 'dashboard.customization_cv.experience_background missing in messages.'.$locale.'.yaml');
            foreach ($experienceBackgroundKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $experienceBackground,
                    'Missing dashboard.customization_cv.experience_background.'.$key.' in messages.'.$locale.'.yaml'
                );
            }
            $sectionBackground = $customizationCv['section_background'] ?? null;
            self::assertIsArray($sectionBackground, 'dashboard.customization_cv.section_background missing in messages.'.$locale.'.yaml');
            self::assertArrayHasKey('color_mode_about', $sectionBackground);
            self::assertArrayHasKey('filter_intensity_light_label', $sectionBackground);
            foreach (['skills_accordion', 'education_accordion', 'certification_accordion', 'profile_accordion', 'contact_accordion'] as $accordionBlock) {
                $block = $customizationCv[$accordionBlock] ?? null;
                self::assertIsArray($block, 'dashboard.customization_cv.'.$accordionBlock.' missing in messages.'.$locale.'.yaml');
                foreach ($sectionOnlyAccordionKeys as $key) {
                    self::assertArrayHasKey(
                        $key,
                        $block,
                        'Missing dashboard.customization_cv.'.$accordionBlock.'.'.$key.' in messages.'.$locale.'.yaml'
                    );
                }
            }
            $experience = $customizationCv['experience'] ?? null;
            self::assertIsArray($experience, 'dashboard.customization_cv.experience missing in messages.'.$locale.'.yaml');

            foreach ($logoKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $experience,
                    'Missing dashboard.customization_cv.experience.'.$key.' in messages.'.$locale.'.yaml'
                );
            }
            $experienceModal = $experience['modal'] ?? null;
            self::assertIsArray($experienceModal, 'dashboard.customization_cv.experience.modal missing in messages.'.$locale.'.yaml');
            foreach ($experienceModalKeys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $experienceModal,
                    'Missing dashboard.customization_cv.experience.modal.'.$key.' in messages.'.$locale.'.yaml'
                );
            }
        }
    }

    /**
     * @brief Public CV show uses sidebar menu only; main content has no section partials.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testPublicShowUsesSidebarMenuWithoutSections(): void
    {
        $show = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('css/cv-public-static-bundle.css', $show);
        self::assertStringContainsString("path('app_cv_public_sidebar_css'", $show);
        self::assertStringContainsString('_cv_public_shell.html.twig', $show);
        self::assertStringContainsString('embed ', $show);
        self::assertStringContainsString('components/cv/_about.html.twig', $show);
        self::assertStringContainsString('components/cv/_skills.html.twig', $show);
        self::assertStringContainsString('components/cv/_projects.html.twig', $show);
        self::assertStringContainsString('components/cv/_experience.html.twig', $show);
        self::assertStringContainsString('components/cv/_education.html.twig', $show);
        self::assertStringContainsString('components/cv/_certification.html.twig', $show);
        self::assertStringNotContainsString('components/cv/_situation_modal.html.twig', $show);
        self::assertStringContainsString('components/cv/_contact_modal.html.twig', $show);
        self::assertStringContainsString('cv-contact-modal.js', $show);
        self::assertStringNotContainsString('cv-situation-modal.js', $show);
        self::assertStringContainsString('cv-public-nav.js', $show);

        $shell = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_shell.html.twig') ?: '';
        self::assertStringContainsString('cv-public-layout', $shell);
        self::assertStringNotContainsString('col-lg-2', $shell);
        self::assertStringNotContainsString('col-lg-10', $shell);
        self::assertStringNotContainsString('cv-public-header', $shell);

        $brand = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_nav_brand.html.twig') ?: '';
        self::assertStringContainsString('site_favicon_href()', $brand);
        self::assertStringContainsString('bi-house-fill', $brand);
        self::assertStringContainsString("path('app_home')", $brand);

        $nav = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_nav.html.twig') ?: '';
        $navLinks = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_nav_links.html.twig') ?: '';
        self::assertStringContainsString('_cv_public_nav_brand.html.twig', $nav);
        self::assertStringContainsString('_cv_public_nav_links.html.twig', $nav);
        self::assertStringContainsString('cv.menu.section_about', $navLinks);
        self::assertStringContainsString('cvNavSectionBase', $navLinks);
        self::assertStringContainsString('#cvContactModal', $navLinks);
        self::assertStringContainsString('_cv_public_mobile_bar.html.twig', $shell);
        $mobileBar = @file_get_contents(self::projectRoot().'/templates/components/cv/_cv_public_mobile_bar.html.twig') ?: '';
        self::assertStringContainsString('cvPublicNavCollapse', $mobileBar);
        self::assertStringContainsString('data-bs-toggle="collapse"', $mobileBar);
        self::assertStringNotContainsString('offcanvas', $shell);
        self::assertStringNotContainsString('offcanvas', $mobileBar);

        $layoutCss = @file_get_contents(self::projectRoot().'/public/css/cv-public-layout.css') ?: '';
        self::assertStringContainsString('#17283c', $layoutCss);
    }

    /**
     * @brief Section transition i18n and admin picker must exist in all locales.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testContactModalI18nExistsInAllLocales(): void
    {
        $contactKeys = [
            'modal.title',
            'form.name_label',
            'form.submit',
            'flash.success',
            'flash.captcha_invalid',
        ];
        foreach (['fr', 'en', 'de', 'lt', 'no'] as $locale) {
            $messages = Yaml::parseFile(self::projectRoot().'/translations/messages.'.$locale.'.yaml');
            $contact = $messages['cv']['contact'] ?? null;
            self::assertIsArray($contact, 'cv.contact missing in messages.'.$locale.'.yaml');
            foreach ($contactKeys as $dottedKey) {
                $segments = explode('.', $dottedKey);
                $node = $contact;
                foreach ($segments as $segment) {
                    self::assertIsArray($node, 'Missing cv.contact.'.$dottedKey.' in messages.'.$locale.'.yaml');
                    self::assertArrayHasKey($segment, $node, 'Missing cv.contact.'.$dottedKey.' in messages.'.$locale.'.yaml');
                    $node = $node[$segment];
                }
            }
            self::assertArrayHasKey('tile_email_open_aria', $messages['cv']['about']['header'] ?? []);
            self::assertArrayHasKey('tile_cv_pdf_label', $messages['cv']['about']['header'] ?? []);
            self::assertArrayHasKey('tile_cv_pdf_aria', $messages['cv']['about']['header'] ?? []);
        }
    }

    /**
     * @brief Static About stylesheet must declare desktop dot-grid layer between background and content.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testAboutStaticCssDeclaresDesktopBackgroundDecorationLayers(): void
    {
        $css = @file_get_contents(self::projectRoot().'/public/css/cv-about.css') ?: '';
        self::assertStringContainsString('var(--about-bg-decor-dots-size', $css);
        self::assertStringContainsString('var(--about-bg-decor-dots-opacity', $css);
        self::assertStringContainsString('var(--about-bg-decor-enabled', $css);
        self::assertStringContainsString('var(--about-bg-decor-line-rgba', $css);
        self::assertStringContainsString('--about-bg-decor-dots-fill-rgba', $css);
        self::assertStringContainsString('--about-bg-decor-hatch-stroke-rgba', $css);
        self::assertStringContainsString('var(--about-bg-decor-dots-dot-size', $css);
        self::assertStringContainsString('--bg-decor-hex_zoom_mesh', $css);
        self::assertStringContainsString('--bg-decor-ambient_particles', $css);
        self::assertStringContainsString('--bg-decor-isometric_grid', $css);
        self::assertStringContainsString('--bg-decor-fine_grid', $css);
        self::assertStringContainsString('--bg-decor-dev_code_rain', $css);
        self::assertStringContainsString('--about-bg-decor-tint-rgba', $css);
        self::assertStringContainsString('--about-bg-decor-line-rgba', $css);
        self::assertStringContainsString('--about-bg-decor-intensity', $css);
        self::assertStringContainsString('--about-code-speed-factor', $css);
        self::assertStringContainsString('--about-particles-speed-factor', $css);
        self::assertStringContainsString('.cv-about-bg-decor-code-rain', $css);
        self::assertMatchesRegularExpression('/\.cv-about-bg-decor-code-rain\s*\{[^}]*display:\s*none;/s', $css);
        self::assertMatchesRegularExpression('/@media \(max-width: 991\.98px\)[\s\S]*\.cv-about-bg-decor-code-rain[\s\S]*display:\s*none\s*!important/s', $css);
        self::assertStringContainsString('mask-image: radial-gradient', $css);
    }
}
