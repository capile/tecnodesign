<?php


class OAuth2Example {
    

    public static function executeAccessToken()
    {
        Studio\OAuth2\Server::instance()->executeTokenRequest();
    }
}