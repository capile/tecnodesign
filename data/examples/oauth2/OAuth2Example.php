<?php


class OAuth2Example {
    

    public static function executeAccessToken()
    {
        Studio\OAuth2\Server::instance()->executeTokenRequest();
    }

    public static function executeAuth()
    {
        Studio\OAuth2\Server::instance()->executeAuth();
    }
}