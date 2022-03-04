<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
namespace Tecnodesign\Test\Unit;

class MailTest extends \PHPUnit\Framework\TestCase
{
    public function testMailSending()
    {
        $headers = array(
            'From' => 'robo@capile.net',
            'To' => 'g@capile.net',
            'Subject' => 'Testing e-mail submission',
        );
        $body = '<p>This is a simple test done at ' . date('c') . "\n\nPlease disregard it.</p>";

        $msg = new \Tecnodesign_Mail($headers);
        $msg->setHtmlBody($body, true);
        $this->assertEquals($msg->send(), true);
    }
}
