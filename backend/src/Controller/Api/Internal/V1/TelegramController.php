<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Response\Telegram\TelegramStatusResponse;
use App\Response\Telegram\TelegramTestResultResponse;
use App\Service\Telegram\TelegramSender;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Telegram')]
final class TelegramController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'TELEGRAM_DSN')]
        private readonly string $telegramDsn,
        #[Autowire(env: 'TELEGRAM_DAILY_SUMMARY_TIME')]
        private readonly string $dailySummaryTime,
        // Lazy: the Telegram chatter transport is built from TELEGRAM_DSN only when a
        // message is actually sent. Without this, merely constructing the controller
        // (e.g. for GET /telegram/status) instantiates the transport and 500s when the
        // DSN is the unconfigured placeholder — exactly the case status() must report.
        #[Autowire(lazy: true)]
        private readonly TelegramSender $telegramSender
    ) {
    }

    #[Route('/telegram/status', name: 'api_telegram_status', methods: ['GET'])]
    #[OA\Get(
        summary: 'Telegram status',
        description: 'Whether the bot is configured and the daily summary time. No secrets returned.'
    )]
    #[OA\Response(response: 200, description: 'Status', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TelegramStatusResponse::class))
    ]))]
    public function status(): JsonResponse
    {
        return $this->json([
            'data' => new TelegramStatusResponse($this->isConfigured(), $this->dailySummaryTime)
        ]);
    }

    #[Route('/telegram/test', name: 'api_telegram_test', methods: ['POST'])]
    #[OA\Post(
        summary: 'Send Telegram test',
        description: 'Sends a real test message synchronously to the configured chat.'
    )]
    #[OA\Response(response: 200, description: 'Delivery result', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TelegramTestResultResponse::class))
    ]))]
    public function test(): JsonResponse
    {
        if (!$this->isConfigured()) {
            return $this->json(['data' => new TelegramTestResultResponse(ok: false, error: 'not_configured')]);
        }

        try {
            $this->telegramSender->send('🔔 Hestia — тестовое сообщение / test message');

            return $this->json(['data' => new TelegramTestResultResponse(ok: true)]);
        } catch (\Throwable $throwable) {
            return $this->json(['data' => new TelegramTestResultResponse(ok: false, error: $throwable->getMessage())]);
        }
    }

    private function isConfigured(): bool
    {
        return $this->telegramDsn !== '' && !str_starts_with($this->telegramDsn, 'telegram://TOKEN@');
    }
}
