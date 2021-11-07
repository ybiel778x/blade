<?php

namespace CodeAlpha\Blade\Concerns;

trait CompilesLayouts
{
    /**
     * The name of the last section that was started.
     *
     * @var string
     */
    protected $lastSection;

    protected $uidCounter = 0;

    /**
     * Compile the extends statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileExtends($expression)
    {
        $expression = $this->stripParentheses($expression);

        $this->uidCounter++;

        $echo = "<?php if (isset(\$_shouldextend[{$this->uidCounter}])) { echo \$this->make($expression); } ?>";

        $this->footer[] = $echo;

        return "<?php \$_shouldextend[{$this->uidCounter}]=1; ?>";
    }

    /**
     * Compile the extends-first statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileExtendsFirst($expression)
    {
        $expression = $this->stripParentheses($expression);

        $this->uidCounter++;

        $echo = "<?php if (isset(\$_shouldextend[{$this->uidCounter}])) { echo \$this->make($expression); } ?>";

        $this->footer[] = $echo;

        return "<?php \$_shouldextend[{$this->uidCounter}]=1; ?>";
    }

    /**
     * Compile the section statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileSection($expression)
    {
        $this->lastSection = trim($expression, "()'\" ");

        return "<?php \$this->startSection{$expression}; ?>";
    }

    /**
     * Replace the @parent directive to a placeholder.
     *
     * @return string
     */
    protected function compileParent()
    {
        return static::parentPlaceholder($this->lastSection ?: '');
    }

    /**
     * Compile the yield statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileYield($expression)
    {
        return "<?php echo \$this->yieldContent{$expression}; ?>";
    }

    /**
     * Compile the show statements into valid PHP.
     *
     * @return string
     */
    protected function compileShow()
    {
        return '<?php echo $this->yieldSection(); ?>';
    }

    /**
     * Compile the append statements into valid PHP.
     *
     * @return string
     */
    protected function compileAppend()
    {
        return '<?php $this->appendSection(); ?>';
    }

    /**
     * Compile the overwrite statements into valid PHP.
     *
     * @return string
     */
    protected function compileOverwrite()
    {
        return '<?php $this->stopSection(true); ?>';
    }

    /**
     * Compile the stop statements into valid PHP.
     *
     * @return string
     */
    protected function compileStop()
    {
        return '<?php $this->stopSection(); ?>';
    }

    /**
     * Compile the end-section statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndsection()
    {
        return '<?php $this->stopSection(); ?>';
    }
}
