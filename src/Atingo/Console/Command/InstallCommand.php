<?php
namespace Atingo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\Question;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Progress\Progress;

use Alchemy\Zippy\Zippy;
use Alchemy\Zippy\Exception\FormatNotSupportedException;

class InstallCommand extends Command
{
    private $input;
    private $output; 

    private $uriDownload = 'https://github.com/joomla/joomla-cms/archive/staging.zip';
    private $config;

    private $db = null;

    protected function configure()
    {
        $this
            ->setName('joomla:install')
            ->setDescription('Download and install')
            ->addOption(
                'install-dir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Installation directory?'
            )
            ->addOption(
                'host',
                null,
                InputOption::VALUE_OPTIONAL,
                'DB Host?'
            )
            ->addOption(
                'user',
                null,
                InputOption::VALUE_OPTIONAL,
                'DB user name?'
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'DB password?'
            )
            ->addOption(
                'db',
                null,
                InputOption::VALUE_OPTIONAL,
                'DB name?'
            )
            ->addOption(
                'dbprefix',
                null,
                InputOption::VALUE_OPTIONAL,
                'DB prefix?'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input; 
        $this->output = $output;

        // Download Joomla
        //$joomlaFile = $this->download();
        //$joomlaFile = '/tmp/joomla-downloadMCxkFu.zip';

        // Get Installation Directory
        //$installationDir = $this->getInstallationDir($input);
        $installationDir = '/home/vifito/dev/joomla3';

        // Uncompress 
        //$this->uncompress($joomlaFile, $installationDir);
        
        // Setup configuration.php
        $this->setupConfiguration($installationDir);

        // TODO:
        // Bulk SQL
        $this->loadDatabase($installationDir);

        // Move installation directory

        $text = '';

        $output->writeln($text);
    }

    private function download()
    {
        //$uri = 'http://joomlacode.org/gf/download/frsrelease/19524/159413/Joomla_3.3.1-Stable-Full_Package.zip';
        $uri = $this->uri;
        $this->output('Downloading ... ' . $filename);

        $progressBar = new ProgressBar($this->output, 100);
        $progressBar->start();

        $uploadCallback = function ($expected, $total) use ($progressBar) {
            $progressBar->setCurrent((int)100 * ($total / $expected));
        };

        $downloadCallback = function ($expected, $total) use ($progressBar) {
            $progressBar->setCurrent((int)100 * ($total / $expected));
        };

        $progress = new Progress($uploadCallback, $downloadCallback);

        $tmpFile = tempnam(sys_get_temp_dir(), 'joomla-download') . '.zip';

        $client = new Client();
        $client->get($uri, array(
            'subscribers' => array($progress),
            'save_to'     => $tmpFile,
        ));

        $progressBar->finish();

        return $tmpFile;
    }

    private function uncompress($filename, $installationDir)
    {        
        $this->output('Descompressing ... ' . $filename);

        $zippy = Zippy::load();
        $archive = $zippy->open($filename);
        $archive->extract($installationDir);
    }

    private function getInstallationDir($input)
    {
        $installationDir = $input->getOption('install-dir');

        if(!file_exists($installationDir) || !is_dir($installationDir)) {    
            $helper = $this->getHelperSet()->get('question');

            $dirs = array('/var/www', getcwd());
            $question = new Question('Installation directory (must exist): ', false);
            $question->setValidator(function ($answer) {
                if (!file_exists($answer) || !is_dir($answer)) {
                    throw new \RuntimeException(
                        'Directory must exist'
                    );
                }
                return realpath($answer);
            });
            $question->setMaxAttempts(3);
            $question->setAutocompleterValues($dirs);

            $installationDir = $helper->ask($this->input, $this->output, $question);
        }

        return $installationDir;
    }

    private function getDbHost($input)
    {
        $host = $input->getOption('host');

        if(is_null($host)) {    
            $helper = $this->getHelperSet()->get('question');

            $autocompletations = array('localhost', '192.168.1.1');
            $question = new Question('DB IP/Hostname [localhost]: ', 'localhost');
            $question->setValidator(function ($answer) {
                $regexHost = '/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])|(localhost)$/';
                $isHostname = preg_match($regexHost, $answer);
                $isIP = (bool)filter_var($answer, FILTER_VALIDATE_IP);

                if (!$isHostname && !$isIP) {
                    throw new \RuntimeException(
                        'Invalid Hostname/IP'
                    );
                }
                return $answer;
            });
            $question->setMaxAttempts(3);
            $question->setAutocompleterValues($autocompletations);

            $host = $helper->ask($this->input, $this->output, $question);
        }

        return $host;
    }

    private function getUser($input)
    {
        $user = $input->getOption('user');

        if(is_null($user)) {    
            $helper = $this->getHelperSet()->get('question');

            $autocompletations = array('root', 'joomla');
            $question = new Question('DB User Name [root]: ', 'root');
            $question->setValidator(function ($answer) {
                $regexUser = '/^([a-zA-Z][a-zA-Z0-9_\-]{1,14})$/';

                if (!preg_match($regexUser, $answer)) {
                    throw new \RuntimeException(
                        'Invalid Username'
                    );
                }
                return $answer;
            });
            $question->setMaxAttempts(3);
            $question->setAutocompleterValues($autocompletations);

            $user = $helper->ask($this->input, $this->output, $question);
        }

        return $user;
    }

    private function getPassword($input)
    {
        $password = $input->getOption('password');

        if(is_null($password)) {    
            $helper = $this->getHelperSet()->get('question');

            $question = new Question('DB Password: ', null);
            $question->setValidator(function ($answer) {
                $regexPassword = '/^(.{1,})$/';

                if (!preg_match($regexPassword, $answer)) {
                    throw new \RuntimeException(
                        'Invalid Password'
                    );
                }
                return $answer;
            });
            $question->setMaxAttempts(3);
            $question->setHidden(true);
            $question->setHiddenFallback(false);

            $password = $helper->ask($this->input, $this->output, $question);
        }

        return $password;
    }

    private function getDbName($input)
    {
        $db = $input->getOption('db');

        if(is_null($db)) {
            $helper = $this->getHelperSet()->get('question');

            $autocompletations = array('joomla');
            $question = new Question('DB Name [joomla]: ', 'joomla');
            $question->setValidator(function ($answer) {
                $regexUser = '/^([a-zA-Z][a-zA-Z0-9_\-]{1,14})$/';

                if (!preg_match($regexUser, $answer)) {
                    throw new \RuntimeException(
                        'Invalid database name'
                    );
                }
                return $answer;
            });
            $question->setMaxAttempts(3);
            $question->setAutocompleterValues($autocompletations);

            $db = $helper->ask($this->input, $this->output, $question);
        }

        return $db;
    }

    private function getDbPrefix($input)
    {
        $dbprefix = $input->getOption('dbprefix');

        if(is_null($dbprefix)) {
            $helper = $this->getHelperSet()->get('question');

            $autocompletations = array('jos_');
            $question = new Question('DB Prefix [jos_]: ', 'jos_');
            $question->setValidator(function ($answer) {
                $regexUser = '/^([a-zA-Z][a-zA-Z0-9_]{1,14})$/';

                if (!preg_match($regexUser, $answer)) {
                    throw new \RuntimeException(
                        'Invalid database name'
                    );
                }
                return $answer;
            });
            $question->setMaxAttempts(3);
            $question->setAutocompleterValues($autocompletations);

            $dbprefix = $helper->ask($this->input, $this->output, $question);
        }

        return $dbprefix;
    }

    private function setupConfiguration($installationDir)
    {
        $confSource = realpath($installationDir . '/installation/configuration.php-dist');
        $confTarget = $installationDir . '/configuration.php'; 

        copy($confSource, $confTarget);

        $content = file_get_contents($confTarget);

        // FIXME: load configuration previously, other method
        $hostname = $this->getDbHost($this->input);
        $content = preg_replace('/public \$host = \'.*?\';/', 
            'public \$host = \'' . $hostname . '\';', $content);

        $user = $this->getUser($this->input);
        $content = preg_replace('/public \$user = \'.*?\';/', 
            'public \$user = \'' . $user . '\';', $content);

        $password = $this->getPassword($this->input);
        $content = preg_replace('/public \$password = \'.*?\';/', 
            'public \$password = \'' . $password . '\';', $content);

        $db = $this->getDbName($this->input);
        $content = preg_replace('/public \$db = \'.*?\';/',
            'public \$db = \'' . $db . '\';', $content);

        $dbprefix = $this->getDbPrefix($this->input);
        $content = preg_replace('/public \$dbprefix = \'.*?\';/',
            'public \$dbprefix = \'' . $dbprefix . '\';', $content);            

        // Save configuration 
        $this->config = new \stdClass;
        $this->config->host     = $hostname;
        $this->config->user     = $user;
        $this->config->password = $password;
        $this->config->db       = $db;
        $this->config->dbprefix = $dbprefix;        

        file_put_contents($confTarget, $content);
    }

    private function loadDatabase($installationDir)
    {
        // TODO: preguntar por todos los motores de búsqueda soportados
        $sqlFileTemplate = realpath($installationDir . '/installation/sql/mysql/joomla.sql');
        $sql = file_get_contents($sqlFileTemplate);

        $sql = preg_replace('/#__/', $this->config->dbprefix, $sql);
        $sql = preg_replace('/\n\-\-.*?\n/', "\n", $sql);        

        $sentences = explode(';', $sql);

        foreach($sentences as $sentence) {
            $this->executeSql($sentence);
        }

        $this->db = null;
    }

    private function getDb()
    {
        if($this->db === null) {
            $dsn = 'mysql:host=' . $this->config->host . ';dbname=' . $this->config->db;
            $username = $this->config->user;
            $passwd   = $this->config->password;
            $options = array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ); 

            $this->db = new \PDO($dsn, $username, $passwd, $options);
        }
        
        return $this->db;        
    }

    private function executeSql($sql)
    {
        $db = $this->getDb();
        $count = $db->exec($sql);
    }
    
}
