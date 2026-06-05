<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\StockChangedMessage;
use App\Service\ShoppingListService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StockChangedHandler
{
    public function __construct(
        private ShoppingListService $shoppingListService
    ) {
    }

    public function __invoke(StockChangedMessage $message): void
    {
        $this->shoppingListService->handleStockChange($message->productId);
    }
}
