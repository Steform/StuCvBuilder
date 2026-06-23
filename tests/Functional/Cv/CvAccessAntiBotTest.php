<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Controller\CvController;
use App\Repository\CvProfileRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Cv\AboutPresentationDefaultContentService;
use App\Service\Cv\AboutSectionAtmospherePresetRegistry;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvExperienceSettingsService;
use App\Service\Cv\CvSituationContentSettingsService;
use App\Service\Cv\CvPublicIdentityPlaceholderService;
use App\Service\Cv\CvResolverService;
use App\Service\Locale\LocaleConfigurationService;
use App\Repository\TrackedCompanyRepository;
use App\Service\Http\RequestSessionResolver;
use App\Service\Employment\CompanyCodeNormalizer;
use App\Service\Employment\EmploymentDocumentPdfDeliveryService;
use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\Employment\CompanyCvVisitService;
use App\Service\Employment\CvConnectionLoggingService;
use App\Service\Employment\VisitorCountryResolver;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Security\AntiBotScoringService;
use App\Service\Security\CvBotAccessService;
use App\Service\Security\CvBotAttestationService;
use App\Service\Security\CssSanitizerService;
use App\Service\Site\SiteConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CvAccessAntiBotTest extends TestCase
{
    /**
     * @brief Build anti-bot scoring service with fixed threshold for tests.
     *
     * @param int $threshold Minimum score for KPI eligibility.
     * @return AntiBotScoringService
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createAntiBotScoringService(int $threshold = 50): AntiBotScoringService
    {
        $siteConfiguration = $this->createMock(SiteConfigurationService::class);
        $siteConfiguration->method('getCvAntibotThreshold')->willReturn($threshold);

        return new AntiBotScoringService($siteConfiguration);
    }

    /**
     * @brief Build access service with mocked authorization.
     *
     * @param bool $isAdmin Whether ROLE_ADMIN is granted.
     * @param bool $isConsult Whether ROLE_CV_CONSULT is granted.
     * @return CvBotAccessService
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createAccessService(bool $isAdmin = false, bool $isConsult = false): CvBotAccessService
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => match ($role) {
                'ROLE_ADMIN' => $isAdmin,
                'ROLE_CV_CONSULT' => $isConsult,
                default => false,
            }
        );

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $attestation = new CvBotAttestationService(new RequestSessionResolver($requestStack), 'test-secret', 3600);

        return new CvBotAccessService($auth, $attestation, $this->createAntiBotScoringService());
    }

    /**
     * @brief CvResolverService with deterministic locale configuration for tests.
     *
     * @return CvResolverService
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createCvResolverService(): CvResolverService
    {
        $resolver = $this->createMock(CvResolverService::class);
        $resolver->method('resolve')->willReturn([
            'view' => 'default',
            'formatCode' => '',
            'companyCode' => 'default',
            'companyResolved' => false,
            'title' => 'Test CV',
            'isPlaceholderMode' => false,
            'cvProfileId' => 1,
            'payload' => [],
            'targetFound' => false,
        ]);

        return $resolver;
    }

    /**
     * @brief Build mocked company visit service for controller tests.
     *
     * @param RequestStack $requestStack Request stack.
     * @param CvAccessSessionService $cvAccessSession Format session service.
     * @return CompanyCvVisitService
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function createCompanyCvVisitService(
        RequestStack $requestStack,
        CvAccessSessionService $cvAccessSession,
    ): CompanyCvVisitService {
        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        return new CompanyCvVisitService(
            $entityManager,
            $trackedCompanyRepository,
            $this->createMock(\App\Repository\CompanyCvVisitRepository::class),
            new CompanyCodeNormalizer(),
            $cvAccessSession,
            $this->createAccessService(),
            $this->createMock(CvBotAttestationService::class),
            $this->createMock(CvConnectionLoggingService::class),
            $this->createMock(VisitorCountryResolver::class),
            new RequestSessionResolver($requestStack),
            $this->createMock(\App\Service\Home\HomeCustomizationService::class),
            $this->createMock(\App\Service\Employment\CompanyRecruiterVisitNotificationService::class),
            $this->createMock(\App\Service\Notification\RecruiterVisitEmailNotificationService::class),
            $this->createMock(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class),
            'test-secret',
        );
    }

    /**
     * @brief Build CvController with session access state.
     *
     * @param bool $accessGranted Whether gate access is granted in session.
     * @param bool $adminBypass Whether admin bypass is active.
     * @return CvController
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createCvController(bool $accessGranted, bool $adminBypass = false): CvController
    {
        $session = new Session(new MockArraySessionStorage());
        if ($accessGranted) {
            $session->set(CvAccessSessionService::SESSION_ACCESS_VALID_UNTIL, time() + 1800);
        }

        $request = Request::create('/cv/');
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => $adminBypass && $role === 'ROLE_ADMIN'
        );

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository->method('findActiveByCode')->willReturn(null);

        $cvAccessSession = new CvAccessSessionService(
            $requestStack,
            $security,
            new CompanyCodeNormalizer(),
            $trackedCompanyRepository,
            $this->createMock(CvConnectionLoggingService::class),
            $this->createMock(VisitorCountryResolver::class),
            new RequestSessionResolver($requestStack),
            new NullLogger(),
        );

        return new TestableCvController(
            $this->createCvResolverService(),
            $cvAccessSession,
            $this->createCompanyCvVisitService($requestStack, $cvAccessSession),
            $this->createAccessService(isAdmin: $adminBypass),
            $this->createMock(EmploymentPublicDocumentPdfResolver::class),
            $this->createMock(EmploymentDocumentPdfDeliveryService::class),
        );
    }

    /**
     * @brief Admin bypass allows CV view without attestation.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAdminBypassAllowsView(): void
    {
        $access = $this->createAccessService(isAdmin: true);

        self::assertTrue($access->isBypassAllowed());
        self::assertTrue($access->canViewCv());
        self::assertFalse($access->isVisitCountable());
    }

    /**
     * @brief Consult role bypass allows CV view without attestation.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testConsultBypassAllowsView(): void
    {
        $access = $this->createAccessService(isConsult: true);

        self::assertTrue($access->isBypassAllowed());
        self::assertTrue($access->canViewCv());
    }

    /**
     * @brief Public visitor without attestation cannot view CV.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testPublicWithoutAttestationCannotView(): void
    {
        $access = $this->createAccessService();

        self::assertFalse($access->canViewCv());
    }

    /**
     * @brief Captcha attestation allows view and official company visit eligibility.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCaptchaAttestationAllowsViewAndCompanyVisit(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $attestation = new CvBotAttestationService(new RequestSessionResolver($requestStack), 'test-secret', 3600);
        $attestation->issueFromCaptcha();

        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $accessWithAttestation = new CvBotAccessService($auth, $attestation, $this->createAntiBotScoringService());

        self::assertTrue($accessWithAttestation->canViewCv());
        self::assertTrue($accessWithAttestation->isEligibleForCompanyVisit());
    }

    /**
     * @brief CV show renders when session access is granted (gate already passed).
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testCvShowRendersWhenSessionAccessGranted(): void
    {
        $controller = $this->createCvController(true);
        $response = $controller->show(new Request());

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @brief Admin bypass still allows CV render without session grant.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAdminBypassAllowsCvRender(): void
    {
        $controller = $this->createCvController(false, true);
        $response = $controller->show(new Request());

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @brief Resolved format uses sticky session value.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testShowUsesSessionFormatCode(): void
    {
        $validCode = 'Ab3xY9kLm2Qp';
        $session = new Session(new MockArraySessionStorage());
        $session->set(CvAccessSessionService::SESSION_ACCESS_VALID_UNTIL, time() + 1800);
        $session->set(CvAccessSessionService::SESSION_FORMAT_CODE, $validCode);
        $session->set(CvAccessSessionService::SESSION_FORMAT_VALID_UNTIL, time() + 604800);

        $request = Request::create('/cv/');
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(false);

        $company = new \App\Entity\TrackedCompany($validCode, 'Acme');
        $trackedCompanyRepository = $this->createMock(\App\Repository\TrackedCompanyRepository::class);
        $trackedCompanyRepository->method('findActiveByCode')->willReturn($company);

        $cvAccess = new CvAccessSessionService(
            $requestStack,
            $security,
            new \App\Service\Employment\CompanyCodeNormalizer(),
            $trackedCompanyRepository,
            $this->createMock(\App\Service\Employment\CvConnectionLoggingService::class),
            $this->createMock(\App\Service\Employment\VisitorCountryResolver::class),
            new RequestSessionResolver($requestStack),
            new NullLogger(),
        );

        self::assertSame($validCode, $cvAccess->resolveTargetFormatCode('Xy9kLm2QpAb3', $request));
    }
}

class TestableCvController extends CvController
{
    /**
     * @brief Return deterministic HTML response for tests.
     *
     * @param string $view Template name.
     * @param array<string, mixed> $parameters Template parameters.
     * @param Response|null $response Optional response.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return new Response('<html><body>cv</body></html>', 200);
    }
}
