<?php

/**
 * PlugintdzEntries form.
 *
 * @package    ##PROJECT_NAME##
 * @subpackage form
 * @author     ##AUTHOR_NAME##
 * @version    SVN: $Id: PlugintdzEntriesForm.class.php 1243 2013-07-20 21:48:01Z capile $
 */
abstract class PlugintdzEntriesForm extends BasetdzEntriesForm
{
  public static $entry=null;
  public $reloaded=false;
  public $blocks=array();
  public $use_fields=array();
  public $relations=array();

  public function configure_cms()
  {

    $fields=array('id'=>true);
    $types=array();
    foreach(sfConfig::get('app_e-studio_entry_types') as $tn=>$td) {
      if(isset($td['disabled']) && $td['disabled']) continue;
      $types[$tn]=$td['label'];
    }

    $t=sfContext::getInstance()->getI18N();

    $o=$this->getObject();
    if($this->isNew()) {
      if(count($_POST)>0) {
        $type=$this->getDefault('type');
      } else {
        $type = array_shift(array_keys($types));
      } 
    } else {
      $type = $o->type;
    }

    if($type=='')$type='entry';

    $this->blocks['Properties']=array();
    //$this->blocks['Contents']=array();
    //$this->blocks['Availability']=array();
    if($this->isNew())
      $this->blocks['Properties']['type']=array('type'=>'choice','choices'=>$types,'required'=>true,'attributes'=>array('onchange'=>'tdz.cms_reload()'));

    $this->blocks['Properties']['title']=array('type'=>'text');
    $this->blocks['Properties']['link']=array('type'=>'text','format'=>'url','foreign'=>($type=='entry'));

    if($type!='file')
      $this->blocks['Properties']['summary']=array('type'=>'html');
    $this->blocks['Properties']['tags']=array('type'=>'text','method'=>'tags');
    $tag=$o->getTags();
    $tags=array();
    foreach($tag as $tn)
      $tags[]=$tn->tag;
    $this->setDefault('tags', implode(', ',$tags));

    $o=$this->getObject();
    if($type=='page')
    {
      $this->blocks['Contents']['contents']=array('type'=>'embedded','method'=>'contentsForm');
      if(!$this->isNew())
      {
        $this->blocks['Availability']['parents']=array('type'=>'embedded','method'=>'sitemapForm');
        $this->relations['parents']=array('model'=>'tdzRelations','relation'=>'ParentRelations','foreign'=>'parent','local'=>'entry','add_columns'=>array('position'=>'fixPosition'),'post_process'=>'fixPositions');
      }
    }
    else if($type=='file')
    {
      $options=array();
      $desc=false;
      if($this->isNew())
        $options['file_src']='';
      else if(substr($o->format,0,6)=='image/')
      {
        $options['file_src']='/e-studio/e/preview/'.$o->id.'?optimize=thumb';
        $options['is_image']=true;
        $file=sfConfig::get('app_e-studio_upload_dir').'/'.$o->source;
        if(file_exists($file))
        {
          //$desc  = '<strong>'.$t->__('Format').':</strong> '.$t->__('Image').'<br />';
          $is=getimagesize($file);
          $desc = '<strong>'.$t->__('Dimensions').':</strong> '.$is[0].'x'.$is[1].' pixels<br />';
          $desc .= '<strong>'.$t->__('Size').':</strong> '.tdz::formatBytes(filesize($file));
        }
      }
      else if($o->link)
      {
        $options['file_src']=$o->link;
        if(sfConfig::get('app_e-studio_assets_optimize') && strpos($options['file_src'],sfConfig::get('app_e-studio_assets_prefix'))===0 && preg_match('/\.(jpg|jpeg|png|gif)$/i',$o->link, $m))
        {
          $options['file_src']=substr($options['file_src'],0,strlen($options['file_src'])-strlen($m[0])).'.thumb'.$m[0];
          $options['is_image']=true;
        }
      }
      else
        $options['file_src']='/e-studio/e/preview/'.$o->id;

      $this->blocks['Properties']['source']=array('type'=>'upload','path'=>sfConfig::get('app_e-studio_upload_dir'),'clean_old_files'=>false,'options'=>$options);
      if($desc)$this->blocks['Properties']['source']['description']=$desc;
      $this->blocks['Properties']['format']=array('type'=>'hidden');
    }
    else if($type=='entry')
    {
      $this->blocks['Properties']['published']=array('type'=>'datetime');
      $this->blocks['Contents']['contents']=array('type'=>'embedded','method'=>'imagesForm');
      $this->blocks['Contents']['contents']=array('type'=>'embedded','method'=>'contentsForm','options'=>array('content_type'=>'media'));
      $this->blocks['Availability']['parents']=array('type'=>'embedded','method'=>'feedForm','label'=>'News channel');
      $this->relations['parents']=array('model'=>'tdzRelations','relation'=>'ParentRelations','foreign'=>'parent','local'=>'entry','add_columns'=>array('position'=>'fixPosition'));
    }
    else
    {
      //$this->blocks['Contents']['contents']=array('type'=>'embedded','relation'=>'tdzContents');
      //$this->blocks['Availability']['feed']=array('type'=>'embedded','relation'=>'tdzRelations');
    }
    if($o->hasPermission('edit','Permission'))
    $this->blocks['Permissions']=array(
      'permissions'=>array('type'=>'embedded','method'=>'permissionsForm','label'=>'Permissions'),
    );
    $this->widgetSchema->setNameFormat('tdze[%s]');
    tdz::form($this);
    $this->mergePostValidator(new PlugintdzEntriesValidatorSchema());
  }

  public function doSave($con = null)
  {
    $e=$this->getObject();
    if($i->id=='')
    {
      $e->save();
      tdzEntriesForm::$entry=$e->id;
    }
    $res=parent::doSave($con);
    // check for posted relations
    $rel=$this->relations;
    $post = $this->getTaintedValues();
    $ws = $this->getWidgetSchema();
    foreach($rel as $fn=>$rd)
    {
      if(isset($ws[$fn]))
      {
        $pp = (isset($rd['post_process']))?($rd['post_process']):(false);
        $pk='id';
        $localid=$e['id'];
        $fkn=$rd['foreign'];
        $model=$rd['model'];
        $values = (isset($post[$fn]))?($post[$fn]):(array());
        $keys=array_keys($values);
        $key=array_shift($keys);
        if(!is_numeric($key))$values=array($values);
        $rm='get'.$rd['relation'];
        $curr=$e->$rm();
        // sort by key and foreign key, to properly get the right row
        $ck=array();
        $cfk=array();
        foreach($curr as $ro)
        {
          $k=$ro->$pk;
          $fk=$ro->$fkn;
          $cfk[$fk]=$ck[$k]=$ro;
        }
        // loop through posted values for update
        foreach($values as $k=>$v)
        {
          if(!is_array($v))$v=array($fkn=>$v);
          if(!isset($v[$rd['local']]))
            $v[$rd['local']]=$localid;
          $values[$k]=$v;
          $ro=false;
          if(isset($v[$pk]) && isset($ck[$v[$pk]]))
          {
            $ro=$ck[$v[$pk]];
            //$fk=$ro->$rd['foreign'];
            //if($fk)unset($cfk[$fk]);
          }
          else if(isset($v[$fkn]) && isset($cfk[$v[$fkn]]))
          {
            $ro=$cfk[$v[$fkn]];
          }
          if($ro)
          {
            unset($values[$k]);
            unset($ck[$ro->$pk]);
            foreach($v as $vk=>$vv)
            {
              if($vv=='')$vv=null;
              $ro[$vk]=$vv;
            }
            if(isset($rd['add_columns']))
            {
              foreach($rd['add_columns'] as $ak=>$am)
              {
                if($ro[$ak]=='')
                  $ro[$ak]=$ro->$am();
              }
            }
            $ro->save();
            if($pp)$ro->$pp();
          }
        }
        foreach($values as $k=>$v)
        {
          // use existing keys that were not found
          $ro=false;
          if(count($ck)>0)
            $ro=array_shift($ck);
          if(!$ro)
            $ro = new $model();
          foreach($v as $vk=>$vv)
          {
            if($vv=='')$vv=null;
            $ro[$vk]=$vv;
          }
          $ro->save();
          if($pp)$ro->$pp();
        }
        // keys not used, discard
        foreach($ck as $ro)
        {
          $ro->delete();
          if($pp)$ro->$pp();
        }
      }
    }
    return $res;
  }

  public function render_cms()
  {
    return tdz::renderForm($this);
  }

  public static function addContentsForm($o=false, $values=null)
  {
    if(!$o) $o=new tdzContents();
    $cf = new tdzContentsForm($o);
    if(is_array($values) && count($values)==0) $values=array();
    $cf->configure_cms(array('embedded'=>true), $values);
    return $cf;
  }

  public function contentsForm($options=array(), $arguments=array(), $fd=array())
  {
    $o=$this->getObject();
    $c = (array)$o->getSortedContents();
    $request=sfContext::getInstance()->getRequest();
    $get = $request->getGetParameter('tdze');
    $cb = $request->getGetParameter('cb');
    if(!is_array($cb))$cb=array();
    $post = $request->getPostParameter('tdze');
    $ispost=count($post);
    $new=$request->getParameter('new',array());

    $url = htmlspecialchars($_SERVER['QUERY_STRING']);
    if($url!='' && substr($url,-5)!='&amp;')$url.='&amp;';
    $img=sfConfig::get('app_e-studio_assets_url').'/images/icons.png';

    $fc = new sfForm();
    $i = 0;
    $next = count($c);
    if(isset($post['contents']))$next=count($post['contents']);
    else if(isset($get['contents']))$next=count($get['contents']);

    $values=array();
    if(isset($post['contents']))
      $values=$post['contents'];
    else if(isset($get['contents']))
      $values=$get['contents'];
    else
      $values=$c;
    $len=count($values);
    $next=$len;
    $forms=false;
    $entry=$o->id;
    $positions=array();
    foreach($values as $i=>$val)
    {
      $forms=true;
      if(is_object($val))
      {
        $ci=$val;
        $val=array('entry'=>$entry)+(array)$ci->getData();
      }
      else
      {
        $ci=false;
        if(isset($c[$i])) $ci=$c[$i];
      }

      if(!isset($val['slot']))$val['slot']='body';
      $slot=$val['slot'];
      if(!isset($positions[$slot]))$positions[$slot]=0;
      $positions[$slot]++;
      $val['position']=$positions[$slot];
      //<div class=\"tdzcms\"><a class=\"new\" href=\"?{$url}new[$pos]=before\" onclick=\"tdz.cms_reload({qs:'new[$pos]=before'});return false;\"><img src=\"{$img}\" alt=\"New\" /></a></div>
      if(isset($new[$i]) && $new[$i]=='before')
      {
        $pos=$next++;
        $cf=self::addContentsForm(false, array('entry'=>$entry));
        $fc->embedForm($pos,$cf,"<div id=\"tdzc_{$pos}\" class=\"embedded sortable item\">%content%</div>");
      }
      $pos=$i;
      $cf=self::addContentsForm($ci,$val);
      $fc->embedForm($pos,$cf,"<div id=\"tdzc_{$pos}\" class=\"embedded sortable item\">%content%</div>");
      if(isset($new[$i]) && $new[$i]!='before')
      {
        $pos=$next++;
        $cf=self::addContentsForm(false, array('entry'=>$entry));
        $fc->embedForm($pos,$cf,"<div id=\"tdzc_{$pos}\" class=\"embedded sortable item\">%content%</div>");
      }
    }
    if($next==0 && isset($new['-1']) && $new['-1']!='before')
    {
      $pos=$next++;
      $cf=self::addContentsForm(false, array('entry'=>$entry));
      $fc->embedForm($pos,$cf,"<div id=\"tdzc_{$pos}\" class=\"embedded sortable item\">%content%</div>");
    }
    if(true ||$forms)
    {
      //$fc->widgetSchema->setFormFormatterName('tdz');
      $pos=$next;
      $this->embedForm('contents',$fc,"%content%<div class=\"embedded\"></div>");
    }
    return true;
  }

  public function imagesForm($options=array(), $arguments=array(), $fd=array())
  {
    $o=$this->getObject();
    $c = $o->getContents();
    $request=sfContext::getInstance()->getRequest();
    $get = $request->getGetParameter('tdze');

    $fc = new sfForm();
    $i = 0;
    if($c->count()>0)
    {
      foreach($c as $ci)
      {
        if(isset($get['contents'][$i]))
        {
          if(isset($get['contents'][$i]['contents'])) unset($get['contents'][$i]['contents']);
          foreach($get['contents'][$i] as $k=>$v)
          {
            $ci[$k]=$v;
          }
        }
        $cf = new tdzContentsForm($ci);
        $cf->configure_cms(array('embedded'=>true));
        $fc->embedForm($i++,$cf);
      }
    }
    if($i>0)
    {
      $this->embedForm('contents',$fc);
    }
    return true;
  }

  public function feedForm($options=array(), $arguments=array(), $fd=array())
  {
    $o=$this->getObject();
    $request=sfContext::getInstance()->getRequest();
    $get = $request->getGetParameter('tdze');
    if(isset($get['parents']))
      $d = $get['parents'];
    else
    {
      $d = array();
      $c = $o->getParentFeed();
      if($c->count()>0)
      {
        foreach($c as $e)
          $d[]=$e->id;
      }
    }
    $this->setDefault('parents',$d);


    $co = tdzEntries::getFeeds();
    $choices = array();
    foreach($co as $feed)
      $choices[$feed->id]=$feed->title;

    $options['choices']=$choices;
    //$options['model']='tdzEntries';
    //$options['table_method']='getChannels';
    $options['multiple']=true;
    $options['expanded']=true;
    return new sfWidgetFormChoice($options, $arguments);
  }

  public function sitemapForm($options=array(), $arguments=array(), $fd=array())
  {
    $o=$this->getObject();
    $fk='parents';
    $p = $o->getRelations();
    $request=sfContext::getInstance()->getRequest();
    $get = $request->getGetParameter('tdze');
    $post = $request->getPostParameter('tdze');
    $ispost=(count($post)>0);
    //$new=$request->getParameter('new',array());

    $url = htmlspecialchars($_SERVER['QUERY_STRING']);
    if($url!='' && substr($url,-5)!='&amp;')$url.='&amp;';
    $img=sfConfig::get('app_e-studio_assets_url').'/images/icons.png';

    $values=array();
    $ro=false;
    if(isset($post[$fk]))
      $values=$post[$fk];
    else if(isset($get[$fk]))
      $values=$get[$fk];
    else if($p->count()>0)
    {
      $values=(array)$p->getFirst()->getData();
    }
    $ro=($p->count()>0)?($p[0]):(false);
    if(!$ro)
    {
      $ro = new tdzRelations();
      foreach($values as $k=>$v)
        $ro[$k]=$v;
    }
    $ro->entry=$this->getObject()->getId();

    $o=array('indent'=>1);
    if(isset($values['entry'])) $o['selected']=array($values['entry']);
    $sitemap=tdzRelations::renderSitemap(null,$o);
    $sf = new tdzRelationsForm($ro);
    $sf->configure_cms('hidden');
    //tdz::debug($values,$o,$sitemap);
    //$fc->widgetSchema->setFormFormatterName('tdz');
    $sitemap = str_replace('%','%%',$sitemap);
    $label = sfContext::getInstance()->getI18n()->__('Sitemap');
    $this->embedForm($fk,$sf,"<label for=\"tdze_{$fk}_sitemap\">{$label}</label><div class=\"select_list\">{$sitemap}</div>%content%");
    return true;
  }

  public function updateLinkColumn($value)
  {
    if($this->getValue('type')=='file' && substr($value,-1)=='/')
    {
      $files = (isset($_FILES[$this->getName()]))?($_FILES[$this->getName()]):(null);
      if($files && isset($files['name']['source']) && $files['name']['source']!='')
        $value = tdz::validUrl($value.$files['name']['source']);

    }
    return $value;
  }
  public function updateFormatColumn($value)
  {
    if($this->getValue('type')=='file')
    {
      $files = (isset($_FILES[$this->getName()]))?($_FILES[$this->getName()]):(null);
      if($files && isset($files['type']['source']) && $files['type']['source']!='')
        $value = $files['type']['source'];

    }
    return $value;
  }
  public function updateSitemapColumn($value)
  {
    return false;
  }
  public function updateTagsColumn($value)
  {
    if(!is_array($value))
      $value=preg_split('/\s*[,;]+\s*/', trim($value), null, PREG_SPLIT_NO_EMPTY);
    $o=$this->getObject();
    if(!$o->id && count($value)>0) $o->save();
    $tags=$o->getTags();
    $todelete=$tags->count();
    $maxi=$todelete;
    $existing=array();
    $i=0;
    $updated=false;
    foreach($value as $i=>$v)
    {
      $v=preg_replace('/\s+/', ' ', trim($v));
      $slug=tdz::textToSlug($v);
      if(isset($existing[$slug])) continue;
      $existing[$slug]=true;
      $updated=true;
      if(!isset($tags[$i]))
      {
        $tags[$i]=new tdzTags();
        $tags[$i]->entry=$o->id;
        $tags[$i]->tag=$v;
        $tags[$i]->slug=$slug;
        $tags[$i]->save();
      }
      else if($tags[$i]->tag!=$v)
      {
        $tags[$i]->tag=$v;
        $tags[$i]->slug=$slug;
        $tags[$i]->save();
      }
      $todelete--;
    }
    if($todelete>0)
    {
      $updated=true;
      while($todelete>0)
      {
        $i=$maxi-$todelete;
        $tags[$i]->delete();
        $tags[$i]->save();
        $todelete--;
      }
    }
    if($updated)$o->updated=date('Y-m-d H:i:s');
    return true;
  }

  protected function removeFile($field)
  {
    // prevent the removal of old files...
  }
  public function permissionsForm($options=array(), $arguments=array(), $fd=array())
  {
    $o=$this->getObject();
    $p = $o->getPermissions();
    $request=sfContext::getInstance()->getRequest();
    $get = $request->getGetParameter('tdze');
    $post = $request->getPostParameter('tdze');
    $ispost=count($post);
    $new=$request->getParameter('new',null);

    $url = htmlspecialchars($_SERVER['QUERY_STRING']);
    if($url!='' && substr($url,-5)!='&amp;')$url.='&amp;';
    $img=sfConfig::get('app_e-studio_assets_url').'/images/icons.png';

    $fp = new sfForm();
    $i = 0;
    $roles = sfConfig::get('app_e-studio_form_permissions');
    $fk='permissions';
    $values=array();
    if(isset($post[$fk]))
      $values=$post[$fk];
    else if(isset($get[$fk]))
      $values=$get[$fk];
    else
    {
      foreach($p as $i=>$v)
        $values[]=$v->getData();
    }

    $vr=array();
    foreach($values as $value)
    {
      if(isset($value['role']))
      {
        $k=(string)$value['role'];
        if(!isset($roles[$k]))
          $roles[$k]=$k;
        if(isset($vr[$k]))
          $vr[$k]['credentials'].=$value['credentials'];
        else
          $vr[$k]=$value;
      }
    }

    $entry=$o->id;
    $i=0;
    foreach($roles as $role=>$label)
    {
      if(isset($vr[$role]))
      {
        $val=$vr[$role];
      }
      else
      {
        $val=array(
         'entry'=>$entry,
         'role'=>$role,
         'credentials'=>$o->getPermission($role),
        );
      }
      //<div class=\"tdzcms\"><a class=\"new\" href=\"?{$url}new[$pos]=before\" onclick=\"tdz.cms_reload({qs:'new[$pos]=before'});return false;\"><img src=\"{$img}\" alt=\"New\" /></a></div>
      $pos=$i;
      $po=(isset($p[$i]))?($p[$i]):(null);
      $pf=self::addPermissionsForm($po,$val,$label);
      $fp->embedForm($pos,$pf,"<div id=\"tdzp_{$pos}\" class=\"item\">%content%</div>");
      $i++;
    }
    $this->embedForm($fk,$fp,"%content%<div class=\"embedded\"></div>");
    return true;
  }
  public static function addPermissionsForm($o=false, $values=null, $label=false)
  {
    if(!$o) $o=new tdzPermissions();
    if(!is_array($values)) $values=array();
    foreach($values as $k=>$v)
      $o[$k]=$v;
    if(!$label)$label=ucfirst($o['role']);
    $pf = new tdzPermissionsForm($o);
    $pf->configure_cms(array('embedded'=>true,'label'=>$label), $values);
    return $pf;
  }
  
  public function saveEmbeddedForms($con = null, $forms = null)
  {
    if(isset($this->embeddedForms['permissions']) && null === $forms)
    {
      $p = $this->getValue('permissions');
      $e=$this->getObject();
      $forms = $this->embeddedForms;
      foreach ($this->embeddedForms['permissions'] as $name => $form)
      {
        if(!is_numeric($name))
        {
          unset($forms['permissions'][$name]);
          continue;
        }
        $cp=$e->getPermission($p[$name]['role']);
        if(!is_array($cp)) $cp=array();
        asort($cp);
        $cp=implode(', ',$cp);
        
        $np=(isset($p[$name]['credentials']))?($p[$name]['credentials']):(array());
        if(!is_array($np)) $np=array();
        asort($np);
        $np=implode(', ',$np);
        if($np==$cp)
          unset($forms['permissions'][$name]);
        //else if($forms['permissions'][$name]->getObject()->id=='')
        //{
        //  $forms['permissions'][$name]->getObject()->id=$e->id;
        //}
      }
    }
    return parent::saveEmbeddedForms($con, $forms);
  }

}
