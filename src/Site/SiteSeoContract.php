<?php

declare(strict_types=1);

namespace App\Site;

/**
 * @brief Validation bounds for site-wide SEO settings stored on home customization translations.
 */
final class SiteSeoContract
{
    public const MAX_META_DESCRIPTION_LENGTH = 320;

    public const SERP_META_DESCRIPTION_TARGET = 160;

    public const MAX_TITLE_SEGMENT_LENGTH = 60;

    public const OPEN_GRAPH_IMAGE_MIN_WIDTH = 1200;

    public const OPEN_GRAPH_IMAGE_MIN_HEIGHT = 630;

    public const REQUEST_FIELD_META_DESCRIPTION = 'site_seo_meta_description';
}
