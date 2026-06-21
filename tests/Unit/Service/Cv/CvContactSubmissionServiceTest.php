<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvContactSubmissionService;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for CV contact form validation.
 * @date 2026-05-23
 * @author Stephane H.
 */
final class CvContactSubmissionServiceTest extends TestCase
{
    private CvContactSubmissionService $service;

    protected function setUp(): void
    {
        $this->service = new CvContactSubmissionService();
    }

    /**
     * @brief Valid payload returns normalized data without error key.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testValidateAcceptsValidPayload(): void
    {
        $result = $this->service->validate([
            'contact_name' => 'Jane Doe',
            'contact_email' => 'jane@example.com',
            'contact_subject' => 'Hello',
            'contact_message' => 'This is a long enough message.',
        ]);

        self::assertNull($result['errorKey']);
        self::assertSame('Jane Doe', $result['data']['name']);
        self::assertSame('jane@example.com', $result['data']['email']);
    }

    /**
     * @brief HTML in message body is stripped before validation.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testValidateStripsHtmlFromMessage(): void
    {
        $result = $this->service->validate([
            'contact_name' => 'Jane Doe',
            'contact_email' => 'jane@example.com',
            'contact_subject' => 'Hello',
            'contact_message' => '<p>This is a safe message body.</p>',
        ]);

        self::assertNull($result['errorKey']);
        self::assertSame('This is a safe message body.', $result['data']['message']);
    }

    /**
     * @brief Short message returns validation error key.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testValidateRejectsShortMessage(): void
    {
        $result = $this->service->validate([
            'contact_name' => 'Jane Doe',
            'contact_email' => 'jane@example.com',
            'contact_subject' => 'Hello',
            'contact_message' => 'short',
        ]);

        self::assertSame('cv.contact.flash.message_invalid', $result['errorKey']);
    }
}
