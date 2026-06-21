<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * Validates CV public contact form payloads.
 */
final class CvContactSubmissionService
{
    private const NAME_MIN_LENGTH = 2;

    private const NAME_MAX_LENGTH = 120;

    private const SUBJECT_MIN_LENGTH = 2;

    private const SUBJECT_MAX_LENGTH = 200;

    private const MESSAGE_MIN_LENGTH = 10;

    private const MESSAGE_MAX_LENGTH = 5000;

    private const EMAIL_MAX_LENGTH = 180;

    /**
     * @brief Build CV contact submission validator.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function __construct()
    {
    }

    /**
     * @brief Normalize and validate contact form fields from request data.
     *
     * @param array<string, mixed> $payload Raw request fields (contact_name, contact_email, etc.).
     * @return array{data: array{name: string, email: string, subject: string, message: string}, errorKey: string|null}
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function validate(array $payload): array
    {
        $name = $this->normalizeScalar($payload['contact_name'] ?? '');
        $email = strtolower($this->normalizeScalar($payload['contact_email'] ?? ''));
        $subject = $this->normalizeScalar($payload['contact_subject'] ?? '');
        $message = $this->normalizeMessage($payload['contact_message'] ?? '');

        if ($name === '' || mb_strlen($name) < self::NAME_MIN_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.name_invalid'];
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.name_invalid'];
        }

        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.email_invalid'];
        }
        if (strlen($email) > self::EMAIL_MAX_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.email_invalid'];
        }

        if ($subject === '' || mb_strlen($subject) < self::SUBJECT_MIN_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.subject_invalid'];
        }
        if (mb_strlen($subject) > self::SUBJECT_MAX_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.subject_invalid'];
        }

        if ($message === '' || mb_strlen($message) < self::MESSAGE_MIN_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.message_invalid'];
        }
        if (mb_strlen($message) > self::MESSAGE_MAX_LENGTH) {
            return ['data' => [], 'errorKey' => 'cv.contact.flash.message_invalid'];
        }

        return [
            'data' => [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
            ],
            'errorKey' => null,
        ];
    }

    /**
     * @brief Trim and collapse whitespace on a scalar field.
     *
     * @param mixed $value Raw submitted value.
     * @return string Normalized string.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function normalizeScalar(mixed $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    /**
     * @brief Strip HTML and normalize message body.
     *
     * @param mixed $value Raw message value.
     * @return string Plain-text message.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function normalizeMessage(mixed $value): string
    {
        $plain = strip_tags((string) $value);

        return $this->normalizeScalar($plain);
    }
}
