<?php

namespace CodeAlpha\Blade\Concerns;

trait CompilesClasses
{
    /**
    * Compile the conditional class statement into valid PHP.
    *
    * @param  string  $expression
    * @return string
    */
    protected function compileClass($expression)
    {
        $expression = is_null($expression) ? '([])' : $expression;

        return "class=\"<?= self::toCssClasses{$expression} ?>\"";
    }

    /**
    * Conditionally compile classes from an array into a CSS class list.
    *
    * @param  array  $array
    * @return string
    */
    public static function toCssClasses($array)
    {
        $classList = static::wrap($array);

        $classes = [];

        foreach ($classList as $class => $constraint) {
            if (is_numeric($class)) {
                $classes[] = $constraint;
            } elseif ($constraint) {
                $classes[] = $class;
            }
        }

        return implode(' ', $classes);
    }


    /**
    * If the given value is not an array and not null, wrap it in one.
    *
    * @param  mixed  $value
    * @return array
    */
    public static function wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
