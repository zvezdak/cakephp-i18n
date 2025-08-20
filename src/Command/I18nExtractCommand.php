<?php
declare(strict_types=1);

namespace ADmad\I18n\Command;

use Cake\Command\I18nExtractCommand as CakeI18nExtractCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Utility\Inflector;

class I18nExtractCommand extends CakeI18nExtractCommand
{
    use I18nModelTrait;

    public const DEFAULT_MODEL = 'I18nMessages';

    /** @var list<string> */
    protected array $_languages = [];

    /** @var bool */
    protected bool $_relativePaths = false;

    public function initialize(): void
    {
        $this->_model = $this->getTableLocator()->get(static::DEFAULT_MODEL);
    }


    public static function defaultName(): string
    {
        return 'admad/i18n extract';
    }

    protected function _getLanguages(Arguments $args): array
    {
        $languages = [];
        if ($args->hasOption('languages') && $args->getOption('languages')) {
            $languages = explode(',', (string)$args->getOption('languages'));
        } elseif (Configure::read('I18n.languages')) {
            $languages = (array)Configure::read('I18n.languages');
        }
        return array_map('trim', $languages);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->_languages = $this->_getLanguages($args);
        if ($this->_languages === []) {
            $io->err(
                'You must specify the languages list using the `I18n.languages` config or the `--languages` option.'
            );
            return static::CODE_ERROR;
        }

        $plugin = '';
        if ($args->hasOption('exclude')) {
            $this->_exclude = explode(',', (string)$args->getOption('exclude'));
        }
        if ($args->hasOption('files')) {
            $this->_files = explode(',', (string)$args->getOption('files'));
        }
        if ($args->hasOption('paths')) {
            $this->_paths = explode(',', (string)$args->getOption('paths'));
        } elseif ($args->hasOption('plugin')) {
            $plugin = Inflector::camelize((string)$args->getOption('plugin'));
            $this->_paths = [Plugin::classPath($plugin), Plugin::templatePath($plugin)];
        } else {
            $this->_getPaths($io);
        }

        if ($args->hasOption('extract-core')) {
            $this->_extractCore = strtolower((string)$args->getOption('extract-core')) !== 'no';
        } else {
            $response = $io->askChoice(
                'Would you like to extract the messages from the CakePHP core?',
                ['y', 'n'],
                'n'
            );
            $this->_extractCore = strtolower($response) === 'y';
        }

        if ($args->hasOption('exclude-plugins') && $this->_isExtractingApp()) {
            $this->_exclude = array_merge($this->_exclude, App::path('plugins'));
        }

        if ($this->_extractCore) {
            $this->_paths[] = CAKE;
        }

        if ($args->hasOption('merge')) {
            $this->_merge = strtolower((string)$args->getOption('merge')) === 'yes';
        } else {
            $io->out();
            $response = $io->askChoice(
                'Would you like to merge all domain strings into the default.pot file?',
                ['y', 'n'],
                'n'
            );
            $this->_merge = strtolower($response) === 'y';
        }

        $this->_markerError = (bool)$args->getOption('marker-error');

        if (property_exists($this, '_relativePaths')) {
            $this->_relativePaths = (bool)$args->getOption('relative-paths');
        }

        if (empty($this->_files)) {
            $this->_searchFiles();
        }

        // Call parent extract method (this triggers _addMessage internally)
        $this->_extract($args, $io);

        return static::CODE_SUCCESS;
    }

    /**
     * Save extracted message to DB
     */
    protected function _save(
        string $domain,
        string $singular,
        ?string $plural = null,
        ?string $context = null,
        ?string $refs = null,
        ?ConsoleIo $io = null
    ): void {
        if (!$this->_model) {
            $this->_model = $this->getTableLocator()->get(static::DEFAULT_MODEL);
        }

        foreach ($this->_languages as $locale) {
            $existing = $this->_model->find()
                ->where(compact('domain', 'locale', 'singular'))
                ->first();

            if ($existing) {
                continue;
            }

            $entity = $this->_model->newEmptyEntity();
            $entity = $this->_model->patchEntity($entity, [
                'domain' => $domain,
                'locale' => $locale,
                'singular' => $singular,
                'plural' => $plural,
                'context' => $context,
                'value_0' => null,
                'value_1' => null,
                'value_2' => null,
            ]);

            if (!$this->_model->save($entity) && $io) {
                $io->err('Failed to save message: ' . json_encode($entity->getErrors()));
            }
        }
    }

   protected function _collectMessages(): array
{
    $messages = [];

    foreach ($this->_files as $file) {
        $content = file_get_contents($file);
        if (!$content) {
            continue;
        }

        // Match __('text'), __n('singular', 'plural', ...), etc.
        preg_match_all('/__\(\s*[\'"](.*?)[\'"]/', $content, $matches);
        foreach ($matches[1] as $msg) {
            $messages[] = [
                'domain' => 'default',
                'singular' => $msg,
                'plural' => null,
                'context' => null,
                'refs' => $file,
            ];
        }
    }

    return $messages;
}


    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(
            'Extract translated strings from application source files. ' .
            'Source files are parsed and string literal format strings ' .
            'provided to the <info>__</info> family of functions are extracted.'
        )
        ->addOption('model', [
            'help' => 'Model to use for storing messages. Defaults to: ' . static::DEFAULT_MODEL,
        ])
        ->addOption('languages', [
            'help' => 'Comma separated list of languages used by app. Defaults used from `I18n.languages` config.',
        ])
        ->addOption('app', [
            'help' => 'Directory where your application is located.',
        ])
        ->addOption('paths', [
            'help' => 'Comma separated list of paths that are searched for source files.',
        ])
        ->addOption('merge', [
            'help' => 'Merge all domain strings into a single `default` domain.',
            'default' => 'no',
            'choices' => ['yes', 'no'],
        ])
        ->addOption('relative-paths', [
            'help' => 'Use application relative paths in references.',
            'boolean' => true,
            'default' => false,
        ])
        ->addOption('files', [
            'help' => 'Comma separated list of files to parse.',
        ])
        ->addOption('exclude-plugins', [
            'boolean' => true,
            'default' => true,
            'help' => 'Ignores all files in plugins if this command is run inside from the same app directory.',
        ])
        ->addOption('plugin', [
            'help' => 'Extracts tokens only from the plugin specified and puts the result in the plugin\'s Locale directory.',
        ])
        ->addOption('exclude', [
            'help' => 'Comma separated list of directories to exclude.',
        ])
        ->addOption('extract-core', [
            'help' => 'Extract messages from the CakePHP core libraries.',
            'choices' => ['yes', 'no'],
        ])
        ->addOption('no-location', [
            'boolean' => true,
            'default' => false,
            'help' => 'Do not write file locations for each extracted message.',
        ])
        ->addOption('marker-error', [
            'boolean' => true,
            'default' => false,
            'help' => 'Do not display marker error.',
        ]);

        return $parser;
    }

    protected function _extract(Arguments $args, ConsoleIo $io): void
{
    $messages = $this->_collectMessages();

    foreach ($messages as $message) {
        $this->_save(
            $message['domain'],
            $message['singular'],
            $message['plural'],
            $message['context'],
            $message['refs'],
            $io
        );
    }

    // Still call parent to generate .pot files
    parent::_extract($args, $io);
}

}
