<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin Certification customization POST fields to a CV content JSON payload slice.
 */
class CvCertificationAdminUpdateService
{
    /**
     * @brief Wire Certification admin update service.
     *
     * @param CvCertificationProofUploadService $cvCertificationProofUploadService Proof PDF upload service.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvCertificationProofUploadService $cvCertificationProofUploadService,
    ) {
    }

    /**
     * @brief Parse, merge proof PDF uploads, and normalize certification entries from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload slice or full profile.
     * @param Request $request HTTP request with flat `certification_entries` and optional proof PDF uploads.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale used for required field validation.
     * @return array{
     *     payload: array<string, mixed>,
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>
     * }
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function applyCertificationFromRequest(
        array $payload,
        Request $request,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $rawEntries = CertificationContract::parseRawEntriesFromRequest($request);
        if ($rawEntries === null) {
            $flashError[] = 'dashboard.customization_cv.flash.certification_invalid';

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $previousEntries = CertificationContract::entriesFromStoredPayload($payload, $activeLocales, $defaultLocale);

        try {
            $this->applyCertificationProofPdfUploads($request, $rawEntries, $previousEntries);
        } catch (\InvalidArgumentException $exception) {
            $flashWarning[] = $exception->getMessage();

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $parsed = CertificationContract::normalizeEntries($rawEntries, $activeLocales, $defaultLocale);
        if ($parsed === null) {
            $flashError[] = 'dashboard.customization_cv.flash.certification_invalid';

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $payload[CertificationContract::KEY_ENTRIES] = $parsed;
        unset($payload[CertificationContract::KEY_ENTRIES_BY_LOCALE]);

        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Merge uploaded or removed certification proof PDFs into raw rows before normalization.
     *
     * @param Request $request HTTP request with file bag and remove flags keyed by entry id.
     * @param list<array<string, mixed>> $rawEntries Raw flat rows (mutated in place).
     * @param list<array<string, mixed>> $previousEntries Previously stored normalized entries.
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function applyCertificationProofPdfUploads(Request $request, array &$rawEntries, array $previousEntries): void
    {
        $previousById = [];
        foreach ($previousEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryId = is_string($entry['id'] ?? null) ? $entry['id'] : '';
            if ($entryId !== '') {
                $previousById[$entryId] = $entry;
            }
        }

        $removeFlags = $request->request->all('certification_remove_proof_pdf');

        foreach ($rawEntries as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $entryId = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
            if ($entryId === '') {
                continue;
            }

            $existingPath = CertificationContract::normalizeStoredProofPdfPath($row['proofPdfPath'] ?? null);
            if ($existingPath === null && isset($previousById[$entryId])) {
                $existingPath = CertificationContract::normalizeStoredProofPdfPath($previousById[$entryId]['proofPdfPath'] ?? null);
            }

            $shouldRemove = is_array($removeFlags) && self::normalizeBoolFromRequest($removeFlags[$entryId] ?? false);
            $upload = $request->files->get('certification_proof_pdf');
            $uploadedFile = null;
            if (is_array($upload) && isset($upload[$entryId]) && $upload[$entryId] instanceof UploadedFile) {
                $candidate = $upload[$entryId];
                if ($candidate->isValid()) {
                    $uploadedFile = $candidate;
                }
            }

            if ($uploadedFile instanceof UploadedFile) {
                $newPath = $this->cvCertificationProofUploadService->store($uploadedFile, $entryId);
                if ($existingPath !== null) {
                    $this->cvCertificationProofUploadService->deleteIfStored($existingPath);
                }
                $row['proofPdfPath'] = $newPath;

                continue;
            }

            if ($shouldRemove) {
                if ($existingPath !== null) {
                    $this->cvCertificationProofUploadService->deleteIfStored($existingPath);
                }
                $row['proofPdfPath'] = null;

                continue;
            }

            $row['proofPdfPath'] = $existingPath;
        }
        unset($row);
    }

    /**
     * @brief Normalize checkbox-like request values to bool.
     *
     * @param mixed $value Raw request value.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    private static function normalizeBoolFromRequest(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }
}
