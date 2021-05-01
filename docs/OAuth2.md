<!--
---
title: OAuth2 Authentication
...
-->

# OAuth2 Authentication


## Configuration options

OAuth2 component configuration should be set under `studio.oauth2`:

|      Configuration Parameter      |                                  Description                                  |   Default value    |
|-----------------------------------|-------------------------------------------------------------------------------|--------------------|
| client_secret_basic               | Allows basic HTTP authentication on the token endpoint                        | true               |
| client_secret_post                | Allows POST authentication on the token endpoint                              | true               |
| unique_access_token               | Deletes older access tokens when creating a new one for the current client_id | false              |
| client_auth_bearer                | Allows Bearer HTTP authentication on the authenticated endpoints              | true               |
| client_auth_post                  | Allows POST authentication on the authenticated endpoints                     | true               |
| use_jwt_access_tokens             | false                                                                         |                    |
| jwt_extra_payload_callable        | ~                                                                             |                    |
| store_encrypted_token_string      | true                                                                          |                    |
| use_openid_connect                | true                                                                          |                    |
| id_lifetime                       | 3600                                                                          |                    |
| access_lifetime                   | 3600                                                                          |                    |
| www_realm                         | Service                                                                       |                    |
| token_param_name                  | access_token                                                                  |                    |
| token_bearer_header_name          | Bearer                                                                        |                    |
| enforce_state                     | true                                                                          |                    |
| require_exact_redirect_uri        | true                                                                          |                    |
| allow_implicit                    | false                                                                         |                    |
| allow_credentials_in_request_body | true                                                                          |                    |
| allow_public_clients              | true                                                                          |                    |
| always_issue_new_refresh_token    | false                                                                         |                    |
| unset_refresh_token_after_use     | true                                                                          |                    |
| auth_code_lifetime                | 3600                                                                          |                    |
| default_scope                     | basic openid                                                                  |                    |
| supported_scopes                  | [ basic, openid ]                                                             |                    |
| grant_types                       | [ authorization_code, client_credentials, jwt_bearer, refresh_token,          | user_credentials ] |
| response_types                    | [ token, id_token, code, code id_token, id_token token ]                      |                    |

## Authentication Options

Valid options for requesting a an access token:

- client_secret_basic
- client_secret_post
- client_jwt - @todo

- **Authorization Code** user authenticates with the IdP and receives an authorization code, which should be exchanged for a authtication token at the server.
- **Implicit** authentication is performed at the service and redirected back to the javascript app, no secret is stored, only the authorization token.
- **Client Credentials**

## Testing

We need to create a private key for testing this app:

