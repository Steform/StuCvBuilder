<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Minimal translator for unit/functional tests constructing {@see \App\Service\Cv\CvPublicIdentityPlaceholderService}.
 * @date 2026-05-14
 * @author Stephane H.
 */
final class CvPdfPlaceholderTestTranslator
{
    /**
     * @brief Build a translator with `dashboard.cv_public_identity.placeholder_pdf_button` for all active CV locales.
     * @param void No input parameter.
     * @return TranslatorInterface Translator instance.
     * @date 2026-05-14
     * @author Stephane H.
     */
    public static function create(): TranslatorInterface
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $pdfLabels = [
            'fr' => 'Télécharger le CV PDF',
            'en' => 'Download CV PDF',
            'de' => 'Lebenslauf als PDF herunterladen',
            'lt' => 'Atsisiųsti CV PDF',
            'no' => 'Last ned CV som PDF',
        ];
        $lmPdfLabels = [
            'fr' => 'Télécharger la lettre de motivation PDF',
            'en' => 'Download cover letter PDF',
            'de' => 'Anschreiben als PDF herunterladen',
            'lt' => 'Atsisiųsti motyvacinį laišką PDF',
            'no' => 'Last ned søknadsbrev som PDF',
        ];
        foreach ($pdfLabels as $locale => $label) {
            $lmPdfLabel = $lmPdfLabels[$locale] ?? $lmPdfLabels['en'];
            $fallbackDisplayName = match ($locale) {
                'fr' => 'Votre nom',
                'en' => 'Your name',
                'de' => 'Ihr Name',
                'lt' => 'Jūsų vardas',
                'no' => 'Ditt navn',
                default => 'Your name',
            };
            $translator->addResource('array', [
                'dashboard.cv_public_identity.placeholder_pdf_button' => $label,
                'cv.about.pdf_download_button' => $label,
                'cv.about.lm_pdf_download_button' => $lmPdfLabel,
                'cv.about.learn_more_link' => match ($locale) {
                    'fr' => 'En savoir plus',
                    'en' => 'Learn more',
                    'de' => 'Mehr erfahren',
                    'lt' => 'Sužinoti daugiau',
                    'no' => 'Les mer',
                    default => 'Learn more',
                },
                'cv.about.learn_more_link_aria' => match ($locale) {
                    'fr' => 'En savoir plus sur ma situation professionnelle',
                    'en' => 'Learn more about my professional situation',
                    'de' => 'Mehr zu meiner beruflichen Situation',
                    'lt' => 'Sužinoti daugiau apie mano profesine situacija',
                    'no' => 'Les mer om min profesjonelle situasjon',
                    default => 'Learn more about my professional situation',
                },
                'cv.about.presentation_default.fallback_display_name' => $fallbackDisplayName,
                'site.seo.skills_prefix' => match ($locale) {
                    'fr' => 'Competences : %skills%.',
                    'en' => 'Skills: %skills%.',
                    default => 'Skills: %skills%.',
                },
                'cv.experience.period_current' => '%start% – Present',
                'cv.experience.period_range' => '%start% – %end%',
                'cv.education.period_current' => '%start% – Present',
                'cv.education.period_range' => '%start% – %end%',
                'cv.placeholder.experience.title' => 'cv.placeholder.experience.title',
                'cv.placeholder.experience.company' => 'cv.placeholder.experience.company',
                'cv.placeholder.experience.period' => 'cv.placeholder.experience.period',
                'cv.placeholder.experience.description' => 'cv.placeholder.experience.description',
                'cv.placeholder.education.title' => 'cv.placeholder.education.title',
                'cv.placeholder.education.institution' => 'cv.placeholder.education.institution',
                'cv.placeholder.education.period' => 'cv.placeholder.education.period',
                'cv.placeholder.education.description' => 'cv.placeholder.education.description',
                'cv.certification.period_current' => '%start% – Present',
                'cv.certification.period_range' => '%start% – %end%',
                'cv.placeholder.certification.title' => 'cv.placeholder.certification.title',
                'cv.placeholder.certification.provider' => 'cv.placeholder.certification.provider',
                'cv.placeholder.certification.period' => 'cv.placeholder.certification.period',
                'cv.placeholder.certification.description' => 'cv.placeholder.certification.description',
            ], $locale, 'messages');
        }

        return $translator;
    }
}
