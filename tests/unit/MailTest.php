<?php

namespace TecnodesignTest\Unit;

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
