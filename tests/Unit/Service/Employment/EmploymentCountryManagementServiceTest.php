<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\EmploymentCountry;
use App\Repository\EmploymentCountryRepository;
use App\Service\Employment\EmploymentCountryManagementService;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for employment country management.
 */
final class EmploymentCountryManagementServiceTest extends TestCase
{
    /**
     * @brief Rejects invalid ISO codes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreateRejectsInvalidCode(): void
    {
        $service = $this->buildService(null);

        $result = $service->create('France', 'France', 'fr');

        self::assertNull($result['country']);
        self::assertSame('employment.countries.flash.code_invalid', $result['error']);
    }

    /**
     * @brief Rejects duplicate country codes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreateRejectsDuplicateCode(): void
    {
        $existing = new EmploymentCountry('FR', 'France', 'fr');
        $service = $this->buildService($existing);

        $result = $service->create('FR', 'France 2', 'fr');

        self::assertNull($result['country']);
        self::assertSame('employment.countries.flash.code_duplicate', $result['error']);
    }

    /**
     * @brief Rejects inactive presentation locale.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreateRejectsInactiveLocale(): void
    {
        $service = $this->buildService(null, []);

        $result = $service->create('PL', 'Poland', 'pl');

        self::assertNull($result['country']);
        self::assertSame('employment.countries.flash.locale_invalid', $result['error']);
    }

    /**
     * @brief Persists valid country.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreatePersistsCountry(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(EmploymentCountry::class));
        $entityManager->expects(self::once())->method('flush');

        $repository = $this->createMock(EmploymentCountryRepository::class);
        $repository->method('findOneByCode')->willReturn(null);

        $service = new EmploymentCountryManagementService(
            $entityManager,
            $repository,
            $this->buildLocaleResolver(['fr', 'en', 'de']),
        );
        $result = $service->create('pl', 'Poland', 'en');

        self::assertInstanceOf(EmploymentCountry::class, $result['country']);
        self::assertSame('PL', $result['country']->getCode());
        self::assertSame('Poland', $result['country']->getLabel());
        self::assertSame('en', $result['country']->getPresentationLocale());
        self::assertNull($result['error']);
    }

    /**
     * @brief Updates country label and locale.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testUpdateChangesLabelAndLocale(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $repository = $this->createMock(EmploymentCountryRepository::class);
        $country = new EmploymentCountry('FR', 'France', 'fr');

        $service = new EmploymentCountryManagementService(
            $entityManager,
            $repository,
            $this->buildLocaleResolver(['fr', 'en', 'de']),
        );
        $error = $service->update($country, 'République française', 'de');

        self::assertNull($error);
        self::assertSame('République française', $country->getLabel());
        self::assertSame('de', $country->getPresentationLocale());
    }

    /**
     * @brief Build service with optional existing country.
     *
     * @param EmploymentCountry|null $existing Existing row for code lookup.
     * @param list<string>|null $activeLocales Active locales for resolver.
     * @return EmploymentCountryManagementService
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildService(?EmploymentCountry $existing, ?array $activeLocales = null): EmploymentCountryManagementService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EmploymentCountryRepository::class);
        $repository->method('findOneByCode')->willReturn($existing);

        return new EmploymentCountryManagementService(
            $entityManager,
            $repository,
            $this->buildLocaleResolver($activeLocales ?? ['fr', 'en', 'de', 'lt', 'no']),
        );
    }

    /**
     * @brief Build locale resolver mock.
     *
     * @param list<string> $activeLocales Active locale codes.
     * @return EmploymentCountryPresentationLocaleResolver
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildLocaleResolver(array $activeLocales): EmploymentCountryPresentationLocaleResolver
    {
        $resolver = $this->createMock(EmploymentCountryPresentationLocaleResolver::class);
        $resolver->method('getActiveLocales')->willReturn($activeLocales);
        $resolver->method('normalizeActiveLocale')->willReturnCallback(
            static function (string $locale) use ($activeLocales): ?string {
                $normalized = substr(strtolower(trim($locale)), 0, 2);

                return in_array($normalized, $activeLocales, true) ? $normalized : null;
            },
        );

        return $resolver;
    }
}
