<?php
namespace App\Basket;

use Bitrix\Main\Loader;
use Bitrix\Catalog\StoreProductTable;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Error;
use Bitrix\Iblock\ElementTable;
use Bitrix\Sale\Fuser;

use Sotbit\Multibasket\Models\MBasket;
use Sotbit\Multibasket\Entity\MBasketTable;
use Sotbit\Multibasket\Entity\MBasketItemTable;
use Sotbit\Multibasket\Entity\MBasketItemPropsTable;

class BasketHandler{
    public static function customCheckStock(Event $event)
    {
        if (!Loader::includeModule('catalog') || !Loader::includeModule('sotbit.multibasket')) {
            return new EventResult(EventResult::SUCCESS);
        }

        $basketItem = $event->getParameter('ENTITY');

        if (!$basketItem->getField('PRODUCT_ID')) {
            return;
        }
        
        $fuser = new Fuser();
        $mBasketTable = new MBasketTable();
        $mBasketItemTable = new MBasketItemTable();
        $mBasketItemPropsTable = new MBasketItemPropsTable();
        $context = Context::getCurrent();
        $mBasket = MBasket::getCurrent(
            $fuser,
            $mBasketTable,
            $mBasketItemTable,
            $mBasketItemPropsTable,
            $context
        );
    
        $currentStoreId = (int)$mBasket->getStoreId();
        
        $productId = (int)$basketItem->getField('PRODUCT_ID');

        if (!$productId) {
            return;
        }
        file_put_contents(Application::getDocumentRoot().'/test.txt', print_r('11', true)."\n", FILE_APPEND);
        // Получаем значение свойства "Стандартный склад"
        $res = \CIBlockElement::GetList([], ['ID' => $productId], false, false, ['PROPERTY_DEFAULT_STORE']);
        $item = $res->Fetch();
        $defaultStoreId = isset($item['PROPERTY_DEFAULT_STORE_VALUE']) ? (int)$item['PROPERTY_DEFAULT_STORE_VALUE'] : 0;

        $validBasketId = null;

        $baskets = MBasketTable::getList([
            'filter' => [
                'FUSER_ID' => $fuser->getId(),
            ]
        ])->fetchAll();

        // Проверяем остатки по мультикорзинам
        foreach ($baskets as $basket) {
            $storeId = (int)$basket['STORE_ID'];

            $stock = StoreProductTable::getList([
                'filter' => [
                    '=PRODUCT_ID' => $productId,
                    '=STORE_ID'   => $storeId
                ],
                'select' => ['AMOUNT']
            ])->fetch();
            file_put_contents(Application::getDocumentRoot().'/test.txt', "Stock: ".print_r($stock, true)."\n", FILE_APPEND);
            file_put_contents(Application::getDocumentRoot().'/test.txt', "Stock: ".print_r($basket, true)."\n", FILE_APPEND);
            if ($stock && (float)$stock['AMOUNT'] > 0) {
                $validBasketId = $basket['ID'];
                break;
            }
        }
        file_put_contents(Application::getDocumentRoot().'/test.txt', "ValidBasketId: ".print_r($validBasketId, true)."\n", FILE_APPEND);
        // Если не нашли — проверяем стандартный склад
        if (!$validBasketId && $defaultStoreId > 0) {
            $defaultStock = StoreProductTable::getList([
                'filter' => [
                    '=PRODUCT_ID' => $productId,
                    '=STORE_ID'   => $defaultStoreId
                ],
                'select' => ['AMOUNT']
            ])->fetch();
            file_put_contents(Application::getDocumentRoot().'/test.txt', "DefaultStock: ".print_r($defaultStock, true)."\n", FILE_APPEND);
            if ($defaultStock && (float)$defaultStock['AMOUNT'] > 0) {
                // Проверяем есть ли мультикорзина с этим складом
                $basket = MBasketTable::getList([
                    'filter' => [
                        'FUSER_ID' => $fuser->getId(),
                        'STORE_ID' => $defaultStoreId
                    ],
                    'limit' => 1
                ])->fetch();
                file_put_contents(Application::getDocumentRoot().'/test.txt', print_r($basket, true)."\n", FILE_APPEND);
                if (!$basket) {
                    // Создаём мультикорзину под стандартный склад
                    $result = MBasketTable::add([
                        'FUSER_ID' => $fuser->getId(),
                        'STORE_ID' => $defaultStoreId,
                        'NAME'     => 'Корзина для склада ' . $defaultStoreId
                    ]);

                    if ($result->isSuccess()) {
                        $validBasketId = $result->getId();
                    }
                } else {
                    $validBasketId = $basket['ID'];
                }
            }
        }
        file_put_contents(Application::getDocumentRoot().'/test.txt', "ValidBasketId: ".print_r($validBasketId, true)."\n", FILE_APPEND);
        if ($validBasketId) {
            // Добавляем товар в валидную мультикорзину
            MBasketItemTable::add([
                'BASKET_ID'  => $validBasketId,
                'PRODUCT_ID' => $productId,
                'QUANTITY'   => (float)$basketItem->getQuantity(),
                'PRICE'      => (float)$basketItem->getPrice(),
                'CURRENCY'   => $basketItem->getCurrency()
            ]);

            return new EventResult(EventResult::SUCCESS);
        }

        // Ни на одном складе товара нет — ошибка
        return new EventResult(
            EventResult::ERROR,
            new Error("Товар отсутствует на складе текущей мультикорзины и на стандартном складе.")
        );
    }
}