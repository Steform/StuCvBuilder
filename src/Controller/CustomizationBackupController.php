<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\Customization\CustomizationBackupException;
use App\Service\Auth\AdminReauthenticationService;
use App\Service\Customization\CustomizationBackupCryptoService;
use App\Service\Customization\CustomizationBackupExportService;
use App\Service\Customization\CustomizationBackupImportService;
use App\Service\Customization\CustomizationBackupPolicyService;
use App\Service\Customization\CustomizationPreResetBackupWriter;
use App\Service\Customization\CustomizationResetService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;
use Twig\Environment;

/**
 * @brief Admin dashboard for encrypted customization backup export and restore.
 */
final class CustomizationBackupController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AdminReauthenticationService $adminReauthenticationService,
    ) {
    }

    /**
     * @brief Render backup page and handle export, restore, or reset actions.
     *
     * @param Request $request Current HTTP request.
     * @param Environment $twig Twig environment.
     * @param CustomizationBackupPolicyService $policyService Backup policy guard.
     * @param CustomizationBackupExportService $exportService Export pipeline.
     * @param CustomizationBackupImportService $importService Import pipeline.
     * @param CustomizationResetService $resetService Reset wipe pipeline.
     * @param CustomizationPreResetBackupWriter $preResetBackupWriter Server-side snapshot storage.
     * @param CustomizationBackupCryptoService $cryptoService Encryption guard for reset pre-backup.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[IsGranted('ROLE_CV_EDIT')]
    #[Route('/dashboard/customization/backup', name: 'app_dashboard_customization_backup', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        Environment $twig,
        CustomizationBackupPolicyService $policyService,
        CustomizationBackupExportService $exportService,
        CustomizationBackupImportService $importService,
        CustomizationResetService $resetService,
        CustomizationPreResetBackupWriter $preResetBackupWriter,
        CustomizationBackupCryptoService $cryptoService,
    ): Response {
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');

            if ($action === 'export') {
                return $this->handleExport($request, $policyService, $exportService);
            }

            if ($action === 'restore') {
                return $this->handleRestore($request, $policyService, $importService);
            }

            if ($action === 'reset') {
                return $this->handleReset($request, $policyService, $resetService, $cryptoService);
            }

            if ($action === 'delete_snapshot') {
                return $this->handleDeleteSnapshot($request, $policyService, $preResetBackupWriter);
            }

            if ($action === 'send_reauth_totp') {
                return $this->handleSendReauthTotp();
            }

            $this->addFlash('warning', 'dashboard.customization_backup.flash.invalid_action');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        return new Response($twig->render('admin/customization/backup.html.twig', [
            'exportAllowed' => $policyService->isExportAllowed($request),
            'restoreAllowed' => $policyService->isRestoreAllowed($request),
            'resetAllowed' => $policyService->isResetAllowed($request) && $cryptoService->isConfigured(),
            'cvEditBackupRestricted' => $policyService->isCvEditBackupRestrictedForCurrentUser(),
            'preResetSnapshots' => $preResetBackupWriter->listSnapshots(),
        ]));
    }

    /**
     * @brief Handle encrypted backup export download.
     *
     * @param Request $request Current HTTP request.
     * @param CustomizationBackupPolicyService $policyService Backup policy guard.
     * @param CustomizationBackupExportService $exportService Export pipeline.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function handleExport(
        Request $request,
        CustomizationBackupPolicyService $policyService,
        CustomizationBackupExportService $exportService,
    ): Response {
        if (!$this->isCsrfTokenValid('customization_backup_export', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.invalid_csrf');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $exportDenial = $policyService->getExportDenialReason($request);
        if ($exportDenial !== null) {
            $this->addPolicyDenialFlash('export', $exportDenial, $request);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        try {
            $result = $exportService->export();
        } catch (Throwable $exception) {
            $this->addBackupFlash('warning', $exception);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $response = new Response($result['content']);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $result['filename']
        );
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @brief Handle encrypted backup restore upload.
     *
     * @param Request $request Current HTTP request.
     * @param CustomizationBackupPolicyService $policyService Backup policy guard.
     * @param CustomizationBackupImportService $importService Import pipeline.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function handleRestore(
        Request $request,
        CustomizationBackupPolicyService $policyService,
        CustomizationBackupImportService $importService,
    ): Response {
        if (!$this->isCsrfTokenValid('customization_backup_restore', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.invalid_csrf');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $restoreDenial = $policyService->getRestoreDenialReason($request);
        if ($restoreDenial !== null) {
            $this->addPolicyDenialFlash('restore', $restoreDenial, $request);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        if (!$request->request->getBoolean('confirm_restore')) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.confirm_required');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $reauthError = $this->validateReauthentication($request);
        if ($reauthError !== null) {
            $this->addFlash('warning', $reauthError);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $upload = $request->files->get('backup_file');
        if (!$upload instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.file_required');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        try {
            $importService->restoreFromUpload($upload);
        } catch (Throwable $exception) {
            $this->addBackupFlash('warning', $exception);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $this->addFlash('success', 'dashboard.customization_backup.flash.restore_success');
        $this->logger->info('Customization backup restored', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'client_ip' => $request->getClientIp(),
        ]);

        return $this->redirectToRoute('app_dashboard_customization_backup');
    }

    /**
     * @brief Handle full customization reset with mandatory server-side pre-backup.
     *
     * @param Request $request Current HTTP request.
     * @param CustomizationBackupPolicyService $policyService Backup policy guard.
     * @param CustomizationResetService $resetService Reset pipeline.
     * @param CustomizationBackupCryptoService $cryptoService Encryption guard.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function handleReset(
        Request $request,
        CustomizationBackupPolicyService $policyService,
        CustomizationResetService $resetService,
        CustomizationBackupCryptoService $cryptoService,
    ): Response {
        if (!$this->isCsrfTokenValid('customization_backup_reset', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.invalid_csrf');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        if (!$cryptoService->isConfigured()) {
            $this->addFlash('warning', 'dashboard.customization_backup.error.key_missing');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $resetDenial = $policyService->getResetDenialReason($request);
        if ($resetDenial !== null) {
            $this->addPolicyDenialFlash('reset', $resetDenial, $request);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        if (!$request->request->getBoolean('confirm_reset')) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.reset_confirm_required');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $reauthError = $this->validateReauthentication($request);
        if ($reauthError !== null) {
            $this->addFlash('warning', $reauthError);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        try {
            $snapshotBasename = $resetService->reset();
        } catch (Throwable $exception) {
            $this->addBackupFlash('warning', $exception);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $this->addFlash('success', 'dashboard.customization_backup.flash.reset_success');
        $this->addTranslatedFlash('info', 'dashboard.customization_backup.flash.prebackup_created', [
            '%filename%' => $snapshotBasename,
        ]);
        $this->logger->warning('Customization reset executed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'client_ip' => $request->getClientIp(),
            'snapshot' => $snapshotBasename,
        ]);

        return $this->redirectToRoute('app_dashboard_customization_backup');
    }

    /**
     * @brief Delete one server-side pre-reset snapshot file.
     *
     * @param Request $request Current HTTP request.
     * @param CustomizationBackupPolicyService $policyService Backup policy guard.
     * @param CustomizationPreResetBackupWriter $preResetBackupWriter Snapshot storage.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function handleDeleteSnapshot(
        Request $request,
        CustomizationBackupPolicyService $policyService,
        CustomizationPreResetBackupWriter $preResetBackupWriter,
    ): Response {
        if (!$this->isCsrfTokenValid('customization_backup_delete_snapshot', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.invalid_csrf');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $resetDenial = $policyService->getResetDenialReason($request);
        if ($resetDenial !== null) {
            $this->addPolicyDenialFlash('reset', $resetDenial, $request);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $basename = (string) $request->request->get('snapshot_basename', '');
        try {
            $preResetBackupWriter->deleteSnapshot($basename);
        } catch (Throwable $exception) {
            $this->addBackupFlash('warning', $exception);

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $this->addFlash('success', 'dashboard.customization_backup.flash.snapshot_deleted');

        return $this->redirectToRoute('app_dashboard_customization_backup');
    }

    /**
     * @brief Queue a translated flash message from a backup-related throwable.
     *
     * @param string $type Flash bag type (success, warning, danger, info).
     * @param Throwable $exception Failure to expose to the admin user.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function addBackupFlash(string $type, Throwable $exception): void
    {
        if ($exception instanceof CustomizationBackupException) {
            $this->addTranslatedFlash($type, $exception->getTranslationKey(), $exception->getTranslationParameters());
            $logContext = [
                'reason' => $exception->getReasonCode(),
                'translation_key' => $exception->getTranslationKey(),
                'step' => $exception->getTranslationParameters()['%step%'] ?? null,
            ];
            $previous = $exception->getPrevious();
            if ($previous !== null) {
                $logContext['exception_class'] = $previous::class;
                $logContext['exception_message'] = $previous->getMessage();
                $logContext['trace'] = $previous->getTraceAsString();
            }
            $this->logger->warning('Customization backup operation failed', $logContext);

            return;
        }

        if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'dashboard.customization_backup.')) {
            $this->addFlash($type, $exception->getMessage());

            return;
        }

        $this->logger->warning('Customization backup operation failed', [
            'reason' => 'unexpected',
            'exception' => $exception->getMessage(),
        ]);
        $this->addFlash($type, 'dashboard.customization_backup.error.restore_failed');
    }

    /**
     * @brief Queue a policy denial flash with config vs IP distinction.
     *
     * @param string $action One of export, restore, or reset.
     * @param string $denialReason Policy denial code from CustomizationBackupPolicyService.
     * @param Request $request Current HTTP request for client IP resolution.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function addPolicyDenialFlash(string $action, string $denialReason, Request $request): void
    {
        if ($denialReason === CustomizationBackupPolicyService::DENIAL_CV_EDIT_BACKUP_DISABLED) {
            $this->addTranslatedFlash('warning', 'dashboard.customization_backup.flash.cv_edit_backup_disabled');

            return;
        }

        if ($denialReason === CustomizationBackupPolicyService::DENIAL_IP_NOT_ALLOWED) {
            $this->addTranslatedFlash('warning', sprintf('dashboard.customization_backup.flash.%s_disabled_ip', $action), [
                '%client_ip%' => (string) ($request->getClientIp() ?? ''),
            ]);

            return;
        }

        $this->addTranslatedFlash('warning', sprintf('dashboard.customization_backup.flash.%s_disabled_flag', $action));
    }

    /**
     * @brief Queue a flash message with translation parameters (Symfony 8 addFlash accepts only type + payload).
     *
     * @param string $type Flash bag type (success, warning, danger, info).
     * @param string $translationKey Symfony translation key in messages domain.
     * @param array<string, string|int> $parameters Placeholder map for the trans filter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function addTranslatedFlash(string $type, string $translationKey, array $parameters = []): void
    {
        if ($parameters === []) {
            $this->addFlash($type, $translationKey);

            return;
        }

        $this->addFlash($type, [
            'message' => $translationKey,
            'parameters' => $parameters,
        ]);
    }

    /**
     * @brief Send backup re-authentication TOTP to the current admin email.
     *
     * @return Response
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function handleSendReauthTotp(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('warning', 'dashboard.customization_backup.flash.reauth_required');

            return $this->redirectToRoute('app_dashboard_customization_backup');
        }

        $this->adminReauthenticationService->sendReauthenticationTotp($user);
        $this->addFlash('success', 'dashboard.customization_backup.flash.reauth_totp_sent');

        return $this->redirectToRoute('app_dashboard_customization_backup');
    }

    /**
     * @brief Validate password and TOTP before destructive backup actions.
     *
     * @param Request $request Current HTTP request.
     * @return string|null Translation key when validation fails.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function validateReauthentication(Request $request): ?string
    {
        $user = $this->getUser();
        // #region agent log
        $passwordRaw = (string) $request->request->get('reauth_password', '');
        $totpRaw = (string) $request->request->get('reauth_totp', '');
        $debugPayload = json_encode([
            'sessionId' => '08d76b',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1-H5',
            'location' => 'CustomizationBackupController.php:validateReauthentication',
            'message' => 'Reauth validation input snapshot',
            'data' => [
                'userIsUserEntity' => $user instanceof User,
                'userClass' => $user !== null ? $user::class : null,
                'passwordPresent' => $passwordRaw !== '',
                'passwordLength' => strlen($passwordRaw),
                'totpPresent' => $totpRaw !== '',
                'totpLength' => strlen($totpRaw),
                'requestKeys' => array_keys($request->request->all()),
                'action' => (string) $request->request->get('action', ''),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_THROW_ON_ERROR);
        @file_put_contents('/var/www/StuCvBuilder/.cursor/debug-08d76b.log', $debugPayload."\n", FILE_APPEND);
        // #endregion
        if (!$user instanceof User) {
            return 'dashboard.customization_backup.flash.reauth_required';
        }

        $password = $passwordRaw;
        $totpCode = $totpRaw;
        if ($password === '' || $totpCode === '') {
            // #region agent log
            $emptyFieldsPayload = json_encode([
                'sessionId' => '08d76b',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H1',
                'location' => 'CustomizationBackupController.php:validateReauthentication',
                'message' => 'Reauth rejected: empty password or totp in request',
                'data' => [
                    'passwordEmpty' => $password === '',
                    'totpEmpty' => $totpCode === '',
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_THROW_ON_ERROR);
            @file_put_contents('/var/www/StuCvBuilder/.cursor/debug-08d76b.log', $emptyFieldsPayload."\n", FILE_APPEND);
            // #endregion

            return 'dashboard.customization_backup.flash.reauth_required';
        }

        $validationResult = $this->adminReauthenticationService->validate($user, $password, $totpCode);
        // #region agent log
        $validationPayload = json_encode([
            'sessionId' => '08d76b',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H2-H4',
            'location' => 'CustomizationBackupController.php:validateReauthentication',
            'message' => 'Reauth service validation result',
            'data' => [
                'validationErrorKey' => $validationResult,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_THROW_ON_ERROR);
        @file_put_contents('/var/www/StuCvBuilder/.cursor/debug-08d76b.log', $validationPayload."\n", FILE_APPEND);
        // #endregion

        return $validationResult;
    }
}
