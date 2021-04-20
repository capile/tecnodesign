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

use Studio\Model;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

class AccessToken extends Model implements AccessTokenRepositoryInterface, AccessTokenEntityInterface
{
    // AccessTokenRepositoryInterface
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        // Some logic here to save the access token to a database
    }

    public function revokeAccessToken($tokenId)
    {
        // Some logic here to revoke the access token
    }

    public function isAccessTokenRevoked($tokenId)
    {
        return false; // Access token hasn't been revoked
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);

        return $accessToken;
    }
}