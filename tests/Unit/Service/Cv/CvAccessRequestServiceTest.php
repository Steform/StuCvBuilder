<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\CvAccessRequestStatus;
use App\Entity\CvAccessRequest;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvAccessRequestService;
use App\Service\Employment\EmploymentCountryList;
use App\Service\Employment\TrackedCompanyManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvAccessRequestService}.
 */
final class CvAccessRequestServiceTest extends TestCase
{
    /**
     * @brief Submit rejects invalid email addresses.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testSubmitRejectsInvalidEmail(): void
    {
        $service = $this->createService();

        $result = $service->submit([
            'company_name' => 'Acme',
            'recruiter_name' => 'Jane Doe',
            'email' => 'not-an-email',
        ], '127.0.0.1', 'fr');

        self::assertNull($result['request']);
        self::assertSame('cv.access_request.flash.email_invalid', $result['errorKey']);
    }

    /**
     * @brief Submit persists a pending request for valid payload.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testSubmitPersistsPendingRequest(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(CvAccessRequest::class));
        $entityManager->expects(self::once())->method('flush');

        $countries = $this->createMock(EmploymentCountryList::class);
        $countries->method('isAllowed')->willReturn(true);

        $service = new CvAccessRequestService(
            $entityManager,
            $countries,
            $this->createMock(TrackedCompanyManagementService::class),
        );

        $result = $service->submit([
            'company_name' => 'Acme',
            'recruiter_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'country_code' => 'fr',
            'message' => 'Hello',
        ], '127.0.0.1', 'fr');

        self::assertNull($result['errorKey']);
        self::assertInstanceOf(CvAccessRequest::class, $result['request']);
        self::assertTrue($result['request']->isPending());
        self::assertSame('FR', $result['request']->getCountryCode());
    }

    /**
     * @brief Approve creates a tracked company and marks request approved.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testApproveCreatesCompany(): void
    {
        $request = new CvAccessRequest('Acme', 'Jane Doe', 'jane@example.com', 'FR');
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');

        $management = $this->createMock(TrackedCompanyManagementService::class);
        $management->expects(self::once())
            ->method('create')
            ->willReturn(['company' => $company, 'error' => null]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new CvAccessRequestService(
            $entityManager,
            $this->createMock(EmploymentCountryList::class),
            $management,
        );

        $result = $service->approve($request);

        self::assertNull($result['errorKey']);
        self::assertSame($company, $result['company']);
        self::assertSame(CvAccessRequestStatus::Approved, $request->getStatus());
        self::assertSame($company, $request->getTrackedCompany());
    }

    /**
     * @brief Approve refuses already reviewed requests.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testApproveRejectsAlreadyReviewedRequest(): void
    {
        $request = new CvAccessRequest('Acme', 'Jane Doe', 'jane@example.com');
        $request->reject('Nope');

        $management = $this->createMock(TrackedCompanyManagementService::class);
        $management->expects(self::never())->method('create');

        $service = new CvAccessRequestService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryList::class),
            $management,
        );

        $result = $service->approve($request);

        self::assertNull($result['company']);
        self::assertSame('admin.access_requests.flash.not_pending', $result['errorKey']);
    }

    /**
     * @brief Reject marks a pending request as rejected.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testRejectMarksRequestRejected(): void
    {
        $request = new CvAccessRequest('Acme', 'Jane Doe', 'jane@example.com');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new CvAccessRequestService(
            $entityManager,
            $this->createMock(EmploymentCountryList::class),
            $this->createMock(TrackedCompanyManagementService::class),
        );

        self::assertNull($service->reject($request, 'Not a fit'));
        self::assertSame(CvAccessRequestStatus::Rejected, $request->getStatus());
        self::assertSame('Not a fit', $request->getReviewerNote());
    }

    /**
     * @brief Build service with passive mocks.
     *
     * @return CvAccessRequestService
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function createService(): CvAccessRequestService
    {
        return new CvAccessRequestService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryList::class),
            $this->createMock(TrackedCompanyManagementService::class),
        );
    }
}
