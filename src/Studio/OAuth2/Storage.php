<?php
/**
 * OAuth2 Server Storage mappings
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.4
 */

namespace Studio\OAuth2;

use OAuth2\Storage\ClientCredentialsInterface;
use OAuth2\Storage\UserCredentialsInterface;
use OAuth2\Storage\ClientInterface;
use OAuth2\Storage\AccessTokenInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\JwtBearerInterface;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface;
use OAuth2\OpenID\Storage\UserClaimsInterface;
use OAuth2\Storage\ScopeInterface;
use OAuth2\Storage\PublicKeyInterface;
use InvalidArgumentException;
use tdz as S;

class Storage implements ClientCredentialsInterface, UserCredentialsInterface, AuthorizationCodeInterface, ClientInterface, AccessTokenInterface, RefreshTokenInterface, JwtBearerInterface, UserClaimsInterface, ScopeInterface, PublicKeyInterface
{
    protected static $instance;
    public static 
        $scopes=[
            'client_credentials'=>[
                'client_id'=>'id',
                'client_secret'=>'options.client_secret',
                'redirect_uri'=>'options.redirect_uri',
                'grant_types'=>'options.grant_types',
                'scope'=>'options.scope',
                'user_id'=>'user',
            ],
            'access_token'=>[
                'expires'=>'expires',
                'client_id'=>'options.client_id',
                'user_id'=>'user',
                'scope'=>'options.scope',
                'id_token'=>'options.id_token',
            ],
            'authorization_code'=>[
                'client_id'=>'options.client_id',
                'user_id'=>'user',
                'expires'=>'expires',
                'redirect_uri'=>'options.redirect_uri',
                'scope'=>'options.scope'
            ],
            'access_token'=>[
                'expires'=>'expires',
                'client_id'=>'options.client_id',
                'user_id'=>'user',
                'scope'=>'options.scope',
                'id_token'=>'options.id_token',
            ],
            'refresh_token'=>[
                'expires'=>'expires',
                'client_id'=>'options.client_id',
                'user_id'=>'user',
                'scope'=>'options.scope',
                'id_token'=>'options.id_token',
            ],
            'jwt_bearer'=>[
                'client_id'=>'id',
                'subject'=>'token',
                'user_id'=>'user',
                'public_key'=>'options.public_key',
            ],
            'jti'=> [
                'issuer'=>'token',
                'subject'=>'id',
                'audience'=>'options.audience',
                'expires'=>'expires',
                'jti'=>'options.jti',
            ],
            'user_claims'=>[
                'user_id'=>'user',
            ],
            'public_key'=>[
                'user_id'=>'user',
                'public_key'=>'options.public_key',
                'private_key'=>'options.private_key',
                'encryption_algorithm'=>'options.encryption_algorithm',
                'expires'=>'expires',
            ],
            'server_credentials'=>[
                'id' => 'id',
                'client_id' => 'token',
                'client_secret' => 'options.client_secret',
                'issuer' => 'options.issuer',
                'grant_type' => 'options.grant_type',
                'authorization_endpoint'=>'options.authorization_endpoint',
                'token_endpoint'=>'options.token_endpoint',
                'userinfo_endpoint'=>'options.userinfo_endpoint',
                'scope'=>'options.scope',
                'user_create'=>'options.user_create',
                'user_update'=>'options.user_update',
                'user_key'=>'options.user_key',
                'user_map'=>'options.user_map',
                'button'=>'options.button',
                'name'=>'options.name',
            ],
        ];
    protected 
        $tokens = [],
        $tokenFinder='Studio\\Model\\Tokens';

    public static function instance()
    {
        if(!static::$instance) {
            static::$instance = new Storage();
        }

        return static::$instance;
    }

    public static function find($type, $asArray=true)
    {
        $Q = static::instance()->tokenFinder;
        $L = $Q::find((is_array($type)) ?$type :['type'=>$type],null,null,false);
        $r = [];


        if($L) {
            if(!$asArray) {
                return $L;
            } else {
                $scope = (is_string($type) && isset(static::$scopes[$type])) ?static::$scopes[$type] :null;
                $fn = null;
                if(is_string($asArray) && $scope && isset($scope[$fn])) {
                    $fn = $scope[$fn];
                }

                foreach($L as $i=>$o) {
                    if($o && $o->expires && S::strtotime($o->expires)<TDZ_TIMESTAMP) continue;
                    if($fn) $r[] = $o->$fn;
                    else $r[] = $o->asArray($scope);
                }
            }
        }

        return $r;
    }

    public static function replace($r)
    {
        $Q = static::instance()->tokenFinder;
        return $Q::replace($r);
    }

    public static function fetch($type, $id, $asArray=true)
    {
        $S = static::instance();
        return $S->getObject($type, $id, $asArray);
    }

    public function getObject($type, $id, $asArray=true)
    {
        $Q = $this->tokenFinder;
        $R = $Q::find(['id'=>$id, 'type'=>$type],1);

        if($R && $R->expires && S::strtotime($R->expires)<TDZ_TIMESTAMP) $R=null;
        if($R) {
            if(!$asArray) {
                return $R;
            } else if(is_string($asArray)) {
                $fn = $asArray;
                if (isset(static::$scopes[$type][$fn])) {
                    $fn = static::$scopes[$type][$fn];
                }
                return $R[$fn];
            }

            $r = $R->asArray(static::$scopes[$type]);
            if(isset($r['expires']) && $r['expires']) {
                $r['expires'] = S::strtotime($r['expires']);
            }
            return $r;
        }
    }

    public function getClientCredentials($client_id)
    {
        return $this->getObject('client_credentials', $client_id);
    }

    /**
     * Make sure that the client credentials is valid.
     *
     * @param $client_id
     * Client identifier to be check with.
     * @param $client_secret
     * (optional) If a secret is required, check that they've given the right one.
     *
     * @return
     * TRUE if the client credentials are valid, and MUST return FALSE if it isn't.
     * @endcode
     *
     * @see http://tools.ietf.org/html/rfc6749#section-3.1
     */
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        if($this->getObject('client_credentials', $client_id, 'client_secret')==$client_secret) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the client is a "public" client, and therefore
     * does not require passing credentials for certain grant types
     *
     * @param $client_id
     * Client identifier to be check with.
     *
     * @return
     * TRUE if the client is public, and FALSE if it isn't.
     * @endcode
     *
     * @see http://tools.ietf.org/html/rfc6749#section-2.3
     * @see https://github.com/bshaffer/oauth2-server-php/issues/257
     */
    public function isPublicClient($client_id)
    {
        return $this->checkClientCredentials($client_id);
    }


    /**
     * Grant access tokens for basic user credentials.
     *
     * Check the supplied username and password for validity.
     *
     * You can also use the $client_id param to do any checks required based
     * on a client, if you need that.
     *
     * Required for OAuth2::GRANT_TYPE_USER_CREDENTIALS.
     *
     * @param $username
     * Username to be check with.
     * @param $password
     * Password to be check with.
     *
     * @return
     * TRUE if the username and password are valid, and FALSE if it isn't.
     * Moreover, if the username and password are valid, and you want to
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4.3
     */
    public function checkUserCredentials($username, $password)
    {
        if($U = S::user($username)) {
            return $U->authenticate($password);
        }

        return false;
    }

    /**
     * @param string $username - username to get details for
     * @return array|false     - the associated "user_id" and optional "scope" values
     *                           This function MUST return FALSE if the requested user does not exist or is
     *                           invalid. "scope" is a space-separated list of restricted scopes.
     * @code
     *     return array(
     *         "user_id"  => USER_ID,    // REQUIRED user_id to be stored with the authorization code or access token
     *         "scope"    => SCOPE       // OPTIONAL space-separated list of restricted scopes
     *     );
     * @endcode
     */
    public function getUserDetails($username)
    {
        if($U = S::user($username)) {
            return $U->asArray();
        }

        return false;
    }

    /**
     * Fetch authorization code data (probably the most common grant type).
     *
     * Retrieve the stored data for the given authorization code.
     *
     * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
     *
     * @param $code
     * Authorization code to be check with.
     *
     * @return
     * An associative array as below, and NULL if the code is invalid
     * @code
     * return array(
     *     "client_id"    => CLIENT_ID,      // REQUIRED Stored client identifier
     *     "user_id"      => USER_ID,        // REQUIRED Stored user identifier
     *     "expires"      => EXPIRES,        // REQUIRED Stored expiration in unix timestamp
     *     "redirect_uri" => REDIRECT_URI,   // REQUIRED Stored redirect URI
     *     "scope"        => SCOPE,          // OPTIONAL Stored scope values in space-separated string
     * );
     * @endcode
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4.1
     *
     * @ingroup oauth2_section_4
     */
    public function getAuthorizationCode($code)
    {
        return $this->getObject('authorization_code', $code);
    }

    /**
     * Take the provided authorization code values and store them somewhere.
     *
     * This function should be the storage counterpart to getAuthCode().
     *
     * If storage fails for some reason, we're not currently checking for
     * any sort of success/failure, so you should bail out of the script
     * and provide a descriptive fail message.
     *
     * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
     *
     * @param string $code         - Authorization code to be stored.
     * @param mixed  $client_id    - Client identifier to be stored.
     * @param mixed  $user_id      - User identifier to be stored.
     * @param string $redirect_uri - Redirect URI(s) to be stored in a space-separated string.
     * @param int    $expires      - Expiration to be stored as a Unix timestamp.
     * @param string $scope        - OPTIONAL Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_4
     */
    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        // convert expires to datestring
        $expires = date('Y-m-d H:i:s', $expires);
        $r = [
            'id'=>$code,
            'type'=>'authorization_code',
            'token'=>$client_id,
            'user'=>$user_id,
            'options' => [
                'redirect_uri'=>$redirect_uri,
            ],
            'expires' => date('Y-m-d H:i:s', $expires),
        ];

        if($scope) $r['options']['scope'] = $scope;
        if($id_token) $r['options']['token'] = $id_token;

        $Q = $this->tokenFinder;
        $Q::replace($r);
    }
    /**
     * once an Authorization Code is used, it must be expired
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4.1.2
     *
     *    The client MUST NOT use the authorization code
     *    more than once.  If an authorization code is used more than
     *    once, the authorization server MUST deny the request and SHOULD
     *    revoke (when possible) all tokens previously issued based on
     *    that authorization code
     *
     */
    public function expireAuthorizationCode($code)
    {
        if($R=$this->getObject('authorization_code', $code, false)) {
            $R->delete();
            return true;
        }
    }

    /**
     * Get client details corresponding client_id.
     *
     * OAuth says we should store request URIs for each registered client.
     * Implement this function to grab the stored URI for a given client id.
     *
     * @param $client_id
     * Client identifier to be check with.
     *
     * @return array
     *               Client details. The only mandatory key in the array is "redirect_uri".
     *               This function MUST return FALSE if the given client does not exist or is
     *               invalid. "redirect_uri" can be space-delimited to allow for multiple valid uris.
     *               <code>
     *               return array(
     *               "redirect_uri" => REDIRECT_URI,      // REQUIRED redirect_uri registered for the client
     *               "client_id"    => CLIENT_ID,         // OPTIONAL the client id
     *               "grant_types"  => GRANT_TYPES,       // OPTIONAL an array of restricted grant types
     *               "user_id"      => USER_ID,           // OPTIONAL the user identifier associated with this client
     *               "scope"        => SCOPE,             // OPTIONAL the scopes allowed for this client
     *               );
     *               </code>
     *
     * @ingroup oauth2_section_4
     */
    public function getClientDetails($client_id)
    {
        return $this->getObject('client_credentials', $client_id);
    }

    /**
     * @param string $client_id
     * @param null|string $client_secret
     * @param null|string $redirect_uri
     * @param null|array  $grant_types
     * @param null|string $scope
     * @param null|string $user_id
     * @return bool
     */
    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null)
    {
        $r = [
            'id'=>$client_id,
            'type'=>'client_credentials',
            'user'=>$user_id,
            'options' => [
            ],
        ];

        if($client_secret) $r['options']['client_secret'] = $client_secret;
        if($redirect_uri) $r['options']['redirect_uri'] = $redirect_uri;
        if($grant_types) $r['options']['grant_types'] = $grant_types;
        if($scope) $r['options']['scope'] = $scope;

        $Q = $this->tokenFinder;
        $Q::replace($r);

    }

    /**
     * Get the scope associated with this client
     *
     * @return
     * STRING the space-delineated scope list for the specified client_id
     */
    public function getClientScope($client_id)
    {
        return $this->getObject('client_credentials', $client_id, 'scope');
    }

    /**
     * Check restricted grant types of corresponding client identifier.
     *
     * If you want to restrict clients to certain grant types, override this
     * function.
     *
     * @param $client_id
     * Client identifier to be check with.
     * @param $grant_type
     * Grant type to be check with
     *
     * @return
     * TRUE if the grant type is supported by this client identifier, and
     * FALSE if it isn't.
     *
     * @ingroup oauth2_section_4
     */
    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        if($grant_type = $this->getObject('client_credentials', $client_id, 'grant_type')) {
            $grant_types = explode(' ', $details['grant_types']);

            return in_array($grant_type, (array) $grant_types);
        }

        // if grant_types are not defined, then none are restricted
        return true;
    }

    /**
     * Look up the supplied oauth_token from storage.
     *
     * We need to retrieve access token data as we create and verify tokens.
     *
     * @param string $oauth_token - oauth_token to be check with.
     *
     * @return array|null - An associative array as below, and return NULL if the supplied oauth_token is invalid:
     * @code
     *     array(
     *         'expires'   => $expires,   // Stored expiration in unix timestamp.
     *         'client_id' => $client_id, // (optional) Stored client identifier.
     *         'user_id'   => $user_id,   // (optional) Stored user identifier.
     *         'scope'     => $scope,     // (optional) Stored scope values in space-separated string.
     *         'id_token'  => $id_token   // (optional) Stored id_token (if "use_openid_connect" is true).
     *     );
     * @endcode
     *
     * @ingroup oauth2_section_7
     */
    public function getAccessToken($access_token)
    {
        return $this->getObject('access_token', $access_token);
    }

    /**
     * Store the supplied access token values to storage.
     *
     * We need to store access token data as we create and verify tokens.
     *
     * @param string $access_token - access_token to be stored.
     * @param mixed  $client_id    - client identifier to be stored.
     * @param mixed  $user_id      - user identifier to be stored.
     * @param int    $expires      - expiration to be stored as a Unix timestamp.
     * @param string $scope        - OPTIONAL Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_4
     */
    public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null)
    {
        if(!$scope) $scope = Server::config('default_scope');
        $r = [
            'id'=>$access_token,
            'type'=>'access_token',
            'token'=>$client_id,
            'user'=>$user_id,
            'options' => [
                'client_id'=>$client_id,
                'scope'=>$scope,
            ],
            'expires' => (is_int($expires)) ?date('Y-m-d H:i:s', $expires) :$expires,
        ];

        $Q = $this->tokenFinder;
        $Q::replace($r);

        if(Server::config('unique_access_token')) {
            if($L = $Q::find(['type'=>'access_token','token'=>$client_id,'id!='=>$access_token],null,null,false)) {
                foreach($L as $i=>$o) {
                    $o->delete(true);
                    unset($L[$i], $i, $o);
                }
            }
        }
    }

    /**
     * Expire an access token.
     *
     * This is not explicitly required in the spec, but if defined in a draft RFC for token
     * revoking (RFC 7009) https://tools.ietf.org/html/rfc7009
     *
     * @param $access_token
     * Access token to be expired.
     *
     * @return BOOL true if an access token was unset, false if not
     * @ingroup oauth2_section_6
     *
     * @todo v2.0 include this method in interface. Omitted to maintain BC in v1.x
     */
    public function unsetAccessToken($access_token)
    {
        if($R=$this->getObject('access_token', $access_token, false)) {
            $R->delete();
            return true;
        }
    }

    /**
     * Grant refresh access tokens.
     *
     * Retrieve the stored data for the given refresh token.
     *
     * Required for OAuth2::GRANT_TYPE_REFRESH_TOKEN.
     *
     * @param $refresh_token
     * Refresh token to be check with.
     *
     * @return
     * An associative array as below, and NULL if the refresh_token is
     * invalid:
     * - refresh_token: Refresh token identifier.
     * - client_id: Client identifier.
     * - user_id: User identifier.
     * - expires: Expiration unix timestamp, or 0 if the token doesn't expire.
     * - scope: (optional) Scope values in space-separated string.
     *
     * @see http://tools.ietf.org/html/rfc6749#section-6
     *
     * @ingroup oauth2_section_6
     */
    public function getRefreshToken($refresh_token)
    {
        return $this->getObject('refresh_token', $refresh_token);
    }

    /**
     * Take the provided refresh token values and store them somewhere.
     *
     * This function should be the storage counterpart to getRefreshToken().
     *
     * If storage fails for some reason, we're not currently checking for
     * any sort of success/failure, so you should bail out of the script
     * and provide a descriptive fail message.
     *
     * Required for OAuth2::GRANT_TYPE_REFRESH_TOKEN.
     *
     * @param $refresh_token
     * Refresh token to be stored.
     * @param $client_id
     * Client identifier to be stored.
     * @param $user_id
     * User identifier to be stored.
     * @param $expires
     * Expiration timestamp to be stored. 0 if the token doesn't expire.
     * @param $scope
     * (optional) Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_6
     */
    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        $r = [
            'id'=>$refresh_token,
            'type'=>'refresh_token',
            'token'=>$client_id,
            'user'=>$user_id,
            'options' => [
                'client_id'=>$client_id,
            ],
            'expires' => (is_int($expires)) ?date('Y-m-d H:i:s', $expires) :$expires,
        ];

        if($scope) $r['options']['scope'] = $scope;

        $Q = $this->tokenFinder;
        $Q::replace($r);
    }

    /**
     * Expire a used refresh token.
     *
     * This is not explicitly required in the spec, but is almost implied.
     * After granting a new refresh token, the old one is no longer useful and
     * so should be forcibly expired in the data store so it can't be used again.
     *
     * If storage fails for some reason, we're not currently checking for
     * any sort of success/failure, so you should bail out of the script
     * and provide a descriptive fail message.
     *
     * @param $refresh_token
     * Refresh token to be expired.
     *
     * @ingroup oauth2_section_6
     */
    public function unsetRefreshToken($refresh_token)
    {
        if($this->getObject('refresh_token', $refresh_token, false)) {
            $R->delete();

            return true;
        }
    }



    /**
     * @param string $code
     * @param mixed  $client_id
     * @param mixed  $user_id
     * @param string $redirect_uri
     * @param string $expires
     * @param string $scope
     * @param string $id_token
     * @return bool
     */
    private function setAuthorizationCodeWithIdToken($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        // convert expires to datestring
        $expires = date('Y-m-d H:i:s', $expires);

        // if it exists, update it.
        if ($this->getAuthorizationCode($code)) {
            $stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET client_id=:client_id, user_id=:user_id, redirect_uri=:redirect_uri, expires=:expires, scope=:scope, id_token =:id_token where authorization_code=:code', $this->config['code_table']));
        } else {
            $stmt = $this->db->prepare(sprintf('INSERT INTO %s (authorization_code, client_id, user_id, redirect_uri, expires, scope, id_token) VALUES (:code, :client_id, :user_id, :redirect_uri, :expires, :scope, :id_token)', $this->config['code_table']));
        }

        return $stmt->execute(compact('code', 'client_id', 'user_id', 'redirect_uri', 'expires', 'scope', 'id_token'));
    }

    /*
    // valid scope values to pass into the user claims API call
    const VALID_CLAIMS = 'profile email address phone';

    // fields returned for the claims above
    const PROFILE_CLAIM_VALUES  = 'name family_name given_name middle_name nickname preferred_username profile picture website gender birthdate zoneinfo locale updated_at';
    const EMAIL_CLAIM_VALUES    = 'email email_verified';
    const ADDRESS_CLAIM_VALUES  = 'formatted street_address locality region postal_code country';
    const PHONE_CLAIM_VALUES    = 'phone_number phone_number_verified';
    */

    /**
     * Return claims about the provided user id.
     *
     * Groups of claims are returned based on the requested scopes. No group
     * is required, and no claim is required.
     *
     * @param mixed  $user_id - The id of the user for which claims should be returned.
     * @param string $scope   - The requested scope.
     * Scopes with matching claims: profile, email, address, phone.
     *
     * @return array - An array in the claim => value format.
     *
     * @see http://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
     */
    public function getUserClaims($user_id, $claims)
    {
        if (!$userDetails = $this->getUserDetails($user_id)) {
            return [];
        }

        $claims = explode(' ', trim($claims));

        if(in_array('openid', $claims)) {
            return $userDetails;
        }

        // @TODO: move claims to model queries
        $userClaims = array();

        // for each requested claim, if the user has the claim, set it in the response
        $validClaims = explode(' ', self::VALID_CLAIMS);
        foreach ($validClaims as $validClaim) {
            if (in_array($validClaim, $claims)) {
                if ($validClaim == 'address') {
                    // address is an object with subfields
                    $userClaims['address'] = $this->getUserClaim($validClaim, $userDetails['address'] ?: $userDetails);
                } else {
                    $userClaims = array_merge($userClaims, $this->getUserClaim($validClaim, $userDetails));
                }
            }
        }

        return $userClaims;
    }

    /**
     * @param string $claim
     * @param array  $userDetails
     * @return array
     */
    protected function getUserClaim($claim, $userDetails)
    {
        $userClaims = array();
        $claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
        $claimValues = explode(' ', $claimValuesString);

        foreach ($claimValues as $value) {
            $userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
        }

        return $userClaims;
    }

    /**
     * plaintext passwords are bad!  Override this for your application
     *
     * @param array $user
     * @param string $password
     * @return bool
     */
    protected function checkPassword($user, $password)
    {
        return $user['password'] == $this->hashPassword($password);
    }

    // use a secure hashing algorithm when storing passwords. Override this for your application
    protected function hashPassword($password)
    {
        return sha1($password);
    }

    /**
     * @param string $username
     * @return array|bool
     */
    public function getUser($username)
    {
        if (!($userInfo = $this->getUserDetails($username))) {
            return false;
        }

        // the default behavior is to use "username" as the user_id
        return array_merge(array(
            'user_id' => $username
        ), $userInfo);
    }

    /**
     * plaintext passwords are bad!  Override this for your application
     *
     * @param string $username
     * @param string $password
     * @param string $firstName
     * @param string $lastName
     * @return bool
     */
    public function setUser($username, $password, $firstName = null, $lastName = null)
    {
        S::debug(__METHOD__.' not implemented.');
    }

    /**
     * @param string $scope
     * @return bool
     */
    public function scopeExists($scope)
    {
        return true;
        /*
        $scope = explode(' ', $scope);
        $whereIn = implode(',', array_fill(0, count($scope), '?'));
        $stmt = $this->db->prepare(sprintf('SELECT count(scope) as count FROM %s WHERE scope IN (%s)', $this->config['scope_table'], $whereIn));
        $stmt->execute($scope);

        if ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            return $result['count'] == count($scope);
        }

        return false;
        */
    }

    /**
     * @param mixed $client_id
     * @return null|string
     */
    public function getDefaultScope($client_id = null)
    {
        return Server::config('default_scope');
    }

    /**
     * Get the public key associated with a client_id
     *
     * @param $client_id
     * Client identifier to be checked with.
     *
     * @return
     * STRING Return the public key for the client_id if it exists, and MUST return FALSE if it doesn't.
     */
    public function getClientKey($client_id, $subject)
    {
        if(($r=$this->getObject('jwt', $subject)) && isset($r['public_key'])) {
            return $r['public_key'];
        }
        return false;
    }

    /**
     * Get a jti (JSON token identifier) by matching against the client_id, subject, audience and expiration.
     *
     * @param $client_id
     * Client identifier to match.
     *
     * @param $subject
     * The subject to match.
     *
     * @param $audience
     * The audience to match.
     *
     * @param $expiration
     * The expiration of the jti.
     *
     * @param $jti
     * The jti to match.
     *
     * @return
     * An associative array as below, and return NULL if the jti does not exist.
     * - issuer: Stored client identifier.
     * - subject: Stored subject.
     * - audience: Stored audience.
     * - expires: Stored expiration in unix timestamp.
     * - jti: The stored jti.
     */
    public function getJti($client_id, $subject, $audience, $expiration, $jti)
    {
        return $this->getObject('jti', $subject);
    }

    /**
     * Store a used jti so that we can check against it to prevent replay attacks.
     * @param $client_id
     * Client identifier to insert.
     *
     * @param $subject
     * The subject to insert.
     *
     * @param $audience
     * The audience to insert.
     *
     * @param $expiration
     * The expiration of the jti.
     *
     * @param $jti
     * The jti to insert.
     */
    public function setJti($client_id, $subject, $audience, $expires, $jti)
    {
        $r = [
            'id'=>$subject,
            'type'=>'jti',
            'token'=>$client_id,
            'options' => [
                'client_id'=>$client_id,
                'audience'=>$audience,
                'jti'=>$jti,
            ],
            'expires' => (is_int($expires)) ?date('Y-m-d H:i:s', $expires) :$expires,
        ];

        $Q = $this->tokenFinder;
        $Q::replace($r);
    }

    /**
     * @return
     * TRUE if the grant type requires a redirect_uri, FALSE if not
     */
    public function enforceRedirect()
    {
        $cfg = (($app=S::getApp()->studio) && isset($app['oauth2'])) ?$app['oauth2'] :[];

        return (isset($cfg['enforce_redirect'])) ?(bool)$cfg['enforce_redirect'] :false;
    }

    /**
     * Handle the creation of the authorization code.
     *
     * @param mixed  $client_id    - Client identifier related to the authorization code
     * @param mixed  $user_id      - User ID associated with the authorization code
     * @param string $redirect_uri - An absolute URI to which the authorization server will redirect the
     *                               user-agent to when the end-user authorization step is completed.
     * @param string $scope        - OPTIONAL Scopes to be stored in space-separated string.
     * @param string $id_token     - OPTIONAL The OpenID Connect id_token.
     * @return string
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4
     * @ingroup oauth2_section_4
     */
    public function createAuthorizationCode($client_id, $user_id, $redirect_uri, $scope = null, $id_token = null)
    {
        $code = $this->generateAuthorizationCode();
        $timeout = Server::config('auth_code_lifetime');
        if(!$timeout) $timeout = 3600;

        $r = [
            'id'=>$code,
            'type'=>'authorization_code',
            'token'=>$client_id,
            'user'=>$user_id,
            'options' => [
                'client_id'=>$client_id,
                'redirect_uri'=>$reirect_uri,
            ],
            'expires' => date('Y-m-d H:i:s', time()+$timeout),
        ];

        if($scope) $r['options']['scope'] = $scope;
        if($id_token) $r['options']['id_token'] = $id_token;

        $Q = $this->tokenFinder;
        $Q::replace($r);

        return $code;
    }

    /**
     * @param mixed $client_id
     * @return mixed
     */
    public function getPublicKey($client_id = null)
    {
        return $this->getObject('public_key', ['client_id'=>$client_id], 'public_key');
    }

    /**
     * @param mixed $client_id
     * @return mixed
     */
    public function getPrivateKey($client_id = null)
    {
        return $this->getObject('public_key', ['client_id'=>$client_id], 'private_key');
    }

    /**
     * @param mixed $client_id
     * @return mixed
     */
    public function getEncryptionAlgorithm($client_id = null)
    {
        return ($r=$this->getObject('public_key', ['client_id'=>$client_id], 'encryption_algorithm')) ?$r :'RS256';
    }

    /**
     * Generates an unique auth code.
     *
     * Implementing classes may want to override this function to implement
     * other auth code generation schemes.
     *
     * @return
     * An unique auth code.
     *
     * @ingroup oauth2_section_4
     */
    protected function generateAuthorizationCode()
    {
        return S::salt(40);
    }
}