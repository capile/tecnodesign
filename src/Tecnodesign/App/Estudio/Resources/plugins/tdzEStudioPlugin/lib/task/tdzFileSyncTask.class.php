<?php
/**
 * Pindorama migration
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tecnodzPindoramamigrationTask.class.php 494 2010-10-04 13:27:32Z capile $
 */
class tdzFileSyncTask extends sfBaseTask
{
  private $app = null;
  protected function configure()
  {
    // // add your own arguments here
    $this->addArguments(array(
    //   new sfCommandArgument('id', sfCommandArgument::OPTIONAL, 'Document ID'),
    //   new sfCommandArgument('level', sfCommandArgument::OPTIONAL, 'Levels of recursion'),
    ));

    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application to be used', null),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('url', null, sfCommandOption::PARAMETER_OPTIONAL, 'Initial folder', ''),
      new sfCommandOption('limit', null, sfCommandOption::PARAMETER_OPTIONAL, 'Files to parse', '100'),
      new sfCommandOption('clear', null, sfCommandOption::PARAMETER_OPTIONAL, 'Remove existing files', false),
      new sfCommandOption('ask', null, sfCommandOption::PARAMETER_OPTIONAL, 'Ask for confirmation', false),
      // add your own options here
    ));

    $this->namespace        = 'tdz';
    $this->name             = 'file-sync';
    $this->briefDescription = '';
    $this->detailedDescription = <<<EOF
The [tdz:file-sync|INFO] task searches the web folders for files and syncs them with e-Studio pages.
Call it with:

  [php symfony tdz:file-sync|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    if(is_null($this->app))
    {
      if($options['application']!='')$this->app=$options['application'];
      else
      {
        $app = glob(sfConfig::get('sf_apps_dir').'/*');
        $this->app = basename(array_shift($app));
      }
    }
    // initialize the database connection
    $configuration = ProjectConfiguration::getApplicationConfiguration($this->app, $options['env'], true);
    sfContext::createInstance($configuration);

    $this->updateFiles(array(),$options);
    //tdz::debug($arguments, $options, sfConfig::getAll(), $this->app);
  }
  public function updateFiles($files=array(), $options=array())
  {
    $limit=500;
    if(isset($options['limit']) && is_numeric($options['limit']))$limit=(int)$options['limit'];
    $ask=false;
    if(isset($options['ask']))$ask=(bool)$options['ask'];
    $quiet=true;
    $verbose=false;
    if(isset($options['quiet']))$quiet=(bool)$options['quiet'];
    $root=sfConfig::get('app_e-studio_document_root');
    $exts = sfConfig::get('app_e-studio_auto_update_extensions');
    $formats = sfConfig::get('app_e-studio_auto_update_formats');
    if($options['url']) {
        $files=array_merge($files, preg_split('/\s*\,\s*/', $options['url'], null, PREG_SPLIT_NO_EMPTY));
    }
    if(count($files)==0)$files[]='';
    $proc = array();//tdzEntries::getFiles();
    $skip = sfConfig::get('app_e-studio_auto_update_skip');
    foreach($skip as $file)
      $proc[$file]=$file;

    $procdirs=array();
    set_time_limit(0);
    $request=sfContext::getInstance()->getRequest();
    $finfo=false;
    $assets = '/_assets/e-studio';//sfConfig::get('app_e-studio_assets_prefix');
    $cbase=(is_dir($options['clear']))?($options['clear']):(sfConfig::get('sf_data_dir').'/e-studio/www-old');
    while(count($files) > 0)
    {
      $link = array_shift($files);
      $file = $root.$link;
      $name = basename($file);
      if(isset($proc[$link]) || substr($name,0,1)=='.' || substr($link.'/',0,strlen($assets))==$assets)continue;
      if(is_dir($file))
      {
        $procdirs[$link]=$file;
        $glob = $file;
        $glob .= (substr($glob, -1)=='/')?('*'):('/*');
        $new_files = glob($glob);
        if(!$quiet)tdz::debug("parsing dir: $file... found ".count($new_files).' files',false);
        $proc[$link]=$file;
        foreach($new_files as $nf) {
          $nf = substr($nf,strlen($root));
          if(!isset($proc[$nf])) {
              $files[]=$nf;
          }
        }
      }
      else if(file_exists($file)) {
        $update = true;
        if($verbose)tdz::debug(" $file",false);
        //tdz::debug("file: $file\n",false);
        $ext = preg_replace('/.*\.([^\.]*)$/','$1',$name);
        if(!file_exists($file) || (isset($exts[$ext]) && !$exts[$ext]) || !$exts['*'] || isset($proc[$link])) $update=false;

        if($update)
        {
          if($ext != '' && $request->getMimeType($ext))
            $format = $request->getMimeType($ext);
          else if(class_exists('finfo'))
          {
            if(!$finfo) $finfo=new finfo(FILEINFO_MIME_TYPE);
             $format = $finfo->file($file);
          }
          else if(function_exists('mime_content_type'))
             $format = mime_content_type($file);
          if((isset($formats[$format]) && !$formats[$format]) || !$formats['*']) $update=false;
        }

        $mod = date('Y-m-d H:i:s',filemtime($file));

        $proc[$link]=$link;
        //tdz::debug("$file\n$link\n$format\n$ext",$exts);
        $dest = sfConfig::get('app_e-studio_upload_dir').'/'.md5($link);
        if($ext!='')$dest .= '.'.$ext;
        $source = $link;
        $clear=false;
        if($update && copy($file, $dest))
        {
          $source = basename($dest);
          $clear=true;
        }

        $f=false;
        $f=tdzEntries::match($link, true);
        if($f)
        {
          $emod=strtotime($f->updated);
          $clear = true;
          if($emod > $mod) $update=false;
        }
        else if ($update)
          $f = new tdzEntries();

        if($clear && $options['clear'])
        {
          $cdest=$cbase.$link;
          if(!is_dir(dirname($cdest))) @mkdir(dirname($cdest), 0777, true);
          rename($file, $cdest);
        }

        if($update)
        {
          $limit--;
          $f->title=$name;
          $f->link=$link;
          $f->type='file';
          $f->format=$format;
          $f->published=date('Y-m-d H:i:s');
          $f->source=$source;
          $f->save();
          if(!$quiet)tdz::debug("added $link ($limit left)",false);
        }
        if($f)
        {
          $f->free();
          unset($f);
        }

        if(!$limit) break;
      }
    }

    foreach($procdirs as $link=>$file)
    {
      if(is_dir($file) && $options['clear'] && is_dir($cbase.$link) && count(glob("{$file}/*"))==0)
      {
        if(@rmdir($file) && $verbose)tdz::debug(" removed dir $link",false);
      }
    }
  }

}
