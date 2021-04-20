<?php
/**
 * OAuth2 Server implementation using thephpleague/oauth2-server
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.3
 */

namespace Studio\OAuth2;

use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

class AuthCode extends Model implements AuthCodeRepositoryInterface, AuthCodeEntityInterface
{
    // AuthCodeRepositoryInterface
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        // Some logic to persist the auth code to a database
    }

    public function revokeAuthCode($codeId)
    {
        // Some logic to revoke the auth code in a database
    }

    public function isAuthCodeRevoked($codeId)
    {
        return false; // The auth code has not been revoked
    }

    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }
}