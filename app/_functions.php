<?php

use App\App;

/**
 * @param array|string $key
 * @param $value [optional]
 * @return string|array|bool|mixed|null
 */
function config(array|string $key = '', $value = null): mixed
{
    $app = App::factory();

    if (!is_null($value) && !empty($key)) {
        $app->setConfig($key, $value);
        return true;
    }

    // Для инициализации мы передаем репозиторий, но не конфиг.
    if ($key instanceof \Arris\Core\Dot) {
        $app->setConfig('', $value);
    }

    if (is_array($key)) {
        foreach ($key as $k => $v) {
            $app->setConfig($k, $v);
        }
        return true;
    }

    if (empty($key)) {
        return $app->getConfig();
    }

    return $app->getConfig($key);
}

/**
 * @todo: copy to Core.App
 * @param string $key
 * @param string $default
 * @return mixed
 */
function input(string $key = '', string $default = ''):mixed
{
    if (property_exists(App::class, 'dot_input') && App::$dot_input) {
        if (empty($key)) {
            return App::$dot_input->all();
        }

        return App::$dot_input->get($key, $default);
    } else {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($key)) {
            return $input;
        }

        return (new \Arris\Core\Dot($input))->get($key, $default);
    }
}

function request(string $key = '', string $default = ''):string|array
{
    if (empty($key)) {
        return $_REQUEST;
    }

    if (array_key_exists($key, $_REQUEST)) {
        return $_REQUEST[$key];
    } else {
        return $default;
    }
}

function pluralForm($number, $forms, string $glue = '|'):string
{
    if (@empty($forms)) {
        return $number;
    }

    if (\is_string($forms)) {
        $forms = \explode($forms, $glue);
    } elseif (!\is_array($forms)) {
        return $number;
    }

    switch (\count($forms)) {
        case 1: {
            $forms[] = \end($forms);
            $forms[] = \end($forms);
            break;
        }
        case 2: {
            $forms[] = \end($forms);
        }
    }

    return
        ($number % 10 == 1 && $number % 100 != 11)
            ? $forms[0]
            : (
        ($number % 10 >= 2 && $number % 10 <= 4 && ($number % 100 < 10 || $number % 100 >= 20))
            ? $forms[1]
            : $forms[2]
        );
}