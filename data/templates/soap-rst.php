<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */

use Studio as S;

if(!isset($timeout)) $timeout = 7200;
$t = time();

$action = 'Issue';
$action = 'Issue';
$tokenType = 'http://docs.oasis-open.org/wss/oasis-wss-saml-token-profile-1.1#SAMLV1.1';

?><s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
<s:Header>
  <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
    <wsu:Timestamp xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="_0">
      <wsu:Created><?php echo gmdate('Y-m-d\TH:i:s', $t); ?>Z</wsu:Created>
      <wsu:Expires><?php echo gmdate('Y-m-d\TH:i:s', $t+$timeout); ?>Z</wsu:Expires>
    </wsu:Timestamp>
    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="Me">
      <wsse:Username><?php echo S::xml($username) ?></wsse:Username>
      <wsse:Password><?php echo S::xml($password) ?></wsse:Password>
    </wsse:UsernameToken>
  </wsse:Security>
  <wsa:To xmlns:wsa="http://www.w3.org/2005/08/addressing"><?php echo S::xml($rst) ?></wsa:To>
  <wsa:Action xmlns:wsa="http://www.w3.org/2005/08/addressing">http://docs.oasis-open.org/ws-sx/ws-trust/200512/RST/<?php echo S::xml($action) ?></wsa:Action>
</s:Header>
<s:Body>
  <wst:RequestSecurityToken xmlns:wst="http://docs.oasis-open.org/ws-sx/ws-trust/200512">
    <wst:TokenType><?php echo S::xml($tokenType) ?></wst:TokenType>
    <wst:RequestType>http://docs.oasis-open.org/ws-sx/ws-trust/200512/<?php echo S::xml($action) ?></wst:RequestType>
    <wsp:AppliesTo xmlns:wsp="http://schemas.xmlsoap.org/ws/2004/09/policy">
      <wsa:EndpointReference xmlns:wsa="http://www.w3.org/2005/08/addressing">
        <wsa:Address><?php echo S::xml($dsn) ?></wsa:Address>
      </wsa:EndpointReference>
    </wsp:AppliesTo>
    <wst:KeyType>http://docs.oasis-open.org/ws-sx/ws-trust/200512/Bearer</wst:KeyType>
  </wst:RequestSecurityToken>
</s:Body>
</s:Envelope>