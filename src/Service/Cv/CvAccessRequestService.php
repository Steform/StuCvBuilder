<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Entity\CvAccessRequest;
use App\Entity\TrackedCompany;
use App\Service\Employment\EmploymentCountryList;
use App\Service\Employment\TrackedCompanyContactInput;
use App\Service\Employment\TrackedCompanyDocumentInput;
use App\Service\Employment\TrackedCompanyManagementService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @brief Validate and process public CV access requests.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
class CvAccessRequestService
{
    private const MAX_COMPANY_NAME_LENGTH = 255;

    private const MAX_RECRUITER_NAME_LENGTH = 255;

    private const MAX_EMAIL_LENGTH = 255;

    private const MAX_MESSAGE_LENGTH = 1000;

    private const MAX_REVIEWER_NOTE_LENGTH = 1000;

    private const MIN_NAME_LENGTH = 2;

    /**
     * @brief Build access request service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param EmploymentCountryList $employmentCountryList Allowed countries.
     * @param TrackedCompanyManagementService $trackedCompanyManagementService Company creator.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmploymentCountryList $employmentCountryList,
        private readonly TrackedCompanyManagementService $trackedCompanyManagementService,
    ) {
    }

    /**
     * @brief Validate and persist a pending access request from public form data.
     *
     * @param array<string, mixed> $raw Submitted request payload.
     * @param string|null $submitterIp Client IP address.
     * @param string|null $locale Visitor locale.
     * @return array{request: CvAccessRequest|null, errorKey: string|null}
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function submit(array $raw, ?string $submitterIp, ?string $locale): array
    {
        $companyName = trim((string) ($raw['company_name'] ?? ''));
        $recruiterName = trim((string) ($raw['recruiter_name'] ?? ''));
        $email = trim((string) ($raw['email'] ?? ''));
        $countryCode = trim((string) ($raw['country_code'] ?? ''));
        $message = trim((string) ($raw['message'] ?? ''));

        if (mb_strlen($companyName) < self::MIN_NAME_LENGTH || mb_strlen($companyName) > self::MAX_COMPANY_NAME_LENGTH) {
            return ['request' => null, 'errorKey' => 'cv.access_request.flash.company_name_invalid'];
        }

        if (mb_strlen($recruiterName) < self::MIN_NAME_LENGTH || mb_strlen($recruiterName) > self::MAX_RECRUITER_NAME_LENGTH) {
            return ['request' => null, 'errorKey' => 'cv.access_request.flash.recruiter_name_invalid'];
        }

        if ($email === '' || mb_strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['request' => null, 'errorKey' => 'cv.access_request.flash.email_invalid'];
        }

        $normalizedCountry = null;
        if ($countryCode !== '') {
            $normalizedCountry = strtoupper($countryCode);
            if (!$this->employmentCountryList->isAllowed($normalizedCountry)) {
                return ['request' => null, 'errorKey' => 'cv.access_request.flash.country_invalid'];
            }
        }

        if ($message !== '' && mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return ['request' => null, 'errorKey' => 'cv.access_request.flash.message_invalid'];
        }

        $request = new CvAccessRequest(
            $companyName,
            $recruiterName,
            $email,
            $normalizedCountry,
            $message !== '' ? $message : null,
            $submitterIp,
            $locale !== null && $locale !== '' ? $locale : null,
        );

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return ['request' => $request, 'errorKey' => null];
    }

    /**
     * @brief Approve a pending request and create the tracked company.
     *
     * @param CvAccessRequest $request Access request to approve.
     * @param string|null $reviewerNote Optional admin note.
     * @return array{company: TrackedCompany|null, errorKey: string|null}
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function approve(CvAccessRequest $request, ?string $reviewerNote = null): array
    {
        if (!$request->isPending()) {
            return ['company' => null, 'errorKey' => 'admin.access_requests.flash.not_pending'];
        }

        $note = $this->normalizeReviewerNote($reviewerNote);
        if ($note === false) {
            return ['company' => null, 'errorKey' => 'admin.access_requests.flash.reviewer_note_invalid'];
        }

        $created = $this->trackedCompanyManagementService->create(
            $request->getCompanyName(),
            $request->getCountryCode(),
            new TrackedCompanyContactInput(
                $request->getRecruiterName(),
                null,
                null,
                null,
                null,
                null,
                $request->getEmail(),
            ),
            new TrackedCompanyDocumentInput(),
        );

        if ($created['error'] !== null || !$created['company'] instanceof TrackedCompany) {
            return ['company' => null, 'errorKey' => $created['error'] ?? 'admin.access_requests.flash.company_create_failed'];
        }

        $request->approve($created['company'], $note);
        $this->entityManager->flush();

        return ['company' => $created['company'], 'errorKey' => null];
    }

    /**
     * @brief Reject a pending access request.
     *
     * @param CvAccessRequest $request Access request to reject.
     * @param string|null $reviewerNote Optional admin note.
     * @return string|null Error translation key when rejection fails.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function reject(CvAccessRequest $request, ?string $reviewerNote = null): ?string
    {
        if (!$request->isPending()) {
            return 'admin.access_requests.flash.not_pending';
        }

        $note = $this->normalizeReviewerNote($reviewerNote);
        if ($note === false) {
            return 'admin.access_requests.flash.reviewer_note_invalid';
        }

        $request->reject($note);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @brief Normalize optional reviewer note or return false when invalid.
     *
     * @param string|null $reviewerNote Raw note.
     * @return string|null|false Normalized note, null when empty, false when invalid.
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function normalizeReviewerNote(?string $reviewerNote): string|null|false
    {
        if ($reviewerNote === null) {
            return null;
        }

        $trimmed = trim($reviewerNote);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_REVIEWER_NOTE_LENGTH) {
            return false;
        }

        return $trimmed;
    }
}
