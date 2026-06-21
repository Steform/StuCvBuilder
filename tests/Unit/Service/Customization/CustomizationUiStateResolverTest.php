<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Service\Customization\CustomizationUiStateResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see CustomizationUiStateResolver}.
 * @date 2026-05-17
 * @author Stephane H.
 */
final class CustomizationUiStateResolverTest extends TestCase
{
    private CustomizationUiStateResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CustomizationUiStateResolver();
    }

    /**
     * @brief Invalid home panel must fall back to background.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testResolveHomeStateRejectsInvalidPanel(): void
    {
        $state = $this->resolver->resolveHomeState('invalid', 'xx', ['fr', 'en'], 'fr');

        self::assertSame('background', $state->panel);
        self::assertSame('fr', $state->locale);
    }

    /**
     * @brief Site identity panel must be accepted for home customization accordion state.
     *
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testResolveHomeStateFallsBackWhenSiteIdentityPanelRemoved(): void
    {
        $state = $this->resolver->resolveHomeState('site_identity', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('background', $state->panel);
        self::assertSame(['panel' => 'background'], $this->resolver->buildHomeRedirectParams($state));
    }

    /**
     * @brief Home redirect must include locale only for texts panel.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBuildHomeRedirectParamsIncludesLocaleForTextsPanel(): void
    {
        $tiles = $this->resolver->resolveHomeState('tiles', 'lt', ['fr', 'en', 'lt'], 'fr');
        $texts = $this->resolver->resolveHomeState('texts', 'lt', ['fr', 'en', 'lt'], 'fr');

        self::assertSame(['panel' => 'tiles'], $this->resolver->buildHomeRedirectParams($tiles));
        self::assertSame(
            ['panel' => 'texts', 'locale' => 'lt'],
            $this->resolver->buildHomeRedirectParams($texts),
        );
    }

    /**
     * @brief Experience tab must default invalid panel to professional_entries.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testResolveCvStateForExperienceProfessionalEntries(): void
    {
        $state = $this->resolver->resolveCvState('experience', 'photo', 'no', ['fr', 'no'], 'fr');

        self::assertSame('experience', $state->tab);
        self::assertSame('professional_entries', $state->panel);
        self::assertSame('no', $state->locale);
        self::assertSame(
            ['tab' => 'experience', 'panel' => 'professional_entries', 'locale' => 'no'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Experience entry deep link must preserve UUID in redirect params.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testResolveCvStateForExperienceEntryDeepLink(): void
    {
        $entryId = '550e8400-e29b-41d4-a716-446655440000';
        $state = $this->resolver->resolveCvState(
            'experience',
            'professional_entries',
            'fr',
            ['fr', 'en'],
            'fr',
            $entryId,
        );

        self::assertSame($entryId, $state->entry);
        self::assertSame(
            ['tab' => 'experience', 'panel' => 'professional_entries', 'locale' => 'fr', 'entry' => $entryId],
            $this->resolver->buildCvRedirectParams($state),
        );

        $invalid = $this->resolver->resolveCvState('experience', 'professional_entries', 'fr', ['fr'], 'fr', 'not-a-uuid');
        self::assertNull($invalid->entry);
    }

    /**
     * @brief A valid experience entry deep link must open the professional entries panel.
     *
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function testResolveCvStateForExperienceEntryForcesProfessionalEntriesPanel(): void
    {
        $entryId = '550e8400-e29b-41d4-a716-446655440000';
        $state = $this->resolver->resolveCvState(
            'experience',
            'section',
            'fr',
            ['fr', 'en'],
            'fr',
            $entryId,
        );

        self::assertSame('professional_entries', $state->panel);
        self::assertSame($entryId, $state->entry);
        self::assertSame(
            ['tab' => 'experience', 'panel' => 'professional_entries', 'locale' => 'fr', 'entry' => $entryId],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Situation content panel must preserve locale in redirect params.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testResolveCvStateForSituationContentLocale(): void
    {
        $state = $this->resolver->resolveCvState('about', 'situation_content', 'lt', ['fr', 'lt', 'no'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('situation_content', $state->panel);
        self::assertSame('lt', $state->locale);
        self::assertSame(
            ['tab' => 'about', 'panel' => 'situation_content', 'locale' => 'lt'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief About photo save redirect must preserve tab and panel.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBuildCvRedirectParamsForAboutPhoto(): void
    {
        $state = $this->resolver->resolveCvState('about', 'photo', null, ['fr', 'en'], 'fr');

        self::assertSame(
            ['tab' => 'about', 'panel' => 'photo'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief About presentation save redirect must preserve accordion panel and locale tab.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testBuildCvRedirectParamsForAboutPresentationLocale(): void
    {
        $state = $this->resolver->resolveCvState('about', 'presentation', 'de', ['fr', 'de', 'en'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('presentation', $state->panel);
        self::assertSame('de', $state->locale);
        self::assertSame(
            ['tab' => 'about', 'panel' => 'presentation', 'locale' => 'de'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Request resolver must read POST hidden fields when query is empty.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    /**
     * @brief Custom quick tiles panel must be accepted for home customization accordion state.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testResolveHomeStateAcceptsCustomQuickTilesPanel(): void
    {
        $state = $this->resolver->resolveHomeState('custom_quick_tiles', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('custom_quick_tiles', $state->panel);
        self::assertSame(
            ['panel' => 'custom_quick_tiles'],
            $this->resolver->buildHomeRedirectParams($state),
        );
    }

    public function testResolveHomeFromRequestUsesPostHiddenFields(): void
    {
        $request = Request::create(
            '/dashboard/customization/home',
            'POST',
            [
                'customization_panel' => 'tiles',
                'customization_locale' => 'de',
            ],
        );

        $state = $this->resolver->resolveHomeFromRequest($request, ['fr', 'de'], 'fr');

        self::assertSame('tiles', $state->panel);
        self::assertSame('de', $state->locale);
    }

    /**
     * @brief Situation tab must resolve situation_content panel and preserve locale in redirect params.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testResolveCvStateForSituationContent(): void
    {
        $state = $this->resolver->resolveCvState('about', 'situation_content', 'de', ['fr', 'en', 'de'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('situation_content', $state->panel);
        self::assertSame(
            ['tab' => 'about', 'panel' => 'situation_content', 'locale' => 'de'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Legacy situation tab slug must redirect to About learn-more panel.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testResolveCvStateRemapsLegacySituationTab(): void
    {
        $state = $this->resolver->resolveCvState('situation', 'situation_content', 'de', ['fr', 'en', 'de'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('situation_content', $state->panel);
        self::assertSame(
            ['tab' => 'about', 'panel' => 'situation_content', 'locale' => 'de'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Legacy situation section_background panel slug must fall back to situation_content.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testResolveCvStateForSituationRejectsSectionBackgroundPanel(): void
    {
        $state = $this->resolver->resolveCvState('situation', 'section_background', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('situation_content', $state->panel);
    }

    /**
     * @brief Legacy skills section_background panel slug must fall back to skills_catalog.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testResolveCvStateForSkillsRejectsSectionBackgroundPanel(): void
    {
        $state = $this->resolver->resolveCvState('skills', 'section_background', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('skills', $state->tab);
        self::assertSame('skills_catalog', $state->panel);
    }

    /**
     * @brief Situation panel must be ignored when tab is not situation.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testResolveCvStateIgnoresPanelForSkillsTab(): void
    {
        $state = $this->resolver->resolveCvState('about', 'skills_catalog', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('section', $state->panel);
    }

    /**
     * @brief Experience panel must be ignored when tab is not experience.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testResolveCvStateIgnoresExperiencePanelForAboutTab(): void
    {
        $state = $this->resolver->resolveCvState('about', 'professional_entries', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('about', $state->tab);
        self::assertSame('section', $state->panel);
    }

    /**
     * @brief Legacy About panel slugs must fall back to section customization panel.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testResolveCvStateFallsBackLegacyAboutPanelsToSection(): void
    {
        $state = $this->resolver->resolveCvState('about', 'background', 'fr', ['fr', 'en'], 'fr');

        self::assertSame('section', $state->panel);
    }

    /**
     * @brief Flagship project entry deep link must preserve UUID in redirect params.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testResolveCvStateForFlagshipProjectEntryDeepLink(): void
    {
        $entryId = '550e8400-e29b-41d4-a716-446655440000';
        $state = $this->resolver->resolveCvState(
            'flagship_projects',
            'situation_content',
            'fr',
            ['fr', 'en'],
            'fr',
            $entryId,
        );

        self::assertSame('flagship_projects', $state->tab);
        self::assertNull($state->panel);
        self::assertSame($entryId, $state->entry);
        self::assertSame(
            ['tab' => 'flagship_projects', 'entry' => $entryId],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Legacy page_title tab slug must map to cv_data with locale in redirect params.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testResolveCvStateMapsLegacyPageTitleTabToCvData(): void
    {
        $state = $this->resolver->resolveCvState('page_title', null, 'de', ['fr', 'de', 'en'], 'fr');

        self::assertSame('cv_data', $state->tab);
        self::assertNull($state->panel);
        self::assertSame('de', $state->locale);
        self::assertSame(
            ['tab' => 'cv_data', 'locale' => 'de'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }

    /**
     * @brief Languages tab must default to languages_entries panel.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testResolveCvStateForLanguagesTab(): void
    {
        $state = $this->resolver->resolveCvState('languages', 'invalid', null, ['fr', 'en'], 'fr');

        self::assertSame('languages', $state->tab);
        self::assertSame('languages_entries', $state->panel);
        self::assertSame(['tab' => 'languages', 'panel' => 'languages_entries'], $this->resolver->buildCvRedirectParams($state));
    }

    /**
     * @brief References tab must preserve locale in redirect params.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testBuildCvRedirectParamsForReferencesTabIncludesLocale(): void
    {
        $state = $this->resolver->resolveCvState('references', 'references_entries', 'en', ['fr', 'en'], 'fr');

        self::assertSame(
            ['tab' => 'references', 'panel' => 'references_entries', 'locale' => 'en'],
            $this->resolver->buildCvRedirectParams($state),
        );
    }
}
