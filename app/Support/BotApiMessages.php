<?php

namespace App\Support;

class BotApiMessages
{
    public static function userNotFound(): string
    {
        return "Я не нашла тебя в базе 😢\n\nИспользуй /register и попробуй ещё раз.";
    }

    public static function accessRequiresPayment(): string
    {
        return 'VPN не оплачен, необходимо пополнить баланс. Команда /balance';
    }

    public static function configNotFound(): string
    {
        return "Я не смогла найти такой конфиг ☹️\n\nСообщи об этом @soussangler";
    }

    public static function unexpectedError(): string
    {
        return "Что-то пошло не так 🤯\n\nПопробуй ещё раз позже или сообщи об этом @soussangler";
    }
}
