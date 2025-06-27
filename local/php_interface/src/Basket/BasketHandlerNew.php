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
use Bitrix\Sale\Basket;

use Sotbit\Multibasket\Models\MBasket;
use Sotbit\Multibasket\Entity\MBasketTable;
use Sotbit\Multibasket\Entity\MBasketItemTable;
use Sotbit\Multibasket\Entity\MBasketItemPropsTable;

class BasketHandlerNew
{
    public static function checkStockAndDistribute(Event $event)
    {
        if (!Loader::includeModule('catalog') || !Loader::includeModule('sotbit.multibasket')) {
            return new EventResult(EventResult::SUCCESS);
        }

        $basketItem = $event->getParameter('ENTITY');
        $productId  = (int)$basketItem->getField('PRODUCT_ID');
        $quantity   = (float)$basketItem->getQuantity();

        if ($productId <= 0 || $quantity <= 0) {
            return new EventResult(EventResult::SUCCESS);
        }

        $fuser       = new Fuser();
        $fuserId     = $fuser->getId();
        $siteId      = SITE_ID;
        $context     = Context::getCurrent();

        $mBasketTbl  = new MBasketTable();
        $mBasketItemTbl = new MBasketItemTable();
        $mBasketItemPropsTbl = new MBasketItemPropsTable();

        // Получаем текущую мультикорзину
        $mBasket = MBasket::getCurrent($fuser, $mBasketTbl, $mBasketItemTbl, $mBasketItemPropsTbl, $context);
        $currentStoreId = (int)$mBasket->getStoreId();

        // Проверяем остаток на складе текущей мультикорзины
        $stock = StoreProductTable::getRow([
            'filter' => ['PRODUCT_ID' => $productId, 'STORE_ID' => $currentStoreId],
            'select' => ['AMOUNT']
        ]);

        if ($stock && (float)$stock['AMOUNT'] >= $quantity) {
            return new EventResult(EventResult::SUCCESS);
        }

        // Получаем "Стандартный склад" из свойства
        $res = \CIBlockElement::GetList([], ['ID' => $productId], false, false, ['PROPERTY_DEFAULT_STORE']);
        $element = $res->Fetch();
        $defaultStoreId = isset($element['PROPERTY_DEFAULT_STORE_VALUE']) ? (int)$element['PROPERTY_DEFAULT_STORE_VALUE'] : 0;

        if ($defaultStoreId > 0) {
            $defaultStock = StoreProductTable::getRow([
                'filter' => ['PRODUCT_ID' => $productId, 'STORE_ID' => $defaultStoreId],
                'select' => ['AMOUNT']
            ]);

            if ($defaultStock && (float)$defaultStock['AMOUNT'] >= $quantity) {
                // Ищем или создаём мультикорзину с этим складом
                $basketRow = MBasketTable::getRow([
                    'filter' => ['FUSER_ID' => $fuserId, 'STORE_ID' => $defaultStoreId]
                ]);
                file_put_contents(Application::getDocumentRoot().'/test.txt', "basketRow: ".print_r($basketRow, true)."\n", FILE_APPEND);
                if (!$basketRow) {
                    $result = MBasketTable::add([
                        'FUSER_ID' => $fuserId,
                        'SITE_ID'  => $siteId,
                        'STORE_ID' => $defaultStoreId,
                        'NAME'     => 'Корзина склада ' . $defaultStoreId
                    ]);

                    if (!$result->isSuccess()) {
                        return new EventResult(
                            EventResult::ERROR,
                            new Error("Не удалось создать мультикорзину под склад №{$defaultStoreId}")
                        );
                    }

                    $basketId = $result->getId();
                } else {
                    $basketId = $basketRow['ID'];
                }
                file_put_contents(Application::getDocumentRoot().'/test.txt', "basketId: ".print_r($basketId, true)."\n", FILE_APPEND);
                // Загружаем целевую мультикорзину через getById
                $targetBasket = MBasket::getById(
                    $basketId,
                    $mBasketTbl,
                    $fuser,
                    $context
                  );
                file_put_contents(Application::getDocumentRoot().'/test.txt', "targetBasket: ".print_r($targetBasket, true)."\n", FILE_APPEND);
                // Создаём объект позиции мультикорзины и маппим из basketItem
                $mBasketItem = $mBasketItemTbl::createObject();
                $mBasketItem->mapingFromBasketItem($basketItem, $targetBasket->getId());
                // $targetBasket->setProps($basketItem, $mBasketItem);
                // Добавляем в коллекцию
                $targetBasket->getItems()->add($mBasketItem);

                // Сохраняем мультикорзину
                $targetBasket->save();
                file_put_contents(Application::getDocumentRoot().'/test.txt', "DefaultStock: ".print_r('test', true)."\n", FILE_APPEND);
                // Прерываем добавление в текущую корзину
                return new EventResult(
                    EventResult::ERROR,
                    new Error("Товар перенесен в мультикорзину склада №{$defaultStoreId}.")
                );
            }
        }

        // Если нет остатков нигде
        return new EventResult(
            EventResult::ERROR,
            new Error("Товар отсутствует на складе текущей мультикорзины и на стандартном складе.")
        );
    }
}
