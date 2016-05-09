<?php
/**
 * Pretty Print results
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */
class tdFinePrintTask extends sfBaseTask
{
  private $app = null;
  protected function configure()
  {
    // // add your own arguments here
    $this->addArguments(array(
       new sfCommandArgument('file1', sfCommandArgument::REQUIRED, 'File or dir'),
       new sfCommandArgument('file2', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file3', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file4', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file5', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file6', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file7', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file8', sfCommandArgument::OPTIONAL, 'Another file...'),
       new sfCommandArgument('file9', sfCommandArgument::OPTIONAL, 'Another file...'),
    ));

    $this->addOptions(array(
      new sfCommandOption('w', null, sfCommandOption::PARAMETER_REQUIRED, 'Write back', false),
      new sfCommandOption('l', null, sfCommandOption::PARAMETER_REQUIRED, 'Indent level', 3),
      //new sfCommandOption('file', null, sfCommandOption::PARAMETER_REQUIRED, 'The application to be used', null),
      // add your own options here
    ));

    $this->namespace        = 'tdz';
    $this->name             = 'fine-print';
    $this->briefDescription = '';
    $this->detailedDescription = <<<EOF
The [tdz:fine-print|INFO] task beautifies strings.
Call it with:

  [php symfony tdz:fine-print|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    $f=array();
    foreach($arguments as $k=>$v)
    {
      if($k=='task' || $v=='')continue;
      if(is_dir($v))$v.='.*';
      $f=array_merge(glob($v), $f);
    }
    if(count($f)==0)
      exit("You need to specify at least valid file.\n");

    $a=array();
    foreach($f as $file)
    {
      $fa=sfYaml::load($file);
      if($options['w'])
        file_put_contents($file,sfYaml::dump($fa, $options['l']));
      else if(is_array($fa))
        $a = $fa+$a;
    }
    echo sfYaml::dump($a,$options['l']);
  }
}
