<?php

namespace CodeAlpha\Blade;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;

class BladeCompiler extends Compiler
{
    use Concerns\CompilesHelpers;
    use Concerns\CompilesClasses;
    use Concerns\CompilesEchos;
    use Concerns\CompilesComponents;
    use Concerns\CompilesComments;
    use Concerns\CompilesJson;
    use Concerns\CompilesRawPhp;
    use Concerns\CompilesLoops;
    use Concerns\CompilesIncludes;
    use Concerns\CompilesStacks;
    use Concerns\CompilesConditionals;
    use Concerns\CompilesInjections;
    use Concerns\CompilesLayouts;

    use Manages\ManagesStacks;
    use Manages\ManagesComponents;
    use Manages\ManagesLayouts;
    use Manages\ManagesLoops;

    public $currentUser;
    public $currentRole;
    public $currentPermission = [];
    public $authCallBack;
    public $authAnyCallBack;

    public $missingLog = '';
    public $pipeEnable = false;
    public $includeScope = false;
    public $compileCallbacks = [];

    protected $fileName;

    protected $variables = [];

     /**
     * All of the registered extensions.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * All custom "directive" handlers.
     *
     * @var array
     */
    protected $customDirectives = [];

    /**
     * All custom "condition" handlers.
     *
     * @var array
     */
    protected $conditions = [];

    /**
     * All of the registered precompilers.
     *
     * @var array
     */
    protected $precompilers = [];

    /**
     * The file currently being compiled.
     *
     * @var string
     */
    protected $path;

    /**
     * All of the available compiler functions.
     *
     * @var string[]
     */
    protected $compilers = [
        'Comments',
        'Extensions',
        'Statements',
        'Echos',
    ];

    /**
     * Array of opening and closing tags for raw echos.
     *
     * @var string[]
     */
    protected $rawTags = ['{!!', '!!}'];

    /**
     * Array of opening and closing tags for regular echos.
     *
     * @var string[]
     */
    protected $contentTags = ['{{', '}}'];

    /**
     * Array of opening and closing tags for escaped echos.
     *
     * @var string[]
     */
    protected $escapedTags = ['{{{', '}}}'];

    /**
     * The "regular" / legacy echo string format.
     *
     * @var string
     */
    protected $echoFormat = 'e(%s)';


    /**
     * The array of class component aliases and their class names.
     *
     * @var array
     */
    protected $classComponentAliases = [];

    /**
     * The array of class component namespaces to autoload from.
     *
     * @var array
     */
    protected $classComponentNamespaces = [];

    /**
     * Indicates if component tags should be compiled.
     *
     * @var bool
     */
    protected $compilesComponentTags = true;

    /**
     * Data that should be available to all templates.
     *
     * @var array
     */
    protected $shared = [];

    /**
     * The view composer events.
     *
     * @var array
     */
    protected $composers = [];

    /**
     * The number of active rendering operations.
     *
     * @var int
     */
    protected $renderCount = 0;

    protected $injectResolver;

    protected $baseUrl = '.';
    protected $baseDomain;

    protected $currentUrl;
    protected $relativePath = '';

     /**
     * Array to temporary store the raw blocks found in the template.
     *
     * @var array
     */
    protected $rawBlocks = [];

    /**
     * Array of footer lines to be added to the template.
     *
     * @var array
     */
    protected $footer = [];

    public function testeeeeeeee($viewPath, $cachePath)
    {

        $this->authCallBack = function ($action = null, $subject = null) {
            return in_array($action, $this->currentPermission, true);
        };

        $this->authAnyCallBack = function ($array = []) {
            foreach ($array as $permission) {
                if (in_array($permission, $this->currentPermission, true)) {
                    return true;
                }
            }
            return false;
        };
    }

    /**
     * Show an error in the web.
     *
     * @param string $id          Title of the error
     * @param string $text        Message of the error
     * @param bool   $critic      if true then the compilation is ended, otherwise it continues
     * @param bool   $alwaysThrow if true then it always throws a runtime exception.
     * @return string
     * @throws RuntimeException
     */
    public function showError($id, $text, $critic = false, $alwaysThrow = false)
    {
        ob_get_clean();
        throw new RuntimeException("Error: [$id] $text");
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string   $name
     * @param callable $handler
     * @return void
     */
    public function directive($name, callable $handler)
    {
        $this->customDirectives[$name] = $handler;
    }

    /**
     * Strip the parentheses from the given expression.
     *
     * @param string $expression
     * @return string
     */
    public function stripParentheses($expression)
    {
        if (static::startsWith($expression, '(')) {
            $expression = substr($expression, 1, -1);
        }
        return $expression;
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authentication. Sets with a user,role and permission
     *
     * @param string $user
     * @param null   $role
     * @param array  $permission
     */
    public function setAuth($user = '', $role = null, $permission = [])
    {
        $this->currentUser = $user;
        $this->currentRole = $role;
        $this->currentPermission = $permission;
    }

    /**
     * Compile the given Blade template contents.
     *
     * @param string $value
     * @return string
     */
    public function compileString($value)
    {
        [$this->footer, $result] = [[], ''];

        // First we will compile the Blade component tags. This is a precompile style
        // step which compiles the component Blade tags into @component directives
        // that may be used by Blade. Then we should call any other precompilers.
        $value = $this->compileComments($this->storeUncompiledBlocks($value));


        foreach ($this->precompilers as $precompiler) {
            $value = call_user_func($precompiler, $value);
        }

        // Here we will loop through all of the tokens returned by the Zend lexer and
        // parse each one into the corresponding valid PHP. We will then have this
        // template as the correctly rendered PHP that can be rendered natively.
        foreach (token_get_all($value) as $token) {
            $result .= is_array($token) ? $this->parseToken($token) : $token;
        }

        if (! empty($this->rawBlocks)) {
            $result = $this->restoreRawContent($result);
        }

        // If there are any footer lines that need to get added to a template we will
        // add them here at the end of the template. This gets used mainly for the
        // template inheritance via the extends keyword that should be appended.
        if (count($this->footer) > 0) {
            $result = $this->addFooters($result);
        }

        if (! empty($this->echoHandlers)) {
            $result = $this->addBladeCompilerVariable($result);
        }

        return str_replace(
            ['##BEGIN-COMPONENT-CLASS##', '##END-COMPONENT-CLASS##'],
            '',
            $result
        );
    }

     /**
     * Add the stored footers onto the given content.
     *
     * @param  string  $result
     * @return string
     */
    protected function addFooters($result)
    {
        return ltrim($result, "\n")
                . "\n" . implode("\n", array_reverse($this->footer));
    }

    /**
     * Replace the raw placeholders with the original code stored in the raw blocks.
     *
     * @param  string  $result
     * @return string
     */
    protected function restoreRawContent($result)
    {
        $result = preg_replace_callback('/' . $this->getRawPlaceholder('(\d+)') . '/', function ($matches) {
            return $this->rawBlocks[$matches[1]];
        }, $result);

        $this->rawBlocks = [];

        return $result;
    }

    /**
     * Get a placeholder to temporary mark the position of raw blocks.
     *
     * @param  int|string  $replace
     * @return string
     */
    protected function getRawPlaceholder($replace)
    {
        return str_replace('#', $replace, '@__raw_block_#__@');
    }

    /**
     * Store the blocks that do not receive compilation.
     *
     * @param  string  $value
     * @return string
     */
    protected function storeUncompiledBlocks($value)
    {
        if (strpos($value, '@verbatim') !== false) {
            $value = $this->storeVerbatimBlocks($value);
        }

        if (strpos($value, '@php') !== false) {
            $value = $this->storePhpBlocks($value);
        }

        return $value;
    }

    /**
     * Store the verbatim blocks and replace them with a temporary placeholder.
     *
     * @param string $value
     * @return string
     */
    protected function storeVerbatimBlocks($value)
    {
        return preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function ($matches) {
            return $this->storeRawBlock($matches[1]);
        }, $value);
    }

    /**
     * Store the PHP blocks and replace them with a temporary placeholder.
     *
     * @param  string  $value
     * @return string
     */
    protected function storePhpBlocks($value)
    {
        return preg_replace_callback('/(?<!@)@php(.*?)@endphp/s', function ($matches) {
            return $this->storeRawBlock("<?php{$matches[1]}?>");
        }, $value);
    }

    /**
     * Store a raw block and return a unique raw placeholder.
     *
     * @param  string  $value
     * @return string
     */
    protected function storeRawBlock($value)
    {
        return $this->getRawPlaceholder(
            array_push($this->rawBlocks, $value) - 1
        );
    }

    /**
     * Parse the tokens from the template.
     *
     * @param  array  $token
     * @return string
     */
    protected function parseToken($token)
    {
        [$id, $content] = $token;

        if ($id == T_INLINE_HTML) {
            foreach ($this->compilers as $type) {
                $content = $this->{"compile{$type}"}($content);
            }
        }

        return $content;
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param  array  $array
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public static function last($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? value($default) : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function first($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($array)) {
                return value($default);
            }

            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return value($default);
    }

    /**
     * @param string $name
     * @param        $args []
     * @return string
     * @throws BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if ($method === 'if') {
            return $this->registerIfStatement($args[0] ?? null, $args[1] ?? null);
        }
        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }

    /**
     * Register an "if" statement directive.
     *
     * @param string   $name
     * @param callable $callback
     * @return string
     */
    public function registerIfStatement($name, callable $callback)
    {
        $this->conditions[$name] = $callback;

        $this->directive($name, function ($expression) use ($name) {
            $tmp = $this->stripParentheses($expression);
            return $expression !== ''
                ? "<?php if (\$this->check('$name', $tmp)): ?>"
                : "<?php if (\$this->check('$name')): ?>";
        });

        $this->directive('else' . $name, function ($expression) use ($name) {
            $tmp = $this->stripParentheses($expression);
            return $expression !== ''
                ? "<?php elseif (\$this->check('$name', $tmp)): ?>"
                : "<?php elseif (\$this->check('$name')): ?>";
        });

        $this->directive('end' . $name, function () {
            return '<?php endif; ?>';
        });
        return '';
    }

    /**
     * Check the result of a condition.
     *
     * @param string $name
     * @param array  $parameters
     * @return bool
     */
    public function check($name, ...$parameters)
    {
        return call_user_func($this->conditions[$name], ...$parameters);
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  array  $mergeData
     * @return mixed
     */
    public function make($view, $data = [], $mergeData = [])
    {
        // Next, we will create the view instance and call the view creator for the view
        // which can set any data, etc. Then we will return the view instance back to
        // the caller for rendering or performing other view manipulations on this.
        $data = array_merge($mergeData, $data);

        $variables = array_merge($this->variables, $data);

        return $this->create($view, $variables);
    }

    /**
     * Undocumented function
     *
     * @param string $view
     * @param array $data
     * @return string
     */
    private function create($view, $data = [])
    {
        if (count($this->composers)) {
            $this->evalComposer($view);
        }

        $this->variables = array_merge($data, $this->shared);
        $this->shared = [];
        $this->fileName = $view;
        $this->compile($view);

        return $this->evaluatePath($this->getCompiledPath($this->getPath()), $this->variables);
    }

    protected function evalComposer($view)
    {
        foreach ($this->composers as $viewKey => $fn) {
            if ($this->wildCardComparison($view, $viewKey)) {
                if (is_callable($fn)) {
                    $fn($this);
                } elseif ($this->methodExistsStatic($fn, 'composer')) {
                    // if the method exists statically then $fn is the class and 'composer' is the name of the method
                    $fn::composer($this);
                } elseif (is_object($fn) || class_exists($fn)) {
                    // if $fn is an object, or it is a class and the class exists.
                    $instance = (is_object($fn)) ? $fn : new $fn();
                    if (method_exists($instance, 'composer')) {
                        // and the method exists inside the instance.
                        $instance->composer($this);
                    } else {
                        $this->showError('evalComposer', 'composer() added an incorrect method', true, true);
                        return;
                    }
                } else {
                    $this->showError('evalComposer', 'composer() added an incorrect method', true, true);
                }
            }
        }
    }

    /**
     * It compares with wildcards (*) and returns true if both strings are equals<br>
     * The wildcards only works at the beginning and/or at the end of the string.<br>
     * <b>Example:<b><br>
     * <pre>
     * Text::wildCardComparison('abcdef','abc*'); // true
     * Text::wildCardComparison('abcdef','*def'); // true
     * Text::wildCardComparison('abcdef','*abc*'); // true
     * Text::wildCardComparison('abcdef','*cde*'); // true
     * Text::wildCardComparison('abcdef','*cde'); // false
     *
     * </pre>
     *
     * @param string      $text
     * @param string|null $textWithWildcard
     *
     * @return bool
     */
    protected function wildCardComparison($text, $textWithWildcard)
    {
        if (
            ($textWithWildcard === null || $textWithWildcard === '')
            || strpos($textWithWildcard, '*') === false
        ) {
            // if the text with wildcard is null or empty, or it contains two ** or it contains no * then..
            return $text == $textWithWildcard;
        }
        if ($textWithWildcard === '*' || $textWithWildcard === '**') {
            return true;
        }
        $c0 = $textWithWildcard[0];
        $c1 = substr($textWithWildcard, -1);
        $textWithWildcardClean = str_replace('*', '', $textWithWildcard);
        $p0 = strpos($text, $textWithWildcardClean);
        if ($p0 === false) {
            // no matches.
            return false;
        }
        if ($c0 === '*' && $c1 === '*') {
            // $textWithWildcard='*asasasas*'
            return true;
        }
        if ($c1 === '*') {
            // $textWithWildcard='asasasas*'
            return $p0 === 0;
        }
        // $textWithWildcard='*asasasas'
        $len = strlen($textWithWildcardClean);
        return (substr($text, -$len) === $textWithWildcardClean);
    }

    protected function methodExistsStatic($class, $method)
    {
        try {
            $mc = new ReflectionMethod($class, $method);
            return $mc->isStatic();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * Append the file path to the compiled string.
     *
     * @param  string  $contents
     * @return string
     */
    protected function appendFilePath($contents)
    {
        return $contents . "<?php /**PATH {$this->getPath()} ENDPATH**/ ?>";
    }

    /**
     * Get the path currently being compiled.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->viewPath . '/' . $this->path . '.blade.php';
    }

    /**
     * Set the path currently being compiled.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Compile the view at the given path.
     *
     * @param string $templateName The name of the template. Example folder.template
     * @param bool   $forced       If the compilation will be forced (always compile) or not.
     * @return boolean|string True if the operation was correct, or false (if not exception)
     *                             if it fails. It returns a string (the content compiled) if isCompiled=false
     * @throws Exception
     */
    public function compile($path = null)
    {
        if ($path) {
            $this->setPath($path);
        }

        if (! is_null($this->cachePath)) {
            $contents = $this->compileString($this->files->get($this->getPath()));

            if (! empty($this->getPath())) {
                $contents = $this->appendFilePath($contents);
            }

            $this->ensureCompiledDirectoryExists(
                $compiledPath = $this->getCompiledPath($this->getPath())
            );

            if ($this->isExpired($path)) {
                $this->files->put($compiledPath, $contents);
            }
        }

        return true;
    }

    /**
     * Get the full path of the template file.
     * <p>Example: getTemplateFile('.abc.def')</p>
     *
     * @param string $templateName template name. If not template is set then it uses the base template.
     * @return string
     */
    public function getTemplateFile($templateName = '')
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        if (strpos($templateName, '/') !== false) {
            return $this->locateTemplate($templateName); // it's a literal
        }
        $arr = explode('.', $templateName);
        $c = \count($arr);
        if ($c == 1) {
            // its in the root of the template folder.
            return $this->locateTemplate("{$templateName}.blade.php");
        }

        $file = $arr[$c - 1];
        \array_splice($arr, $c - 1, $c - 1); // delete the last element
        $path = implode('/', $arr);
        return $this->locateTemplate("{$path}/{$file}.blade.php");
    }

    /**
     * Find template file with the given name in all template paths in the order the paths were written
     *
     * @param string $name Filename of the template (without path)
     * @return string template file
     */
    private function locateTemplate($name)
    {
        $path = $this->viewPath . '/' . $name;
        if (is_file($path)) {
            return $path;
        }

        return '';
    }

    /**
     * Evaluates a text (string) using the current variables
     *
     * @param string $content
     * @param array  $variables
     * @return string
     * @throws Exception
     */
    protected function evaluateText($content, $variables)
    {
        ob_start();
        \extract($variables);
        // We'll evaluate the contents of the view inside a try/catch block so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.
        try {
            eval(" ?>{$content}<?php ");
        } catch (Exception $e) {
            $this->handleViewException($e);
        }
        return \ltrim(ob_get_clean());
    }

    /**
     * Handle a view exception.
     *
     * @param Exception $e
     * @return void
     * @throws $e
     */
    protected function handleViewException($e)
    {
        ob_get_clean();
        throw $e;
    }

    /**
     * Evaluates a compiled file using the current variables
     *
     * @param string $compiledFile full path of the compile file.
     * @param array  $variables
     * @return string
     * @throws Exception
     */
    protected function evaluatePath($compiledFile, $variables)
    {
        ob_start();

        // note, the variables are extracted locally inside this method,
        // they are not global variables :-3
        extract($variables);
        // We'll evaluate the contents of the view inside a try/catch block so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.

        try {
            include $compiledFile;
        } catch (Exception $e) {
            $this->handleViewException($e);
        }
        return ltrim(ob_get_clean());
    }

    /**
     * Get the first view that actually exists from the given list.
     *
     * @param array $views
     * @param array $data
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function includeFirst(array $views, $data = [], $mergeData = [])
    {
        $view = static::first($views, function ($view) {
            return $this->templateExist($view);
        });

        if (! $view) {
            throw new InvalidArgumentException('None of the views in the given array exist.');
        }

        return $this->make($view, $data);
    }

    /**
     * Determine if a given view exists.
     *
     * @param  string  $view
     * @return bool
     */
    private function templateExist($templateName)
    {
        if (!is_file($this->getTemplateFile($templateName))) {
            return false;
        }

        return true;
    }


    /**
     * Add a piece of data to the view.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }

        return $this;
    }

    /**
     * Add a piece of shared data to the environment.
     *
     * @param  array|string  $key
     * @param  mixed|null  $value
     * @return mixed
     */
    public function share($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            $this->shared[$key] = $value;
        }

        return $value;
    }


    /**
     * Register a custom Blade compiler.
     *
     * @param callable $compiler
     * @return void
     */
    public function extend(callable $compiler)
    {
        $this->extensions[] = $compiler;
    }

    /**
     * Sets the escaped content tags used for the compiler.
     *
     * @param string $openTag
     * @param string $closeTag
     * @return void
     */
    public function setEscapedContentTags($openTag, $closeTag)
    {
        $this->setContentTags($openTag, $closeTag, true);
    }

    /**
     * Gets the content tags used for the compiler.
     *
     * @return array
     */
    public function getContentTags()
    {
        return $this->getTags();
    }

    /**
     * Sets the content tags used for the compiler.
     *
     * @param string $openTag
     * @param string $closeTag
     * @param bool   $escaped
     * @return void
     */
    public function setContentTags($openTag, $closeTag, $escaped = false)
    {
        $property = ($escaped === true) ? 'escapedTags' : 'contentTags';
        $this->{$property} = [\preg_quote($openTag), \preg_quote($closeTag)];
    }

    /**
     * Gets the tags used for the compiler.
     *
     * @param bool $escaped
     * @return array
     */
    protected function getTags($escaped = false)
    {
        $tags = $escaped ? $this->escapedTags : $this->contentTags;
        return \array_map('stripcslashes', $tags);
    }

    /**
     * Gets the escaped content tags used for the compiler.
     *
     * @return array
     */
    public function getEscapedContentTags()
    {
        return $this->getTags(true);
    }

    /**
     * Sets the function used for resolving classes with inject.
     *
     * @param callable $function
     */
    public function setInjectResolver(callable $function)
    {
        $this->injectResolver = $function;
    }

    /**
     * Get the rendered contents of a partial from a loop.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  string  $iterator
     * @param  string  $empty
     * @return string
     */
    public function renderEach($view, $data, $iterator, $empty = 'raw|')
    {
        $result = '';

        // If is actually data in the array, we will loop through the data and append
        // an instance of the partial view to the final result HTML passing in the
        // iterated value of this data array, allowing the views to access them.
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $result .= $this->make(
                    $view,
                    ['key' => $key, $iterator => $value]
                );
            }
        } else {
            // If there is no data in the array, we will render the contents of the empty
            // view. Alternatively, the "empty view" could be a raw string that begins
            // with "raw|" for convenience and to let this know that it is a string.
            $result = static::startsWith($empty, 'raw|')
                        ? substr($empty, 4)
                        : $this->make($empty);
        }

        return $result;
    }

    /**
     * Run the blade engine. It returns the result of the code.
     *
     * @param string|null $view      The name of the cache. Ex: "folder.folder.view" ("/folder/folder/view.blade")
     * @param array       $variables An associative arrays with the values to display.
     * @return string
     * @throws Exception
     */
    public function run($view = null, $variables = [])
    {
        if ($view === null) {
            throw new Exception("Pasta de template não definida.", 1);
            exit;
        }

        $this->sections = [];

        return $this->create($view, $variables);
    }

     /**
     * Register a view composer event.
     *
     * @param  array|string  $views
     * @param  \Closure|string  $callback
     * @return array
     */
    public function composer($views, $callback)
    {
        $this->composers = [];

        foreach ((array) $views as $view) {
            $this->composers[$view] = $callback;
        }

        return $this;
    }


    /**
     * @return string
     */
    public function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * @param string $currentUser
     */
    public function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
    }

    /**
     * @return string
     */
    public function getCurrentRole()
    {
        return $this->currentRole;
    }

    /**
     * @param string $currentRole
     */
    public function setCurrentRole($currentRole)
    {
        $this->currentRole = $currentRole;
    }

    /**
     * @return string[]
     */
    public function getCurrentPermission()
    {
        return $this->currentPermission;
    }

    /**
     * @param string[] $currentPermission
     */
    public function setCurrentPermission($currentPermission)
    {
        $this->currentPermission = $currentPermission;
    }

    /**
     * It sets the base url and it also calculates the relative path.<br>
     * The base url defines the "root" of the project, not always the level of the domain but it could be
     * any folder.<br>
     * This value is used to calculate the relativity of the resources but it is also used to set the domain.<br>
     * <b>Note:</b> The trailing slash is removed automatically if it's present.<br>
     * <b>Note:</b> We should not use arguments or name of the script.<br>
     * <b>Examples:</b><br>
     * <pre>
     * $this->setBaseUrl('http://domain.dom/myblog');
     * $this->setBaseUrl('http://domain.dom/corporate/erp');
     * $this->setBaseUrl('http://domain.dom/blog.php?args=20'); // avoid this one.
     * $this->setBaseUrl('http://another.dom');
     * </pre>
     *
     * @param string $baseUrl Example http://www.web.com/folder  https://www.web.com/folder/anotherfolder
     * @return BladeCompiler
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = \rtrim($baseUrl, '/'); // base with the url trimmed
        $this->baseDomain = @parse_url($this->baseUrl)['host'];
        $currentUrl = $this->getCurrentUrlCalculated();
        if ($currentUrl === '') {
            $this->relativePath = '';
            return $this;
        }
        if (strpos($currentUrl, $this->baseUrl) === 0) {
            $part = \str_replace($this->baseUrl, '', $currentUrl);
            $numf = substr_count($part, '/') - 1;
            $numf = ($numf > 10) ? 10 : $numf; // avoid overflow
            $this->relativePath = ($numf < 0) ? '' : \str_repeat('../', $numf);
        } else {
            $this->relativePath = '';
        }
        return $this;
    }

    /**
     * It gets the full current url calculated with the information sends by the user.<br>
     * <b>Note:</b> If we set baseurl, then it always uses the baseurl as domain (it's safe).<br>
     * <b>Note:</b> This information could be forged/faked by the end-user.<br>
     * <b>Note:</b> It returns empty '' if it is called in a command line interface / non-web.<br>
     * <b>Note:</b> It doesn't returns the user and password.<br>
     * @param bool $noArgs if true then it excludes the arguments.
     * @return string
     */
    public function getCurrentUrlCalculated($noArgs = false)
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            return '';
        }
        $host = $this->baseDomain ?? $_SERVER['HTTP_HOST']; // <-- it could be forged!
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $port = $_SERVER['SERVER_PORT'];
        $port2 = (($link === 'http' && $port === '80') || ($link === 'https' && $port === '443')) ? '' : ':' . $port;
        $link .= "://$host$port2$_SERVER[REQUEST_URI]";
        if ($noArgs) {
            $link = @explode('?', $link)[0];
        }
        return $link;
    }

    /**
     * It gets the full current url<br>
     * <b>Example:</b> https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <ul>
     * <li>It returns the $this->currentUrl if not null</li>
     * <li>Otherwise, the url is calculated with the information sends by the user</li>
     * </ul>
     *
     * @param bool $noArgs if true then it ignore the arguments.
     * @return string|null
     */
    public function getCurrentUrl($noArgs = false)
    {
        $link = $this->currentUrl ?? $this->getCurrentUrlCalculated();
        if ($noArgs) {
            $link = @explode('?', $link)[0];
        }
        return $link;
    }

    /**
     * It sets the full current url.<br>
     * <b>Example:</b> https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <b>Note:</b> If the current url is not set, then the system could calculate the current url.
     *
     * @param string|null $currentUrl
     * @return BladeCompiler
     */
    public function setCurrentUrl($currentUrl = null)
    {
        $this->currentUrl = $currentUrl;
        return $this;
    }

    /**
     * Execute the user defined extensions.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileExtensions($value)
    {
        foreach ($this->extensions as $compiler) {
            $value = $compiler($value, $this);
        }

        return $value;
    }

    /**
     * Compile Blade statements that start with "@".
     *
     * @param  string  $value
     * @return string
     */
    protected function compileStatements($value)
    {
        return preg_replace_callback(
            '/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x',
            function ($match) {
                return $this->compileStatement($match);
            },
            $value
        );
    }

    /**
     * Compile a single Blade @ statement.
     *
     * @param  array  $match
     * @return string
     */
    protected function compileStatement($match)
    {
        if (static::contains($match[1], '@')) {
            $match[0] = isset($match[3]) ? $match[1] . $match[3] : $match[1];
        } elseif (isset($this->customDirectives[$match[1]])) {
            $match[0] = $this->callCustomDirective($match[1], static::get($match, 3));
        } elseif (method_exists($this, $method = 'compile' . ucfirst($match[1]))) {
            $match[0] = $this->$method(static::get($match, 3));
        }

        return isset($match[3]) ? $match[0] : $match[0] . $match[2];
    }

    /**
     * Call the given directive with the given value.
     *
     * @param  string  $name
     * @param  string|null  $value
     * @return string
     */
    protected function callCustomDirective($name, $value)
    {
        $value = $value ?? '';

        if (static::startsWith($value, '(') && static::endsWith($value, ')')) {
            $value = static::substr($value, 1, -1);
        }

        return call_user_func($this->customDirectives[$name], trim($value));
    }

    /**
     * Returns the portion of the string specified by the start and length parameters.
     *
     * @param  string  $string
     * @param  int  $start
     * @param  int|null  $length
     * @return string
     */
    public static function substr($string, $start, $length = null)
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function endsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if (
                $needle !== '' && $needle !== null
                && substr($haystack, -strlen($needle)) === (string) $needle
            ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Determine if a given string contains a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function contains($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cap a string with a single instance of a given value.
     *
     * @param  string  $value
     * @param  string  $cap
     * @return string
     */
    public static function finish($value, $cap)
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    /**
     * For compile custom directive at runtime.
     *
     * @param $match
     * @return string
     */
    protected function compileStatementCustom($match)
    {
        $v = $this->stripParentheses(static::get($match, 3));
        $v = ($v == '') ? '' : ',' . $v;
        return '<?php call_user_func($this->customDirectives[\'' . $match[1] . '\']' . $v . '); ?>';
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param ArrayAccess|array $array
     * @param string            $key
     * @param mixed             $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        $accesible = is_array($array) || $array instanceof ArrayAccess;
        if (!$accesible) {
            return static::value($default);
        }
        if (is_null($key)) {
            return $array;
        }
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $segment) {
            if (static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return static::value($default);
            }
        }
        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param ArrayAccess|array $array
     * @param string|int        $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return array_key_exists($key, $array);
    }
}
