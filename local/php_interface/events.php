<?php
use \Bitrix\Main\EventManager;
use App\Basket\BasketHandler;

$eventManager = EventManager::getInstance();
// $eventManager->addEventHandler('main', 'OnProlog', [BasketHandler::class, "customCheckStock"]);
$eventManager->addEventHandler('sale', 'OnSaleBasketItemRefreshData', [BasketHandler::class, "customCheckStock"]);