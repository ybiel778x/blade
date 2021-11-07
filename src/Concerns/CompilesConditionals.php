<?php

namespace CodeAlpha\Blade\Concerns;

trait CompilesConditionals
{
    /**
     * Identifier for the first case in the switch statement.
     *
     * @var bool
     */
    protected $firstCaseInSwitch = true;

    /**
     * Compile the if-auth statements into valid PHP.
     *
     * @param  string|null  $guard
     * @return string
     */
    protected function compileAuth($guard = null)
    {
        $role = $this->stripParentheses($guard);

        if ($role == '') {
            return "<?php if(isset($this->currentUser)): ?>";
        }

        return "<?php if(isset(\$this->currentUser) && \$this->currentRole==$role): ?>";
    }


    /**
     * Compile the else-auth statements into valid PHP.
     *
     * @param  string|null  $guard
     * @return string
     */
    protected function compileElseAuth($guard = null)
    {
        $role = $this->stripParentheses($guard);
        if ($role == '') {
            return "<?php else: ?>";
        }

        return "<?php elseif(isset(\$this->currentUser) && \$this->currentRole==$role): ?>";
    }

    /**
     * Compile the end-auth statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndAuth()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the if-guest statements into valid PHP.
     *
     * @param  string|null  $guard
     * @return string
     */
    protected function compileGuest($guard = null)
    {
        if (is_null($guard)) {
            return "<?php if(!isset($this->currentUser)): ?>";
        }

        $role = $this->stripParentheses($guard);
        if ($role == '') {
            return "<?php if(!isset($this->currentUser)): ?>";
        }

        return "<?php if(!isset(\$this->currentUser) || \$this->currentRole!=$role): ?>";
    }

    /**
     * Compile the else-guest statements into valid PHP.
     *
     * @param  string|null  $guard
     * @return string
     */
    protected function compileElseGuest($guard = null)
    {
        $role = $this->stripParentheses($guard);
        if (is_null($guard)) {
            return "<?php else: ?>";
        }

        return "<?php elseif(!isset(\$this->currentUser) || \$this->currentRole!=$role): ?>";
    }

    /**
     * Compile the end-guest statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndGuest()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the has-section statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileHasSection($expression)
    {
        return "<?php if (! empty(trim(\$this->yieldContent{$expression}))): ?>";
    }

    /**
     * Compile the section-missing statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileSectionMissing($expression)
    {
        return "<?php if (empty(trim(\$this->yieldContent{$expression}))): ?>";
    }

    /**
     * Compile the if statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileIf($expression)
    {
        return "<?php if{$expression}: ?>";
    }

    /**
     * Compile the unless statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnless($expression)
    {
        return "<?php if (! {$expression}): ?>";
    }

    /**
     * Compile the else-if statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElseif($expression)
    {
        return "<?php elseif{$expression}: ?>";
    }

    /**
     * Compile the else statements into valid PHP.
     *
     * @return string
     */
    protected function compileElse()
    {
        return '<?php else: ?>';
    }

    /**
     * Compile the end-if statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndif()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-unless statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndunless()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the if-isset statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileIsset($expression)
    {
        return "<?php if(isset{$expression}): ?>";
    }

    /**
     * Compile the end-isset statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndIsset()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the switch statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileSwitch($expression)
    {
        $this->firstCaseInSwitch = true;

        return "<?php switch{$expression}:";
    }

    /**
     * Compile the case statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileCase($expression)
    {
        if ($this->firstCaseInSwitch) {
            $this->firstCaseInSwitch = false;

            return "case {$expression}: ?>";
        }

        return "<?php case {$expression}: ?>";
    }

    /**
     * Compile the default statements in switch case into valid PHP.
     *
     * @return string
     */
    protected function compileDefault()
    {
        return '<?php default: ?>';
    }

    /**
     * Compile the end switch statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndSwitch()
    {
        return '<?php endswitch; ?>';
    }
}
