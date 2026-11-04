<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Service;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Error\SyntaxError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityPolicy;
use Twig\TwigFilter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Creates sandboxed Twig environments and renders templates.
 *
 * Both environments run in the Twig sandbox with an allow-list. This keeps the full expressiveness
 * needed for course listings (loops, conditions, arithmetic, date handling, `slice`, `date_modify` …)
 * while blocking everything that reaches outside the template: `include`/`source` (file access),
 * `constant()`, `extends`/`use`, method calls on objects and string callables in `map`/`filter`/`sort`.
 * Without the sandbox, a Twig template can execute PHP functions, so the sandbox is what makes it safe
 * to let non-super-user managers edit templates.
 *
 * @since  1.0.0
 */
final class TwigFactory
{
    /**
     * Tags allowed in output templates.
     */
    public const TEMPLATE_TAGS = [
        'apply', 'autoescape', 'do', 'for', 'from', 'guard', 'if', 'import', 'macro', 'set', 'types', 'verbatim', 'with',
    ];

    /**
     * Filters allowed in output templates (all Twig core filters except `invoke`, plus `jdate`).
     */
    public const TEMPLATE_FILTERS = [
        'abs', 'batch', 'capitalize', 'column', 'convert_encoding', 'date', 'date_modify', 'default', 'e', 'escape',
        'filter', 'find', 'first', 'format', 'join', 'json_encode', 'keys', 'last', 'length', 'lower', 'map', 'merge',
        'nl2br', 'number_format', 'raw', 'reduce', 'replace', 'reverse', 'round', 'shuffle', 'slice', 'sort',
        'spaceless', 'split', 'striptags', 'title', 'trim', 'upper', 'url_encode',
        'jdate',
    ];

    /**
     * Functions allowed in output templates. Not allowed: include, source, constant, enum, parent, block.
     */
    public const TEMPLATE_FUNCTIONS = ['attribute', 'cycle', 'date', 'max', 'min', 'random', 'range'];

    /**
     * Tests allowed in all environments (all core tests except `constant`).
     */
    public const TESTS = [
        'defined', 'divisible by', 'empty', 'even', 'iterable', 'mapping', 'none', 'null', 'odd', 'same as', 'sequence', 'true',
    ];

    /**
     * Allow-list for the GraphQL variables preprocessor, which only needs to compute values.
     */
    public const VARIABLES_TAGS      = ['if', 'set'];
    public const VARIABLES_FILTERS   = [
        'abs', 'date', 'date_modify', 'default', 'format', 'json_encode', 'lower', 'number_format', 'replace', 'round', 'trim', 'upper',
    ];
    public const VARIABLES_FUNCTIONS = ['date', 'max', 'min'];

    /**
     * Minimum Twig version (the bundled one); older copies loaded by other extensions are rejected.
     */
    public const MIN_TWIG_VERSION = '3.30.0';

    /**
     * Whether our Twig autoloader has been registered
     *
     * @var boolean
     */
    private static bool $autoloaderRegistered = false;

    private ?Environment $templateEnvironment  = null;
    private ?Environment $variablesEnvironment = null;

    /**
     * @param   string  $timezone  Timezone used by the `date` filter and function (the site timezone)
     *
     * @since   1.0.0
     */
    public function __construct(private readonly string $timezone)
    {
    }

    /**
     * Makes the bundled Twig library loadable.
     *
     * Twig is loaded through a small PSR-4 autoloader that is only consulted for the `Twig\` namespace.
     * If another extension already provides Twig 3, that copy is used instead of loading a second one.
     *
     * @return  void
     *
     * @throws  KursoException  If no compatible Twig version is available
     *
     * @since   1.0.0
     */
    public static function ensureLoaded(): void
    {
        if (!self::$autoloaderRegistered && !class_exists(Environment::class)) {
            $base = \dirname(__DIR__, 2) . '/vendor/twig/twig/src/';

            spl_autoload_register(static function (string $class) use ($base): void {
                if (!str_starts_with($class, 'Twig\\')) {
                    return;
                }

                $file = $base . str_replace('\\', '/', substr($class, 5)) . '.php';

                if (is_file($file)) {
                    require $file;
                }
            });

            self::$autoloaderRegistered = true;
        }

        if (!class_exists(Environment::class)) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_TWIG_MISSING'));
        }

        // Another extension may have loaded its own Twig before us. Older releases lack sandbox protections this
        // extension relies on (e.g. closures only in "map"/"filter"/"sort"), so refuse to render with them.
        if (Environment::MAJOR_VERSION !== 3 || version_compare(Environment::VERSION, self::MIN_TWIG_VERSION, '<')) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_TWIG_INCOMPATIBLE', Environment::VERSION, self::MIN_TWIG_VERSION));
        }
    }

    /**
     * Renders an output template.
     *
     * @param   string  $template  The Twig template source
     * @param   array   $context   The template variables (the `data` object of the GraphQL response)
     *
     * @return  string  The rendered HTML
     *
     * @throws  KursoException  On syntax, sandbox or runtime errors (message includes the line number)
     *
     * @since   1.0.0
     */
    public function renderTemplate(string $template, array $context): string
    {
        return $this->render($this->getTemplateEnvironment(), $template, $context);
    }

    /**
     * Checks an output template for syntax errors and disallowed constructs without rendering it.
     *
     * @param   string  $template  The Twig template source
     *
     * @return  void
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public function validateTemplate(string $template): void
    {
        $env = $this->getTemplateEnvironment();

        try {
            // Parsing reports syntax errors and disallowed tags; the sandbox checks filters and functions when the
            // template starts rendering, so render once with an empty context to check them, too.
            $env->createTemplate($template, 'template')->render([]);
        } catch (SyntaxError | SecurityError $e) {
            throw new KursoException(self::describe($e), 0, $e);
        } catch (TwigError) {
            // Runtime errors caused by the empty context (e.g. arithmetic on missing values) are not template errors.
        }
    }

    /**
     * Evaluates Twig expressions inside a GraphQL variables JSON string.
     *
     * @param   string  $source  The variables JSON with optional Twig expressions
     *
     * @return  string  The evaluated JSON string
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public function renderVariables(string $source): string
    {
        return $this->render($this->getVariablesEnvironment(), $source, []);
    }

    /**
     * Renders a template in the given environment and converts Twig errors to KursoExceptions.
     *
     * @param   Environment  $env       The environment
     * @param   string       $template  The template source
     * @param   array        $context   The variables
     *
     * @return  string
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    private function render(Environment $env, string $template, array $context): string
    {
        try {
            return $env->createTemplate($template, 'template')->render($context);
        } catch (TwigError $e) {
            throw new KursoException(self::describe($e), 0, $e);
        }
    }

    /**
     * Builds a readable error message including the template line.
     *
     * @param   TwigError  $e  The Twig error
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function describe(TwigError $e): string
    {
        $message = $e->getRawMessage();

        if ($e instanceof SecurityError) {
            $message = Text::sprintf('COM_KURSO_ERROR_TWIG_NOT_ALLOWED', $message);
        }

        $line = $e->getTemplateLine();

        return $line > 0 ? Text::sprintf('COM_KURSO_ERROR_TWIG_LINE', $line, $message) : $message;
    }

    /**
     * @return  Environment  The environment for output templates (HTML autoescaping)
     *
     * @since   1.0.0
     */
    private function getTemplateEnvironment(): Environment
    {
        if ($this->templateEnvironment === null) {
            self::ensureLoaded();

            $policy = new SecurityPolicy(self::TEMPLATE_TAGS, self::TEMPLATE_FILTERS, [], [], self::TEMPLATE_FUNCTIONS, self::TESTS);
            $env    = $this->createEnvironment($policy, 'html');

            $env->addFilter(new TwigFilter('jdate', [self::class, 'joomlaDate']));

            $this->templateEnvironment = $env;
        }

        return $this->templateEnvironment;
    }

    /**
     * @return  Environment  The environment for the variables preprocessor (no escaping, it produces JSON)
     *
     * @since   1.0.0
     */
    private function getVariablesEnvironment(): Environment
    {
        if ($this->variablesEnvironment === null) {
            self::ensureLoaded();

            $policy = new SecurityPolicy(self::VARIABLES_TAGS, self::VARIABLES_FILTERS, [], [], self::VARIABLES_FUNCTIONS, self::TESTS);

            $this->variablesEnvironment = $this->createEnvironment($policy, false);
        }

        return $this->variablesEnvironment;
    }

    /**
     * Creates a sandboxed environment.
     *
     * @param   SecurityPolicy  $policy      The sandbox policy
     * @param   string|false    $autoescape  The autoescape strategy
     *
     * @return  Environment
     *
     * @since   1.0.0
     */
    private function createEnvironment(SecurityPolicy $policy, string|false $autoescape): Environment
    {
        // Strict mode: no implicit "extends"/"use" tags or "parent"/"block" functions (Twig 4 behaviour).
        if (method_exists($policy, 'setStrict')) {
            $policy->setStrict(true);
        }

        $env = new Environment(new ArrayLoader(), [
            'autoescape'       => $autoescape,
            'strict_variables' => false,
            'cache'            => false,
            'charset'          => 'UTF-8',
        ]);

        $env->addExtension(new SandboxExtension($policy, true));

        try {
            $env->getExtension(CoreExtension::class)->setTimezone($this->timezone ?: 'UTC');
        } catch (\Exception) {
            $env->getExtension(CoreExtension::class)->setTimezone('UTC');
        }

        return $env;
    }

    /**
     * The `jdate` filter: formats a date with Joomla's date handling, i.e. translated month and day names
     * in the site language and conversion to the site timezone.
     *
     * Date-only values such as "2027-01-18" are not shifted between timezones.
     *
     * @param   mixed   $value   A date string, timestamp or DateTimeInterface
     * @param   string  $format  A PHP date format or a Joomla language key such as DATE_FORMAT_LC4
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function joomlaDate(mixed $value, string $format = 'DATE_FORMAT_LC4'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Language keys such as DATE_FORMAT_LC4 (contain an underscore, so "Y" or "H" stay date formats).
        if (preg_match('/^[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+$/', $format)) {
            $format = Text::_($format);
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTimeInterface::ATOM);
            } elseif (\is_int($value) || (\is_string($value) && ctype_digit($value))) {
                $value = '@' . $value;
            }

            $dateOnly = \is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
            $date     = new Date((string) $value, 'UTC');

            if (!$dateOnly) {
                $date->setTimezone(new \DateTimeZone(self::siteTimezone()));
            }

            return $date->format($format, true, true);
        } catch (\Exception) {
            return (string) $value;
        }
    }

    /**
     * @return  string  The timezone of the current user, falling back to the site timezone
     *
     * @since   1.0.0
     */
    private static function siteTimezone(): string
    {
        try {
            $app  = Factory::getApplication();
            $user = $app->getIdentity();
            $tz   = $user ? (string) $user->getParam('timezone', '') : '';

            return $tz !== '' ? $tz : (string) $app->get('offset', 'UTC');
        } catch (\Exception) {
            return 'UTC';
        }
    }
}
