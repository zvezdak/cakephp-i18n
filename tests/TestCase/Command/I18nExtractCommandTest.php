<?php
declare(strict_types=1);

namespace ADmad\I18n\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use TestPlugin\Plugin;

/**
 * I18nExtractCommand Test Case.
 */
class I18nExtractCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['plugin.ADmad/I18n.I18nMessages'];

    protected Table $model;

    public function setUp(): void
    {
        parent::setUp();

        // Explicitly set test app namespace
        $this->setAppNamespace('TestApp');

        // Configure the test application
        $this->configApplication(
            'TestApp\Application',
            [TESTS . 'test_app' . DS . 'config']
        );

        // Get I18nMessages table
        $this->model = $this->getTableLocator()->get('ADmad/I18n.I18nMessages');

        // Clean up old messages
        $this->model->deleteAll([]);

        // Set languages for extraction
        Configure::write('I18n.languages', ['en_US', 'fr_FR']);
    }

    public function testExecute()
    {
        $extractPath = TESTS . 'test_app' . DS . 'templates' . DS . 'Pages';

        if (!file_exists($extractPath . DS . 'test_extract.php')) {
            $this->fail('Test file test_extract.php does not exist at: ' . $extractPath);
        }

        $this->exec(
            'i18n extract ' .
            '--extract-core=no ' .
            '--merge=no ' .
            '--paths=' . escapeshellarg($extractPath)
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['domain' => 'default'])
            ->count();
        $this->assertTrue($result > 0);

        $result = $this->model->find()
            ->where(['domain' => 'domain'])
            ->count();
        $this->assertTrue($result > 0);

        $result = $this->model->find()
            ->where(['domain' => 'cake'])
            ->count();
        $this->assertSame(0, $result);

        $result = $this->model->find()
            ->where(['singular' => 'You have %d new message.'])
            ->enableHydration(false)
            ->first();
        $this->assertTrue((bool)$result);

        $result = $this->model->find()
            ->where(['singular' => 'letter'])
            ->enableHydration(false)
            ->first();
        $this->assertEquals('mail', $result['context']);

        $result = $this->model->find()
            ->where([
                'domain' => 'domain',
                'singular' => 'You have %d new message (domain).',
            ])
            ->enableHydration(false)
            ->first();
        $this->assertEquals('You have %d new message (domain).', $result['singular']);
        $this->assertEquals('You have %d new messages (domain).', $result['plural']);
    }

    public function testExecuteError()
    {
        Configure::delete('I18n.languages');

        $this->exec('i18n extract');
        $this->assertExitError();
        $this->assertErrorContains('You must specify the languages list using the `I18n.languages` config or the `--languages` option.');
    }

    public function testExecuteMerge()
    {
        $extractPath = TESTS . 'test_app' . DS . 'templates' . DS . 'Pages';

        $this->exec(
            'i18n extract ' .
            '--extract-core=no ' .
            '--merge=yes ' .
            '--paths=' . escapeshellarg($extractPath)
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['domain' => 'default'])
            ->count();
        $this->assertTrue($result > 0);

        $result = $this->model->find()
            ->where(['domain' => 'domain'])
            ->count();
        $this->assertSame(0, $result);
    }

    public function testExtractWithExclude()
    {
        $extractPath = TESTS . 'test_app' . DS . 'templates';

        $this->exec(
            'i18n extract ' .
            '--extract-core=no ' .
            '--exclude=Pages,layout ' .
            '--paths=' . escapeshellarg($extractPath)
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['refs LIKE' => '%extract.php%'])
            ->count();
        $this->assertSame(0, $result);

        $result = $this->model->find()
            ->where(['refs LIKE' => '%cache_form.php%'])
            ->count();
        $this->assertTrue($result > 0);
    }

    public function testExtractWithoutLocations()
    {
        $extractPath = TESTS . 'test_app' . DS . 'templates';

        $this->exec(
            'i18n extract ' .
            '--extract-core=no ' .
            '--exclude=Pages,layout ' .
            '--no-location ' .
            '--paths=' . escapeshellarg($extractPath)
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['refs IS NOT' => null])
            ->count();
        $this->assertSame(0, $result);
    }

    public function testExtractMultiplePaths()
    {
        $pagesPath = TESTS . 'test_app' . DS . 'templates' . DS . 'Pages';
        $postsPath = TESTS . 'test_app' . DS . 'templates' . DS . 'Posts';

        $this->exec(
            'i18n extract ' .
            '--extract-core=no ' .
            '--paths=' . escapeshellarg($pagesPath . ',' . $postsPath)
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['refs LIKE' => '%extract.php%'])
            ->count();
        $this->assertTrue($result > 0);

        $result = $this->model->find()
            ->where(['refs LIKE' => '%cache_form.php%'])
            ->count();
        $this->assertTrue($result > 0);
    }

    public function testExtractExcludePlugins()
    {
        $srcPath = TESTS . 'test_app' . DS . 'src';

        $this->exec(
            'i18n extract ' .
            '--exclude-plugins ' .
            '--paths=' . escapeshellarg($srcPath) .
            ' --extract-core=no'
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['refs LIKE' => '%TestPlugin%'])
            ->count();
        $this->assertSame(0, $result);
    }

    public function testExtractPlugin()
{
    $plugin = new Plugin();
    $this->loadPlugins([$plugin]);

    $potFile = PLUGIN_TESTS . 'test_app' . DS . 'plugins' . DS . 'TestPlugin' . DS . 'resources' . DS . 'locales' . DS . 'default.pot';
    @unlink($potFile);

    $this->exec(
        'i18n extract ' .
        '--plugin=TestPlugin --extract-core=no'
    );
    $this->assertExitSuccess();

    $result = $this->model->find()
        ->where(['refs LIKE' => '%translate.php%'])
        ->count();
    $this->assertTrue($result > 0);
}

    public function testExtractVendoredPlugin()
    {
        $plugin = new \Company\TestPluginThree\Plugin();
        $this->loadPlugins([$plugin]);

        $this->exec(
            'i18n extract ' .
            '--extract-core=no ' .
            '--plugin=Company/TestPluginThree'
        );

        debug($this->_out->output());

        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['domain' => 'default'])
            ->count();
        $this->assertSame(0, $result);

        $result = $this->model->find()
            ->where(['domain' => 'company/test_plugin_three'])
            ->count();
        $this->assertTrue($result > 0);
    }

    public function testExtractCore()
    {
        $srcPath = TESTS . 'test_app' . DS . 'src';

        $this->exec(
            'i18n extract ' .
            '--extract-core=yes ' .
            '--paths=' . escapeshellarg($srcPath)
        );
        $this->assertExitSuccess();

        $result = $this->model->find()
            ->where(['domain' => 'cake'])
            ->count();
        $this->assertTrue($result > 0);
    }
}
