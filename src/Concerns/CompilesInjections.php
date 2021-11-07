<?php

namespace CodeAlpha\Blade\Concerns;

use InvalidArgumentException;

trait CompilesInjections
{
    /**
     * Compile the inject statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileInject($expression)
    {
        $ex = $this->stripParentheses($expression);
        $p0 = strpos($ex, ',');
        if ($p0 == false) {
            $variable = $this->stripQuotes($ex);
            $namespace = '';
        } else {
            $variable = $this->stripQuotes(substr($ex, 0, $p0));
            $namespace = $this->stripQuotes(substr($ex, $p0 + 1));
        }
        return "<?php \${$variable} = \$this->injectClass('{$namespace}', '{$variable}'); ?>";
    }

    /**
     * Remove first and end quote from a quoted string of text
     *
     * @param mixed $text
     * @return null|string|string[]
     */
    public function stripQuotes($text)
    {
        if (!$text || strlen($text) < 2) {
            return $text;
        }
        $text = trim($text);
        $p0 = $text[0];
        $p1 = substr($text, -1);
        if ($p0 === $p1 && ($p0 === '"' || $p0 === "'")) {
            return substr($text, 1, -1);
        }
        return $text;
    }

     /**
     * Resolve a given class using the injectResolver callable.
     *
     * @param string      $className
     * @param string|null $variableName
     * @return mixed
     */
    protected function injectClass($className, $variableName = null)
    {
        if (isset($this->injectResolver)) {
            return call_user_func($this->injectResolver, $className, $variableName);
        }

        $fullClassName = $className . "\\" . $variableName;
        if (!class_exists($fullClassName)) {
            throw new InvalidArgumentException("Class '{$fullClassName}' not found.");
        }
        return new $fullClassName();
    }
}
