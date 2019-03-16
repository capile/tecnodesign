<?php

class StudioCest 
{
    public function docsPageWorks(AcceptanceTester $I)
    {
        $I->amOnPage('/docs/');
        $I->see('Tecnodesign Application Development Framework');  
    }
}