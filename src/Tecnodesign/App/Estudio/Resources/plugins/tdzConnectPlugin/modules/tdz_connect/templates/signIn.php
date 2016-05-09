<?php
/**
 * Tecnodesign Single Sign In template
 *
 * If you need a customized template, update app_connect_widtgets/signin/template
 * with the full path of the template, or the basename at sf_app_template_dir
 *
 * @package      tdzConnectPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */

?><div id="connect"><?php if(!$expanded): ?><?php if($user->authenticated): ?><a href="<?php echo $signout ?>"><?php echo $user->getAttribute('name') ?></a><?php else: ?><a href="<?php echo $signin ?>"><?php echo $signin_button ?></a><?php endif; ?></div>
<div id="connect-info"><?php endif; ?><div class="container">
<?php
if($user->authenticated)
{
  $provider=preg_replace('/\:.*$/', '', $user->getAttribute('id'));
  echo $user->preview().'<button id="signout" type="button" onclick="return tdz.connectSignOut(\''.$signout.'/'.$provider.'\');">'.$signout_button.'</button>';
}
else
{
  $forms='';
  $buttons='';
  foreach($ns as $provider=>$options)
  {
    $url=(isset($options['url']))?($options['url']):($signin.'/'.$provider.'?from='.urlencode($_SERVER['REQUEST_URI']));
    if(isset($options['form']) && $options['form'])
    {
      $forms .= '<div id="form-'.$provider.'"><form action="'.$url.'" method="post">';
      $forms .= tdz::renderForm($options['form']).'<button type="submit">'.$signin_button.'</button></form></div>';
    }
    else if(isset($options['signin']) && $options['signin'])
    {
      $buttons .= '<a id="button-'.$provider.'" href="'.$url.'"><img src="'.$icon.'" alt="'.$options['label'].'" title="'.$options['label'].'" /></a>';
    }
  }
  if($buttons!='')$buttons = "<div class=\"buttons\">{$buttons}</div>";
  echo $expanded_text.$forms.$buttons;
}
?><br style="clear:both" /></div>
</div>