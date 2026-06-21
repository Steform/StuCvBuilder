<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Service\Customization\CustomizationAssetScope;
use App\Service\Customization\CustomizationBackupManifestBuilder;
use App\Service\Customization\CustomizationBackupPaths;
use PHPUnit\Framework\TestCase;

final class CustomizationBackupManifestBuilderTest extends TestCase
{
    /**
     * @brief Optional fileScope is included when customizable image trees are exported.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testBuildAddsOptionalFileScope(): void
    {
        $builder = new CustomizationBackupManifestBuilder();
        $entries = [
            CustomizationBackupPaths::DATA_HOME => '{}',
        ];

        $manifest = $builder->build(
            $entries,
            'test',
            CustomizationAssetScope::FILE_SCOPE_CUSTOMIZABLE_ONLY,
        );

        self::assertSame(CustomizationBackupPaths::FORMAT_VERSION, $manifest['formatVersion']);
        self::assertSame(CustomizationAssetScope::FILE_SCOPE_CUSTOMIZABLE_ONLY, $manifest['fileScope']);
    }

    /**
     * @brief Legacy manifests omit fileScope when not provided.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testBuildOmitsFileScopeWhenNotProvided(): void
    {
        $builder = new CustomizationBackupManifestBuilder();
        $manifest = $builder->build([CustomizationBackupPaths::DATA_HOME => '{}'], 'test');

        self::assertArrayNotHasKey('fileScope', $manifest);
    }
}
