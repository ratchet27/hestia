<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\StockChangedMessage;
use App\Service\ShoppingListService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StockChangedHandler
{
    public function __construct(
        private ShoppingListService $shoppingListService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(StockChangedMessage $message): void
    {
        try {
            $this->shoppingListService->handleStockChange($message->productId);
        } catch (\Throwable $throwable) {
            // Stock change is already committed; a reconciliation hiccup must not fail
            // the user's operation. It self-corrects on the next stock change.
            $this->logger->error('Shopping-list reconciliation failed', [
                'productId' => (string) $message->productId,
                'exception' => $throwable
            ]);
        }
    }
}
