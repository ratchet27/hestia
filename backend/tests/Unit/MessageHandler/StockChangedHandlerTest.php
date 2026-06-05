<?php

declare(strict_types = 1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\StockChangedMessage;
use App\MessageHandler\StockChangedHandler;
use App\Service\ShoppingListService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class StockChangedHandlerTest extends TestCase
{
    public function testReconciliationFailureIsLoggedAndSwallowed(): void
    {
        $service = $this->createStub(ShoppingListService::class);
        $service->method('handleStockChange')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning');

        $handler = new StockChangedHandler($service, $logger);

        // Must not throw — the committed stock op stays successful.
        $handler(new StockChangedMessage(Uuid::v4()));
    }

    public function testReconciliationIsDelegatedWithProductId(): void
    {
        $productId = Uuid::v4();
        $service = $this->createMock(ShoppingListService::class);
        $service->expects(static::once())->method('handleStockChange')->with($productId);

        $handler = new StockChangedHandler($service, $this->createStub(LoggerInterface::class));
        $handler(new StockChangedMessage($productId));
    }
}
