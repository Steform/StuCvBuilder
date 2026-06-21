<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\AboutPresentationTypographyContract;

/**
 * @brief Builds scoped CSS font sizes for the public About presentation rich-text block.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class CvAboutPresentationTypographyCssBuilder
{
    /**
     * @brief Emit CSS variables and element rules for `.cv-about__presentation` only.
     *
     * @param array<string, string> $typography Normalized map element => CSS font-size.
     * @return string Safe CSS fragment.
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function buildCss(array $typography): string
    {
        $normalized = AboutPresentationTypographyContract::normalize($typography);
        $variableLines = [];
        $ruleLines = [];

        foreach (AboutPresentationTypographyContract::ELEMENT_KEYS as $elementKey) {
            $size = $normalized[$elementKey];
            $varName = '--cv-about-pres-size-'.$elementKey;
            $variableLines[] = '    '.$varName.': '.$size.';';

            if ($elementKey === AboutPresentationTypographyContract::ELEMENT_P) {
                $ruleLines[] = '.cv-about__presentation p {';
                $ruleLines[] = '    font-size: var('.$varName.');';
                $ruleLines[] = '}';

                continue;
            }

            $ruleLines[] = '.cv-about__presentation '.$elementKey.' {';
            $ruleLines[] = '    font-size: var('.$varName.');';
            $ruleLines[] = '}';
        }

        return ".cv-about__presentation {\n"
            .implode("\n", $variableLines)."\n"
            ."}\n\n"
            .implode("\n", $ruleLines)."\n";
    }
}
