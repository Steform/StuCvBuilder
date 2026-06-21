<?php

namespace App\Controller;

use App\Service\Security\CaptchaImageGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves CV captcha PNG images (multimedia-compatible).
 */
class CvCaptchaController extends AbstractController
{
    /**
     * @brief Generate captcha PNG and store code in session.
     *
     * @param CaptchaImageGenerator $captchaImageGenerator Captcha image builder.
     * @return Response PNG image response.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[Route('/cv/captcha', name: 'cv_captcha', methods: ['GET'])]
    public function captcha(CaptchaImageGenerator $captchaImageGenerator): Response
    {
        return $captchaImageGenerator->generateResponse();
    }
}
