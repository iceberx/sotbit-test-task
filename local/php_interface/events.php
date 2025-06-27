<?php
use \Bitrix\Main\EventManager;
use App\Basket\BasketHandler;
use App\Basket\BasketHandlerNew;

$eventManager = EventManager::getInstance();
// $eventManager->addEventHandler('main', 'OnProlog', [BasketHandler::class, "customCheckStock"]);
// $eventManager->addEventHandler('sale', 'OnSaleBasketItemRefreshData', [BasketHandler::class, "customCheckStock"]);
$eventManager->addEventHandler('sale', 'OnSaleBasketItemRefreshData', [BasketHandlerNew::class, "checkStockAndDistribute"]);
// $eventManager->addEventHandler('sale', 'OnSaleBasketSaved', [BasketHandlerNew::class, "checkStockAndDistribute"]);