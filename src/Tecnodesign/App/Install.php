<?php
/**
 * Tecnodesign Installer
 * 
 * This package implements a installer for the Tecnodesign framework
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
class Tecnodesign_App_Install
{
    protected 
        $project,
        $env,
        $errors=array(),
        $cfgFile, 
        $appsDir,
        $root='.',
        $configDir='config',
        $cacheDir='cache',
        $dataDir='data',
        $logDir='log',
        $tplDir='template',
        $documentRoot='www',
        $skel=array()
        ;

    public static 
        $perms=array(
            'cache-dir'=>0777,
            'data-dir'=>0777,
            'log-dir'=>0777,
        ), 
        $modules = array(
            'database'=>'Database connection', 
            'studio'=>'Studio CMS',
        );
    
    public function __construct($config, $env='prod')
    {
        $app = (tdz::getApp()) ?(tdz::getApp()->tecnodesign) :[];
        $customCfg = (TDZ_APP_ROOT!=TDZ_ROOT);
        if($customCfg && $app['apps-dir']!=realpath($this->root)) $this->root = $app['apps-dir'];
        $this->env='all';
        $this->project = $config;
        $this->cfgFile = ($customCfg && isset($app['config-dir'])) ?$app['config-dir'].'/'.$this->project.'.yml' :tdz::relativePath($this->root.'/'.$this->project.'.yml');
        if(file_exists($this->cfgFile)) {
            $cfg = Tecnodesign_Yaml::load($this->cfgFile);
            if($this->env!='all') {
                if(!isset($cfg[$this->env])) $cfg[$this->env] = array();
                foreach($P as $k=>$v) {
                    if(isset($cfg['all'][$v]) && !isset($cfg[$this->env][$v]))
                        $cfg[$this->env][$v] = $cfg['all'][$v];
                    unset($k, $v);
                }

            }
            if(isset($cfg[$this->env]['tecnodesign']['apps-dir']     )) $this->appsDir     = $cfg[$this->env]['tecnodesign']['apps-dir'];     
            if(isset($cfg[$this->env]['tecnodesign']['config-dir']   )) $this->configDir   = $cfg[$this->env]['tecnodesign']['config-dir'];    
            if(isset($cfg[$this->env]['tecnodesign']['cache-dir']    )) $this->cacheDir    = $cfg[$this->env]['tecnodesign']['cache-dir'];    
            if(isset($cfg[$this->env]['tecnodesign']['data-dir']     )) $this->dataDir     = $cfg[$this->env]['tecnodesign']['data-dir'];     
            if(isset($cfg[$this->env]['tecnodesign']['log-dir']      )) $this->logDir      = $cfg[$this->env]['tecnodesign']['log-dir'];      
            if(isset($cfg[$this->env]['tecnodesign']['templates-dir'])) $this->tplDir      = $cfg[$this->env]['tecnodesign']['templates-dir'];
            if(isset($cfg[$this->env]['tecnodesign']['document-root'])) $this->documentRoot= $cfg[$this->env]['tecnodesign']['document-root'];
        }
        $this->env = null;
    }

    public static function config()
    {
        $project = null;
        $modules = [];
        $apps = [];
        $args = Tecnodesign_App::request('argv');
        if($args) {
            foreach($args as $i=>$a) {
                if(substr($a, 0, 9)==='--enable-' && strlen($a)>9) $apps[substr($a, 9)]=true;
                else if(substr($a, 0, 10)==='--disable-' && strlen($a)>10) $apps[substr($a, 10)]=false;
                else continue;
                unset($args[$i], $a, $i);
            }
        }
        if($args) {
            $project = array_pop($args);
            while($module=array_shift($args)) {
                if(!isset(static::$modules[$module])) {
                    tdz::debug("There's no {$module} module.\n");
                }
                $modules[] = $module;
            }
        }

        if($project) {
            $app = new Tecnodesign_App_Install($project);
        } else if(!$apps) {
            tdz::debug("You have to specify the application name to configure. For example: \n    \$ php ".implode(' ', $_SERVER['argv'])." projectname\n");
        }

        if($project && !$apps) {
            if($modules) {
                foreach($modules as $module) {
                    if(method_exists($app, $fn=$module.'Install')) {
                        $app->$fn();
                    }   
                }
            } else if($project) {
                $app->runInstall();
            }
        }

        if($apps) {
            $cfgSrc = [TDZ_APP_ROOT.'/data/config'];
            $cfgRoot = TDZ_APP_ROOT.'/data/config';
            if(isset($app)) {
                $cfgRoot = $app->configDir;
                $cwd = getcwd();
                $sep='/';
                $vendor="{$sep}vendor{$sep}capile{$sep}tecnodesign";
                if(($p=strpos($cwd, $vendor)) && substr($cwd, $p)==$vendor) {
                    chdir($nd=substr($cwd, 0, $p));
                    unset($nd);
                }
                array_unshift($cfgSrc, $cfgRoot);
            }

            foreach($apps as $a=>$e) {
                $f=$cfgRoot.'/'.$a.'.yml';
                if(!$e && file_exists($f)) {
                    unlink($f);
                    echo "> {$a} disabled\n";
                } else if($e && !file_exists($f)) {
                    foreach($cfgSrc as $d) {
                        if(file_exists($s=$d.'/'.$a.'.yml-example')) {
                            copy($s, $f);
                            echo "> {$a} enabled\n";
                            break;
                        }
                    }
                }
            }
        }
    }

    public function checkSkel()
    {
        foreach($this->skel as $dir=>$umask) {
            if(substr($dir,0,1)!='/') {
                $dir = $this->root.'/'.$dir;
            }
            if(file_exists($dir)) {
                @chmod ($dir, $umask);
            } else if(!mkdir($dir, $umask, true)) {
                $this->errors[] = "Could not create dir {$dir}";
            }
        }
    }

    public function getEnv()
    {
        if(!$this->env) {
            $b0 = "\033[1m";
            $b1 = "\033[0m";
            $this->env = tdz::ask("{$b0}What's the current environment you'd like to configure?{$b1}", 'all', array('all', 'dev', 'prod', 'test'));
            if(!in_array($this->env, array('all', 'dev', 'prod', 'test'))) {
                exit("\n{$b0}ERROR:{$b1} That environment is now allowed: {$this->env}\n");
            } else {
                echo '> '.$this->env;
            }
        }
        return $this->env;

    }
    
    public function runInstall()
    {
        $b0 = "\033[1m";
        $b1 = "\033[0m";
        $this->getEnv();
        $cwd = getcwd();
        $sep='/';
        $vendor="{$sep}vendor{$sep}capile{$sep}tecnodesign";
        if(($p=strpos($cwd, $vendor)) && substr($cwd, $p)==$vendor) {
            chdir($nd=substr($cwd, 0, $p));
            if($this->appsDir==$cwd) {
                $this->appsDir = $nd;
            }
            $cwd = getcwd();
            unset($nd);
        }
        if(!$this->cfgFile) $this->cfgFile = tdz::relativePath($this->root.'/config/'.$this->project.'.yml');
        echo '> '.($this->cfgFile = tdz::ask("\n{$b0}Where should the configuration file be created?{$b1} ", $this->cfgFile));
        $buildConfig = true;
        $P = array('appsDir'=>'apps-dir', 'configDir'=>'config-dir', 'cacheDir'=>'cache-dir', 'dataDir'=>'data-dir', 'logDir'=>'log-dir', 'tplDir'=>'templates-dir', 'documentRoot'=>'document-root');
        if(file_exists($this->cfgFile)) {
            $cfg = Tecnodesign_Yaml::load($this->cfgFile);
            if($this->env!='all') {
                if(!isset($cfg[$this->env])) $cfg[$this->env] = array();
                foreach($P as $k=>$v) {
                    if(isset($cfg['all'][$v]) && !isset($cfg[$this->env][$v]))
                        $cfg[$this->env][$v] = $cfg['all'][$v];
                    unset($k, $v);
                }

            }
            if(isset($cfg[$this->env]['tecnodesign']['apps-dir']     )) $this->appsDir     = $cfg[$this->env]['tecnodesign']['apps-dir'];     
            if(isset($cfg[$this->env]['tecnodesign']['config-dir']   )) $this->configDir   = $cfg[$this->env]['tecnodesign']['config-dir'];    
            if(isset($cfg[$this->env]['tecnodesign']['cache-dir']    )) $this->cacheDir    = $cfg[$this->env]['tecnodesign']['cache-dir'];    
            if(isset($cfg[$this->env]['tecnodesign']['data-dir']     )) $this->dataDir     = $cfg[$this->env]['tecnodesign']['data-dir'];     
            if(isset($cfg[$this->env]['tecnodesign']['log-dir']      )) $this->logDir      = $cfg[$this->env]['tecnodesign']['log-dir'];      
            if(isset($cfg[$this->env]['tecnodesign']['templates-dir'])) $this->tplDir      = $cfg[$this->env]['tecnodesign']['templates-dir'];
            if(isset($cfg[$this->env]['tecnodesign']['document-root'])) $this->documentRoot= $cfg[$this->env]['tecnodesign']['document-root'];
            if(strtolower(tdz::ask("\n{$b0}Should the configuration file be updated?{$b1} [y/N]"))!='y') {
                $buildConfig = false;
                echo "> No\n";
            } else {
                echo "> Yes\n";
            }
        } else {
            $cfg = array($this->env=>array('tecnodesign'=>array('addons'=>array())));
        }

        if($buildConfig) {
            if(!$this->appsDir) $this->appsDir = $this->root;
            $this->appsDir  = tdz::ask("\n{$b0}Application root{$b1}\nThis will be the base folder for all relative paths. ", $this->appsDir);
            echo '> ', realpath($this->appsDir);
            $this->configDir  = tdz::ask("\n{$b0}Load additional configuration files{$b1}\nAdditional configuration files should be loaded from this folder (optional). ", $this->configDir);
            echo '> ', realpath($this->configDir);
            $this->cacheDir = tdz::ask("\n{$b0}Cache dir{$b1}\nWhere temporary files should be stored. ", $this->cacheDir);
            echo '> ', $this->cacheDir;
            $this->dataDir  = tdz::ask("\n{$b0}Data dir{$b1}\nWhere other writable data should be written. ", $this->dataDir);
            echo '> ', $this->dataDir;
            $this->logDir   = tdz::ask("\n{$b0}Log dir{$b1}\nWhere error logs should be written. ", $this->logDir);
            echo '> ', $this->logDir;
            $this->tplDir   = tdz::ask("\n{$b0}Template dir{$b1}\nWhere presentation templates and layouts should be written. ", $this->tplDir);
            echo '> ', $this->tplDir;
            $this->documentRoot = tdz::ask("\n{$b0}Document root{$b1}\nWhere static website files are located. ", $this->documentRoot);
            echo '> ', $this->documentRoot;
            $config = array('tecnodesign'=>array(
                'apps-dir'=>$this->appsDir,
                'config-dir'=>$this->configDir,
                'cache-dir'=>$this->cacheDir,
                'data-dir'=>$this->dataDir,
                'log-dir'=>$this->logDir,
                'templates-dir'=>$this->tplDir,
                'document-root'=>$this->documentRoot,
            ));
            if(!$this->configDir) {
                unset($config['tecnodesign']['config-dir']);
            } else {
                $config = ['include'=>$this->configDir.'/*.yml'] + $config;
            }
            if(strtolower(tdz::ask("\n{$b0}Is this configuration correct? All non-existing directories will be created.{$b1}\n".Tecnodesign_Yaml::dump($config, 2)."[y/N]"))!='y') {
                echo "> No\n";
                return $this->runInstall();
            } else {
                echo "> Yes\n";
            }
            foreach($config['tecnodesign'] as $k=>$v) {
                if($k!='apps-dir') {
                    $config['tecnodesign'][$k] = tdz::relativePath($v, $this->appsDir.'/');
                    $v = (substr($v, 0, 1)!='/')?($this->appsDir.'/'.$v):($v);
                } else {
                    $config['tecnodesign'][$k] = realpath($v);
                }
                if(!is_dir($v)) {
                    if(!mkdir($v, (isset(self::$perms[$k]))?(self::$perms[$k]):(0755), true)) {
                        echo "\n{$b0}ERROR:{$b1} It wasn't possible to create the directory {$v}, please adjust its permissions or configure another location.\n";
                        return $this->runInstall();
                    } else {
                        echo "    + {$v}\n";
                    }
                }
            }
            $cfg[$this->env]['tecnodesign'] = $config['tecnodesign'] + $cfg[$this->env]['tecnodesign'];
            if(isset($config['include'])) {
                unset($config['tecnodesign']);
                $cfg[$this->env] = $config + $cfg[$this->env];
            }
            if(tdz::save($this->cfgFile, Tecnodesign_Yaml::dump($cfg, 2), true)) {
                echo "    + {$this->cfgFile}\n";
            } else {
                echo "\n{$b0}ERROR:{$b1} Could not save file: {$this->cfgFile}\n";
            }
        }
        if($this->logDir) tdz::$logDir = $this->logDir;
        $this->skel += array(
            $this->appsDir => 0755,
            $this->configDir => 0755,
            $this->dataDir => 0777,
            $this->cacheDir => 0777,
            $this->logDir => 0755,
            $this->tplDir => 0755,
            $this->documentRoot => 0755,
        );
        $this->checkSkel();

        $this->cfgFile = realpath($this->cfgFile);
        $this->appsDir = realpath($this->appsDir);
        $cwd = getcwd();
        chdir($this->appsDir);
        $this->documentRoot = realpath($this->documentRoot);
        $env = ($this->env=='all')?('dev'):($this->env);

        if(strtolower(tdz::ask("\n{$b0}Should an application file be created at the document root?{$b1} [y/N]"))=='y') {
            echo '> Yes';
            $appfile = $this->documentRoot.'/'.basename(tdz::ask("\n{$b0}Enter the file name to be created at {$this->documentRoot}{$b1}\n", $this->project.'.php'));
            echo "> {$appfile}\n";
            $tdzf = file_exists($c=$cwd.'/vendor/autoload.php') 
                  ?tdz::relativePath($c, $appfile) 
                  :tdz::relativePath(TDZ_ROOT.'/tdz.php', $appfile);
            $cfgf = tdz::relativePath($this->cfgFile, $appfile);
            if(!file_exists($appfile)) {
                $app = '<';
                $app .= <<<FIM
?php
require '{$tdzf}';
tdz::app('{$cfgf}', '{$this->project}', '{$env}')->run();
FIM;
                if(tdz::save($appfile, $app)) {
                    echo "    + {$appfile}\n";
                } else {
                    echo "\n{$b0}ERROR:{$b1} Could not save file: {$appfile}\n";
                }
            } else {
                echo "\n{$b0}ERROR:{$b1} File already exists: {$appfile}\n";
            }
        } else {
            echo '> No';
        }

        if(strtolower(tdz::ask("\n{$b0}Should a command-line file be created?{$b1} [y/N]"))=='y') {
            echo '> Yes';
            $cmdfile = $cwd.'/'.$this->project;
            $cmdfile = tdz::ask("\n{$b0}Enter the file name{$b1}\n", $cmdfile);
            echo '> ', $cmdfile, "\n";
            $tdzf = file_exists($c=$cwd.'/vendor/autoload.php') 
                  ?tdz::relativePath($c, $cmdfile) 
                  :tdz::relativePath(TDZ_ROOT.'/tdz.php', $cmdfile);
            $cfgf = tdz::relativePath($this->cfgFile, $cmdfile);
            if(!file_exists($cmdfile)) {
                $cmd = "#!/usr/bin/env php\n<";
                $cmd .= <<<FIM
?php
define('TDZ_CLI', true);
require '{$tdzf}';
tdz::app('{$cfgf}', '{$this->project}', '{$env}')->run();
FIM;
                if(tdz::save($cmdfile, $cmd, true, 0755)) {
                    echo "    + {$cmdfile}\n";
                } else {
                    echo "\n{$b0}ERROR:{$b1} Could not save file: {$cmdfile}\n";
                }
            } else {
                echo "\n{$b0}ERROR:{$b1} File already exists: {$cmdfile}\n";
            }
        } else {
            echo '> No';
        }
        foreach(self::$modules as $mod=>$name) {
            if(strtolower(tdz::ask("\nWould you like to configure {$b0}{$name}{$b1}? [y/N]"))=='y') {
                echo "> Yes\n";
                $mod .= 'Install';
                $this->$mod();
            } else {
                echo "> No\n";
            }
        }

        echo "Installation successfull!\n";
    }

    protected $db=array();
    public function databaseInstall()
    {
        $b0 = "\033[1m";
        $b1 = "\033[0m";
        $fn = 'databases.yml';
        $f  = dirname($this->cfgFile).'/'.$fn;

        $cfg = (file_exists($f))?(Tecnodesign_Yaml::load($f)):(array());
        $this->getEnv();

        $appRoot = (isset($this->appsDir)) ?$this->appsDir :TDZ_APP_ROOT;

        if(!isset($cfg[$this->env])) {
            $c = tdz::ask("There's no database connnection configured. Type the name of the connection you'd like to configure.");
        } else {
            $c = tdz::ask("You already have ".count($cfg[$this->env])." configured databases. Type the name of the connection you'd like to configure (".implode(', ', array_keys($cfg[$this->env]))." or type a new name):");
            if(isset($cfg[$this->env][$c])) $this->db[$c] = $cfg[$this->env][$c];
        }
        echo '> ', $c, "\n";
        $r=null;
        if(!isset($this->db[$c]) || ($r=strtolower(tdz::ask("It looks like it's configured. Would you like to update the configuration? [y/N]")))=='y') {
            if($r) echo "> Yes\n";
            $type = (isset($this->db[$c]['dsn']) && strpos($this->db[$c]['dsn'], ':'))?(substr($this->db[$c]['dsn'], 0, strpos($this->db[$c]['dsn'], ':'))):('');
            $type = tdz::ask("What's the database type?", null, array('dblib', 'mysql', 'pgsql', 'sqlite'));
            if($type=='sqlite') {
                $dbf = tdz::ask("Where should it be located (relative to the application root: {$appRoot})?");
                $this->db[$c]['dsn'] = $type.':'.$dbf;
                if(getcwd()!=$appRoot && substr($dbf, 0, 1)!='/') $dbf = $appRoot.'/'.$dbf;
            } else {
                $this->db[$c]['dsn'] = $type.':'
                    . 'host='.tdz::ask("What is the database hostname?", 'localhost').';'
                    . 'dbname='.tdz::ask("What is the database name?")
                    . ((strtolower(tdz::ask("Would you like to force UTF-8 encoding in this connection? [y/N]"))=='y')?(';charset=UTF-8'):(''))
                    ;
                $this->db[$c]['username'] = tdz::ask("What is the database username?");
                $this->db[$c]['password'] = tdz::ask("What is the username password?");
            }
            $this->db[$c]['sync'] = (strtolower(tdz::ask("Should the tables within this database be synchronized with framework models? [y/N]"))=='y');
            if(strtolower(tdz::ask("\n{$b0}Is this configuration correct?{$b1}\n".Tecnodesign_Yaml::dump($this->db[$c], 2)."[y/N]"))!='y') {
                echo "> No\n";
                return $this->databaseInstall();
            } else {
                echo "> Yes\n";
            }
            if($type=='sqlite' && !file_exists(dirname($dbf))) {
                mkdir(dirname($dbf), 0777, true);
            }
            $cfg[$this->env][$c] = $this->db[$c];
            tdz::$database = $cfg[$this->env];
            if(tdz::save($f, Tecnodesign_Yaml::dump($cfg, 2), true)) {
                echo "    + {$f}\n";
            } else {
                echo "\n{$b0}ERROR:{$b1} Could not save file: {$f}\n";
            }
            $r='';
            if(!file_exists($if=dirname($f).'/.gitignore') || strpos($r=file_get_contents($if), "\n{$fn}")===false) {
                $r .= "\n{$fn}";
                if(tdz::save($if, $r, true)) {
                    echo "    + {$if}\n";
                } else {
                    echo "\n{$b0}ERROR:{$b1} Could not save file: {$if}\n";
                }
            } 
        }

        if(strtolower(tdz::ask("Would you like to configure another database connection? [y/N]"))=='y') {
            echo "> Yes\n";
            return $this->databaseInstall();
        }
        echo "> No\n";
    }

    public function studioInstall()
    {
        $b0 = "\033[1m";
        $b1 = "\033[0m";
        $cfg = (file_exists($this->cfgFile)) ?Tecnodesign_Yaml::load($this->cfgFile) :[];
        $this->getEnv();
        $install = false;
        $upgrade = false;
        if(!isset($cfg[$this->env]['studio']['version'])) {
            $cfg[$this->env]['studio']['version'] = Tecnodesign_Studio::VERSION;
            $install = true;
        } else if($cfg[$this->env]['studio']['version']<Tecnodesign_Studio::VERSION) {
            // upgrade
            $upgrade = (float)$cfg[$this->env]['studio']['version'];
        }

        // choose connection
        $dd = (isset($cfg[$this->env]['studio']['database']))?($cfg[$this->env]['studio']['database']):(3);
        $do = array(1=>'bundled', 2=>'return to database configuration', 3=>'No database');
        if(Tecnodesign_Query::database()) {
            foreach(array_keys(tdz::$database) as $k) {
                $do[] = $k;
                unset($k);
            }
        }
        $q = "Studio may use a working database connection. Which connection would you like to use:\n";
        foreach($do as $k=>$v) {
            if($dd && !is_int($dd) && $dd==$v) {
                $dd=$k;
                $q .= "  {$k}) {$v} [Enter]\n";
            } else {
                $q .= "  {$k}) {$v}\n";
            }
        }
        $d = tdz::ask($q, $dd, array_keys($do));
        if($d==1) {
            $dsn = 'sqlite:'.tdz::ask("Where should it be located (relative to {$this->appsDir})?");
            $cfg[$this->env]['studio']['connection']='studio';
            $afn = 'databases.yml';
            $af  = dirname($this->cfgFile).'/'.$afn;
            $a = (file_exists($af))?(Tecnodesign_Yaml::load($af)):(array());
            $a[$this->env]['studio']=array('dsn'=>$dsn, 'sync'=>false);
        } else if($d==2) {
            return $this->databaseInstall();
        } else if($d==3) {
            $cfg[$this->env]['studio']['connection']=null;
        } else {
            $cfg[$this->env]['studio']['connection']=$do[$d];
        }

        // set routes
        if(isset($cfg[$this->env]['tecnodesign']['controller-options']['class']) && $cfg[$this->env]['tecnodesign']['controller-options']['class']!='Tecnodesign_Studio') {
            $cfg[$this->env]['tecnodesign']['routes']['/*']=array(
                'class'=>'Tecnodesign_Studio',
                'method'=>'run',
                'additional-params'=>'true',
            );
        } else {
            $cfg[$this->env]['tecnodesign']['controller-options'] = [
                'class'  => 'Tecnodesign_Studio',
                'static' => true,
                'additional-params' => true,
                'layout' => 'layout',
            ];
            $cfg[$this->env]['tecnodesign']['default-route'] = 'run';
        }
        if(tdz::save($this->cfgFile, Tecnodesign_Yaml::dump($cfg, 2), true)) {
            echo "    + {$this->cfgFile}\n";
        } else {
            echo "\n{$b0}ERROR:{$b1} Could not save file: {$this->cfgFile}\n";
        }

        // install or upgrade tables
        tdz::app($this->cfgFile, $this->project, tdz::env());

        if($cfg[$this->env]['studio']['connection']) {
            $install = true;
            $b=glob(TDZ_ROOT.'/src/Tecnodesign/Resources/studio/studio-*.php');
            foreach($b as $i=>$f) {
                $run=$install;
                if(!$run && $upgrade) {
                    if(preg_match('/\-([0-9\.]+)\-[0-9]+\.php$/', $f, $m)) {
                        $v = (float) $m[1];
                        $run = ($v>$upgrade);
                    }
                }
                if($run) {
                    echo "    > ".basename($f, '.php')."\n";
                    tdz::exec(array('script'=>$f));
                }
                unset($b[$i], $i, $f);
            }
        }

        $co = array(1=>false);
        $q = "Would you like to load a sample content? Please select one of the options available:\n  1) No thanks\n";
        $i=2;
        foreach(glob(TDZ_ROOT.'/src/Tecnodesign/Resources/studio/*.yml') as $f){
            $q .= "  {$i}) (template) ".basename($f, '.yml')."\n";
            $co[$i] = $f;
            $i++;
        }
        if(is_dir(S_VAR.'/studio/data/')) {
            foreach(glob(S_VAR.'/studio/data/*.yml') as $f){
                $q .= "  {$i}) ".basename($f)."\n";
                $co[$i] = $f;
                $i++;
            }
        }
        if(($d=tdz::ask($q, 1, array_keys($co))) && $d>1) {
            if($cfg[$this->env]['studio']['connection']) {

                if($cfg[$this->env]['studio']['connection']!='studio') tdz::$database['studio'] = tdz::$database[$cfg[$this->env]['studio']['connection']];

                echo "    > ".$co[$d]."\n";
                Tecnodesign_Database::import($co[$d]);
            } else {
                // make this as easy as importing yml files
                $f=substr($co[$d], 0, strlen($co[$d])-4);
                $d =realpath($this->appsDir.'/'.$this->dataDir).'/'.tdzEntry::$pageDir;
                if(!is_dir($d)) {
                    if(!mkdir($d, 0777, true)) {
                        echo "\n{$b0}ERROR:{$b1} It wasn't possible to create the directory {$d}, please adjust its permissions or configure another location.\n";
                    } else {
                        echo "    + mkdir {$d}\n";
                    }
                }
                $cmd = "cp -r '{$f}/.' '{$d}/.'";
                echo "    > ".$cmd."\n";
                passthru($cmd);
            }
        }

        $d =realpath($this->appsDir.'/'.$this->dataDir).'/'.tdzEntry::$pageDir.'/docs';
        if(!file_exists($d) && strtolower(tdz::ask("Would you like to load the documentation at /docs? [y/N]"))=='y') {
            $b=realpath(TDZ_ROOT.'/docs');
            $cmd = "ln -s  '{$b}' '{$d}'";
            echo "    > ".$cmd."\n";
            passthru($cmd);
        }

        if($cfg[$this->env]['studio']['connection'] && strtolower(tdz::ask("Would you like to use Studio database for authentication? [y/N]"))=='y') {
            $cid = tdz::ask("What'll be the cookie name used for authentication?", (isset($cfg[$this->env]['user']['session-name']))?($cfg[$this->env]['user']['session-name']):('tdz'));
            if(!isset($cfg[$this->env]['user'])) $cfg[$this->env]['user']=array();
            $cfg[$this->env]['user'] += array(
                'session-name'=>$cid,
                'ns'=>array(),
            );
            $cfg[$this->env]['user']['ns']['e']=array(
                'name'=> 'Studio',
                'enabled'=>true,
                'cookie'=>$cid,
                'timeout'=>1296000,
                'finder'=>'Tecnodesign_Studio_User',
                'storage'=>'disk',
                'properties'=>array(
                  'id'=>'id',
                  'sid'=>'login',
                  'name'=>'name',
                  'password'=>'password',
                  'email'=>'email',
                  'lastAccess'=>'accessed',
                  'credentials'=>'Credentials',
                ),
            );

            if(tdz::save($this->cfgFile, Tecnodesign_Yaml::dump($cfg, 2), true)) {
                echo "    + {$this->cfgFile}\n";
            } else {
                echo "\n{$b0}ERROR:{$b1} Could not save file: {$this->cfgFile}\n";
            }
            if(isset($a)) {
                if(tdz::save($af, Tecnodesign_Yaml::dump($a, 2), true)) {
                    echo "    + {$af}\n";
                } else {
                    echo "\n{$b0}ERROR:{$b1} Could not save file: {$af}\n";
                }
                $r='';
                if(!file_exists($if=dirname($af).'/.gitignore') || strpos($r=file_get_contents($if), "\n{$afn}")===false) {
                    $r .= "\n{$afn}";
                    if(tdz::save($if, $r, true)) {
                        echo "    + {$if}\n";
                    } else {
                        echo "\n{$b0}ERROR:{$b1} Could not save file: {$if}\n";
                    }
                } 
            }
        }

        echo "Studio CMS successfullly configured!\n";


    }

    public function run()
    {
    }
}