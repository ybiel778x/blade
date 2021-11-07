<?php

define('base_url', 'http://localhost:1111');

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('method_field')) {
    /**
     * Generate a form field to spoof the HTTP verb used by forms.
     *
     * @param  string  $method
     * @return string
     */
    function method_field($method)
    {
        return '<input type="hidden" name="_method" value="' . $method . '">';
    }
}

if (!function_exists('dd')) {
    /**
     * Return dump from var.
     *
     * @param  mixed  $vars
     * @return mixed
     */
    function dd($vars)
    {
        echo '<pre>';
        var_dump($vars);
        echo '</pre>';
        exit(1);
    }
}

if (! function_exists('csrf_field')) {
    /**
     * Generate a CSRF token form field.
     *
     * @return string
     */
    function csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (! function_exists('csrf_token')) {
    /**
     * Get the CSRF token value.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    function csrf_token()
    {
        if (session_status() != PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['token'])) {
            return $_SESSION['token'];
        }

        $_SESSION['token'] = bin2hex(random_bytes(35));

        return $_SESSION['token'];

        //throw new RuntimeException('Application session store not set.');
    }
}

if (! function_exists('e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param $value
     * @param  bool  $doubleEncode
     * @return string
     */
    function e($value, $doubleEncode = true)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8', $doubleEncode);
    }
}

if (! function_exists('asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function asset($path)
    {
        return base_url . '/'. $path;
    }
}