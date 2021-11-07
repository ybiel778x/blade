<?php

namespace CodeAlpha\Blade\Concerns;

trait CompilesComponents
{
    /**
     * The component name hash stack.
     *
     * @var array
     */
    protected static $componentHashStack = [];

    /**
     * Compile the component statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileComponent($expression)
    {
        [$component, $alias, $data] = strpos($expression, ',') !== false
                    ? array_map('trim', explode(',', trim($expression, '()'), 3)) + ['', '', '']
                    : [trim($expression, '()'), '', ''];

        $component = trim($component, '\'"');

        $hash = static::newComponentHash($component);

        if (static::contains($component, ['::class', '\\'])) {
            return static::compileClassComponentOpening($component, $alias, $data, $hash);
        }

        return "<?php \$this->startComponent{$expression}; ?>";
    }

    /**
     * Get a new component hash for a component name.
     *
     * @param  string  $component
     * @return string
     */
    public static function newComponentHash(string $component)
    {
        static::$componentHashStack[] = $hash = sha1($component);

        return $hash;
    }

    /**
     * Compile a class component opening.
     *
     * @param  string  $component
     * @param  string  $alias
     * @param  string  $data
     * @param  string  $hash
     * @return string
     */
    public static function compileClassComponentOpening(string $component, string $alias, string $data, string $hash)
    {
        return implode("\n", [
            '<?php if (isset($component)) { $__componentOriginal' . $hash . ' = $component; } ?>',
            '<?php $component = $this->getContainer()->make(' . static::finish($component, '::class') . ', ' . ($data ?: '[]') . '); ?>',
            '<?php $component->withName(' . $alias . '); ?>',
            '<?php if ($component->shouldRender()): ?>',
            '<?php $this->startComponent($component->resolveView(), $component->data()); ?>',
        ]);
    }

    /**
     * Compile the end-component statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndComponent()
    {
        $hash = array_pop(static::$componentHashStack);

        return implode("\n", [
            '<?php if (isset($__componentOriginal' . $hash . ')): ?>',
            '<?php $component = $__componentOriginal' . $hash . '; ?>',
            '<?php unset($__componentOriginal' . $hash . '); ?>',
            '<?php endif; ?>',
            '<?php echo $this->renderComponent(); ?>',
        ]);
    }

    /**
     * Compile the end-component statements into valid PHP.
     *
     * @return string
     */
    public function compileEndComponentClass()
    {
        return $this->compileEndComponent() . "\n" . implode("\n", [
            '<?php endif; ?>',
        ]);
    }

    /**
     * Compile the component-first statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileComponentFirst($expression)
    {
        return "<?php \$this->startComponentFirst{$expression}; ?>";
    }

    /**
     * Compile the end-component-first statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndComponentFirst()
    {
        return $this->compileEndComponent();
    }

    /**
     * Compile the slot statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileSlot($expression)
    {
        return "<?php \$this->slot$expression; ?>";
    }

    /**
     * Compile the end-slot statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndSlot()
    {
        return '<?php $this->endSlot(); ?>';
    }
}
