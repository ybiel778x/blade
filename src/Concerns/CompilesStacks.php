<?php

namespace CodeAlpha\Blade\Concerns;

trait CompilesStacks
{
    /**
     * Compile the stack statements into the content.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileStack($expression)
    {
        return "<?php echo \$this->yieldPushContent{$expression}; ?>";
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compilePush($expression)
    {
        return "<?php \$this->startPush{$expression}; ?>";
    }

    /**
     * Compile the end-push statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndpush()
    {
        return '<?php $this->stopPush(); ?>';
    }

    /**
     * Compile the prepend statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compilePrepend($expression)
    {
        return "<?php \$this->startPush{$expression}; ?>";
    }

    /**
     * Compile the end-prepend statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndprepend()
    {
        return '<?php $this->stopPrepend(); ?>';
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    public function compilePushOnce($expression)
    {
        $key = '$__pushonce__' . trim(substr($expression, 2, -2));
        return "<?php if(!isset($key)): $key=1; \$this->startPush{$expression}; ?>";
    }
}
