<?php
/**
 * PhpStorm.
 * User: Jay
 * Date: 2018/5/2
 */
namespace PHPZlc\MdDoc\MdDocBundle\Command;

use App\Document\Config;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Connection;
use Github\Client;
use PHPZlc\Document\Document;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcher;

class GenerateCommand extends Base
{
    /**
     * @var Connection|null
     */
    private $connection;

    /**
     * @var Filesystem;
     */
    private $fileSystem;

    public function __construct(Connection $connection = null)
    {
        parent::__construct();
        $this->connection = $connection;
        $this->fileSystem = new Filesystem();
    }

    public function configure()
    {
        $this
            ->setName($this->command_pre . 'generate:mddoc')
            ->setDescription($this->description_pre . 'github markdown repository generates multi-version documents');
        ;
    }

    public function getRootPath()
    {
        return parent::getRootPath() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'mddoc-repo';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if($this->fileSystem->exists($this->getRootPath())) {
            $this->fileSystem->remove($this->getRootPath());
        }

        $this->fileSystem->mkdir($this->getRootPath());

        $username = 'phpzlc';
        $repository = 'phpzlc-doc';
        $ssh_uri = 'git@github.com:'. $username . '/' . $repository . '.git';

        $client = new Client();
        $branches = $client->api('repo')->branches($username, $repository);
        $brancheNames = [];

        $command = 'cd ' . $this->getRootPath() . ";";
        foreach($branches as $branch){
            $brancheName = $branch['name'];
            if(strpos($brancheName,'-') === false || strpos($brancheName,'dev') === false){
                $brancheNames[] = $brancheName;
                $command .= 'git clone -b ' . $brancheName . ' ' . $ssh_uri . ' ' . $brancheName . ';';
            }
        }

        system($command);

        //生成数据缓存
        $this->fileSystem->appendToFile($this->getRootPath() . DIRECTORY_SEPARATOR . 'branch.json', json_encode($brancheNames));

        $files = [];
        foreach($brancheNames as $brancheName){
            $this->barnchParsing($files, $brancheName);
        }
        $this->fileSystem->appendToFile($this->getRootPath() . DIRECTORY_SEPARATOR . 'files.json', json_encode($files));

        return 0;
    }

    public function barnchParsing(&$files, $branch, $path = '')
    {
        if(empty($path)) {
            $path = $branch;
        }

        $scandir = @scandir($this->getRootPath() . DIRECTORY_SEPARATOR . $path);

        foreach ($scandir as $file_name){
            if(strpos($file_name, '.') !== 0) {
                $file_path = $path . DIRECTORY_SEPARATOR . $file_name;
                if(is_dir($this->getRootPath() . DIRECTORY_SEPARATOR . $file_path)){
                    $this->barnchParsing($files, $branch, $file_path);
                }else{
                    $key = ltrim($file_path, $branch . DIRECTORY_SEPARATOR);
                    $files[$key]['branchs'][] = $branch;
                    $files[$key][$branch] = array(
                        'path' => $this->getRootPath() . DIRECTORY_SEPARATOR . $file_path
                    );
                }
            }
        }
    }
}

