<?php

namespace App\Services\Payment;

/**
 * Payme Merchant API errors carry `message` as an object of localized texts that Payme shows to the payer
 * (developer.help.paycom.uz/protokol-merchant-api/format-otveta: "message — Object, Локализованный текст сообщения
 * об ошибке"). The webhooks keep one English name per error; this gives its {uz, ru, en} texts.
 * SetFiscalData errors are not shown to the payer and keep the plain parameter text its page asks for.
 */
final class PaymeRpcMessage
{
    /** @var array<string, array{0: string, 1: string}> English => [uz, ru] */
    private const TEXTS = [
        'Insufficient privileges' => ["Ruxsat yo'q", 'Недостаточно привилегий'],
        'Method not found' => ['Metod topilmadi', 'Метод не найден'],
        'Parse error' => ["So'rovni o'qib bo'lmadi (JSON xato)", 'Ошибка разбора JSON'],
        'System error' => ["Tizimda xatolik. Birozdan keyin qayta urinib ko'ring", 'Системная ошибка'],
        'Transaction id is required' => ["Tranzaksiya raqami ko'rsatilmagan", 'Не указан идентификатор транзакции'],
        'Invalid statement period' => ["Davr noto'g'ri ko'rsatilgan", 'Неверный период выписки'],
        'Order not found' => ['Buyurtma topilmadi', 'Заказ не найден'],
        'Order is not payable via Payme' => ["Bu buyurtmani Payme orqali to'lab bo'lmaydi", 'Этот заказ нельзя оплатить через Payme'],
        'Order already paid' => ["Buyurtma allaqachon to'langan", 'Заказ уже оплачен'],
        'Order is not payable' => ["Buyurtmani to'lab bo'lmaydi: u bekor qilingan yoki o'zgargan", 'Заказ нельзя оплатить: он отменён или изменён'],
        'Topup already paid' => ["Balansni to'ldirish allaqachon to'langan", 'Пополнение уже оплачено'],
        'Topup is not payable' => ["Balansni to'ldirish so'rovini to'lab bo'lmaydi", 'Пополнение нельзя оплатить'],
        'Order is awaiting payment by another transaction' => ["Buyurtma boshqa to'lov orqali to'lanmoqda", 'Заказ уже ожидает оплаты другой транзакцией'],
        'Transaction belongs to another order' => ['Tranzaksiya boshqa buyurtmaga tegishli', 'Транзакция относится к другому заказу'],
        'Incorrect amount' => ["Noto'g'ri summa", 'Неверная сумма'],
        'Transaction not found' => ['Tranzaksiya topilmadi', 'Транзакция не найдена'],
        'Unable to perform operation' => ["Amalni bajarib bo'lmaydi", 'Невозможно выполнить операцию'],
        'Invalid transaction state' => ["Tranzaksiya holatida bu amalni bajarib bo'lmaydi", 'Невозможно выполнить операцию в текущем состоянии транзакции'],
        'Transaction timed out' => ["Tranzaksiya muddati o'tdi", 'Время ожидания транзакции истекло'],
        'Unable to cancel transaction' => ["Tranzaksiyani bekor qilib bo'lmaydi: buyurtma bajarilgan", 'Невозможно отменить транзакцию: заказ выполнен'],
    ];

    /**
     * @return array{uz: string, ru: string, en: string}
     */
    public static function of(string $english): array
    {
        [$uz, $ru] = self::TEXTS[$english] ?? [$english, $english];

        return ['uz' => $uz, 'ru' => $ru, 'en' => $english];
    }
}
