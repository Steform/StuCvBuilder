<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Repository\TrackedCompanyRepository;

/**
 * Generates unique 12-character alphanumeric company codes.
 */
class CompanyCodeGenerator
{
    private const CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    private const MAX_ATTEMPTS = 50;

    /**
     * @brief Build company code generator.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
    ) {
    }

    /**
     * @brief Generate a unique company code.
     *
     * @param void No input parameter.
     * @return string Unique 12-character code.
     * @throws \RuntimeException When no unique code can be generated.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function generate(): string
    {
        $charsetLength = strlen(self::CHARSET);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            $code = '';
            for ($i = 0; $i < CompanyCodeNormalizer::CODE_LENGTH; ++$i) {
                $code .= self::CHARSET[random_int(0, $charsetLength - 1)];
            }

            if ($this->trackedCompanyRepository->findOneByCode($code) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique company code.');
    }
}
