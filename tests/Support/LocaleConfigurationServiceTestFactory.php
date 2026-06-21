<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Locale\LocaleConfigurationService;

/**
 * @brief Test double factory for locale configuration service.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class LocaleConfigurationServiceTestFactory
{
    /**
     * @brief Build locale configuration service stub for unit tests.
     *
     * @return LocaleConfigurationService
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function create(): LocaleConfigurationService
    {
        $service = new LocaleConfigurationService(
            ['fr', 'en', 'de', 'lt', 'no'],
            'en',
            sys_get_temp_dir(),
        );

        return $service;
    }
}
