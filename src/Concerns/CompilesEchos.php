<?php

namespace CodeAlpha\Blade\Concerns;

use Closure;

trait CompilesEchos
{
    /**
     * Custom rendering callbacks for stringable objects.
     *
     * @var array
     */
    protected $echoHandlers = [];

    /**
     * Add a handler to be executed before echoing a given class.
     *
     * @param  string|callable  $class
     * @param  callable|null  $handler
     * @return void
     */
    public function stringable($class, $handler = null)
    {
        if ($class instanceof Closure) {
            [$class, $handler] = [$this->firstClosureParameterType($class), $class];
        }

        $this->echoHandlers[$class] = $handler;
    }

    /**
     * Compile Blade echos into valid PHP.
     *
     * @param  string  $value
     * @return string
     */
    public function compileEchos($value)
    {
        foreach ($this->getEchoMethods() as $method) {
            $value = $this->$method($value);
        }

        return $value;
    }

    /**
     * Get the echo methods in the proper order for compilation.
     *
     * @return array
     */
    protected function getEchoMethods()
    {
        return [
            'compileRawEchos',
            'compileEscapedEchos',
            'compileRegularEchos',
        ];
    }

    /**
     * Compile the "raw" echo statements.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileRawEchos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->rawTags[0], $this->rawTags[1]);

        $callback = function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

            return $matches[1]
                ? substr($matches[0], 1)
                : "<?php echo {$this->wrapInEchoHandler($matches[2])}; ?>{$whitespace}";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the "regular" echo statements.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileRegularEchos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->contentTags[0], $this->contentTags[1]);

        $callback = function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

            $wrapped = sprintf($this->echoFormat, $this->wrapInEchoHandler($matches[2]));

            return $matches[1] ? substr($matches[0], 1) : "<?php echo {$wrapped}; ?>{$whitespace}";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compile the escaped echo statements.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileEscapedEchos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->escapedTags[0], $this->escapedTags[1]);

        $callback = function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

            return $matches[1]
                ? $matches[0]
                : "<?php echo e({$this->wrapInEchoHandler($matches[2])}); ?>{$whitespace}";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Add an instance of the blade echo handler to the start of the compiled string.
     *
     * @param  string  $result
     * @return string
     */
    protected function addBladeCompilerVariable($result)
    {
        return "<?php \$__bladeCompiler = app('blade.compiler'); ?>" . $result;
    }

    /**
     * Wrap the echoable value in an echo handler if applicable.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapInEchoHandler($value)
    {
        $result = preg_replace('/^(?=\$)(.+?)\s+or\s+(.+?)$/s', 'isset($1) ? $1 : $2', $value);
        if (!$this->pipeEnable) {
            return $this->fixNamespaceClass($result);
        }

        return $this->pipeDream($this->fixNamespaceClass($result));
    }

    /**
     * It converts a string separated by pipes | into an filtered expression.<br>
     * If the method exists (as directive), then it is used<br>
     * If the method exists (in this class) then it is used<br>
     * Otherwise, it uses a global function.<br>
     * If you want to escape the "|", then you could use "/|"<br>
     *
     * @param string $result
     * @return string
     * @\CodeAlpha\Blade\Blade::$pipeEnable
     */
    protected function pipeDream($result)
    {
        $array = preg_split('~\\\\.(*SKIP)(*FAIL)|\|~s', $result);
        $c = count($array) - 1; // base zero.
        if ($c === 0) {
            return $result;
        }

        $prev = '';
        for ($i = $c; $i >= 1; $i--) {
            $r = @explode(':', $array[$i], 2);
            $fnName = trim($r[0]);
            $fnNameF = $fnName[0]; // first character
            if ($fnNameF === '"' || $fnNameF === '\'' || $fnNameF === '$' || is_numeric($fnNameF)) {
                $fnName = '!isset(' . $array[0] . ') ? ' . $fnName . ' : ';
            } elseif (isset($this->customDirectives[$fnName])) {
                $fnName = '$this->customDirectives[\'' . $fnName . '\']';
            } elseif (method_exists($this, $fnName)) {
                $fnName = '$this->' . $fnName;
            }
            if ($i === 1) {
                $prev = $fnName . '(' . $array[0];
                if (count($r) === 2) {
                    $prev .= ',' . $r[1];
                }
                $prev .= ')';
            } else {
                $prev = $fnName . '(' . $prev;
                if (count($r) === 2) {
                    if ($i === 2) {
                        $prev .= ',';
                    }
                    $prev .= $r[1] . ')';
                }
            }
        }
        return $prev;
    }
}
