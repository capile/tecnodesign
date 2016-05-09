<?php
/**
 * Tecnodesign E-Studio
 *
 * Error template
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */
?>
<?php if ($title != '' || $message != ''): ?>
    <div class="tdz-error message">
    <h1><?php echo $title ?></h1>
    <?php echo $message ?>
    </div>
<?php endif; ?>