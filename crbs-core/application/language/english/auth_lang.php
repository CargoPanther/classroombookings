<?php

$lang['auth.log_in'] = 'Log in';
$lang['auth.log_out'] = 'Log out';

$lang['auth.bad_credentials'] = 'Incorrect username and/or password.';
$lang['auth.login_required'] = 'You must be logged in to access that page.';
$lang['auth.permission_required'] = 'You do not have required permissions to access that page.';

$lang['auth.authentication'] = 'Authentication';
$lang['auth.ldap.ldap'] = 'LDAP';
$lang['auth.ldap.connection'] = 'Connection';
$lang['auth.ldap.search'] = 'Search';
$lang['auth.ldap.user_attribute_mapping'] = 'User attribute mapping';
$lang['auth.ldap.user_assignments'] = 'Default user assignments';

$lang['auth.ldap.user_attribute_mapping.hint.1'] = 'When you use a search filter to find the authenticating user, you can populate the following classroombookings user details with attributes found in LDAP each time they log in.';
$lang['auth.ldap.user_attribute_mapping.hint.2'] = 'Combine multiple LDAP attributes by adding a colon before the attribute name, for example - ';
$lang['auth.ldap.user_attribute_mapping.hint.3'] = 'Leave these fields blank to disable automatic population.';

$lang['auth.ldap.demo_notice'] = "In the demo mode, the verification feature and ability to enable LDAP authentication are disabled to prevent account lock-outs and protect against abuse.";

$lang['auth.ldap.field.ldap_enabled'] = 'Enable';
$lang['auth.ldap.field.ldap_enabled.title'] = 'Use LDAP to authenticate users.';

$lang['auth.ldap.field.ldap_create_users'] = 'Create users';
$lang['auth.ldap.field.ldap_create_users.title'] = 'Automatically create user accounts on successful authentication.';
$lang['auth.ldap.field.ldap_create_users.hint.1'] = 'When enabled, any valid credentials returned from an LDAP authentication attempt will automatically create a classroombookings account with the Role and/or Department specified below.';
$lang['auth.ldap.field.ldap_create_users.hint.2'] = 'When not enabled, only users who have an existing account in classroombookings will be authenticated.';

$lang['auth.ldap.field.ldap_server'] = 'Server';
$lang['auth.ldap.field.ldap_server.hint'] = 'Hostname or IP address.';

$lang['auth.ldap.field.ldap_port'] = 'Port';
$lang['auth.ldap.field.ldap_port.hint'] = 'Standard ports are 389 (non-SSL) or 636 (SSL).';

$lang['auth.ldap.field.ldap_version'] = 'Protocol version';
$lang['auth.ldap.field.ldap_version.hint'] = 'Usually 3.';

$lang['auth.ldap.field.ldap_use_tls'] = 'Use TLS';
$lang['auth.ldap.field.ldap_ignore_cert'] = 'Ignore certificate';
$lang['auth.ldap.field.ldap_bind_dn_format'] = 'Bind DN format';
$lang['auth.ldap.field.ldap_bind_dn_format.hint'] = 'This will vary depending on your server and configuration. The tag `:user` will be replaced with the authenticating user. Some common formats are:';

$lang['auth.ldap.field.ldap_base_dn'] = 'Base DN';
$lang['auth.ldap.field.ldap_search_filter'] = 'Search filter';
$lang['auth.ldap.field.ldap_search_filter.hint'] = 'The tag `:user` will be replaced by the user logging in.';

$lang['auth.ldap.test.title'] = 'Verify settings';
$lang['auth.ldap.test.hint.1'] = "Configure the settings on the left then provide a username and password in this box to verify access. You do not need to click Save before verifying the settings here.";
$lang['auth.ldap.test.hint.2'] = "These credentials are passed directly to the LDAP server that you have specified and are never stored by classroombookings.";
$lang['auth.ldap.test.verify'] = "Verify credentials";
$lang['auth.ldap.test.verifying'] = "Testing connection";

$lang['auth.ldap.test.bind_dn'] = 'Bind DN';
$lang['auth.ldap.test.search_filter'] = 'Search filter';
$lang['auth.ldap.test.auth_success'] = 'Authentication success!';

$lang['auth.ldap.save.success'] = 'The LDAP settings have been updated.';

$lang['auth.externallogintitle'] = 'Other Login options';
$lang['auth.microsoft.title'] = 'Microsoft SSO';
$lang['auth.microsoft.loginbutton'] = 'Login in with Microsoft';
$lang['auth.microsoft.configuration'] = 'Microsoft configuration';
$lang['auth.microsoft.user_provisioning'] = 'User provisioning';
$lang['auth.microsoft.field.enable'] = 'Enable Microsoft sign-in';
$lang['auth.microsoft.field.auto_login'] = 'Automatically start Microsoft sign-in';
$lang['auth.microsoft.field.auto_login_hint'] = 'When enabled, visiting the login page immediately redirects to Microsoft sign-in. The backup page shown here accepts Classroombookings usernames and passwords only, does not start Microsoft sign-in, and is not linked from the regular login page.';
$lang['auth.microsoft.field.client_id'] = 'Client ID';
$lang['auth.microsoft.field.client_secret'] = 'Client secret';
$lang['auth.microsoft.field.redirect_uri'] = 'Redirect URI';
$lang['auth.microsoft.field.redirect_uri_hint'] = 'Enter the exact redirect URI configured in Azure AD. It should normally point to /login/microsoft.';
$lang['auth.microsoft.redirect_uri.current'] = 'Current redirect URI';
$lang['auth.microsoft.field.authority'] = 'Azure authority';
$lang['auth.microsoft.field.authority_hint'] = 'Use common, organizations, consumers, or your tenant ID. Example: common or contoso.onmicrosoft.com.';
$lang['auth.microsoft.field.scopes'] = 'Scopes';
$lang['auth.microsoft.field.scopes_hint'] = 'Space-separated scopes. Use User.Read by default; include offline_access if you want refresh tokens.';
$lang['auth.microsoft.field.create_users'] = 'Create users automatically';
$lang['auth.microsoft.field.create_users_hint'] = 'When enabled, users signing in with Microsoft will be provisioned if they do not already exist.';
$lang['auth.microsoft.demo_notice'] = 'In the demo mode, Microsoft sign-in cannot be enabled to prevent account lock-outs and protect against abuse.';
$lang['auth.microsoft.setup.title'] = 'Microsoft SSO setup guide';
$lang['auth.microsoft.setup.step.1'] = 'Open the Azure portal and register a new application under Azure Active Directory.';
$lang['auth.microsoft.setup.step.2'] = 'In Redirect URIs, add the Classroombookings callback URL exactly as shown here: ';
$lang['auth.microsoft.setup.step.3'] = 'Under Authentication, enable ID tokens and save the redirect URI. Ensure the URI matches your site and is HTTPS in production.';
$lang['auth.microsoft.setup.step.4'] = 'Grant API permissions for openid, profile, and email, then click Grant admin consent for your tenant.';
$lang['auth.microsoft.setup.step.5'] = 'Copy the Application (client) ID and a new Client secret into the fields below.';
$lang['auth.microsoft.setup.step.6'] = 'For the admin user, ensure the admin email exists in Classroombookings or enable automatic provisioning and assign the default admin role after first login.';
$lang['auth.microsoft.field.default_role_id'] = 'Default role ID';
$lang['auth.microsoft.field.default_role_id_hint'] = 'Enter the numeric role ID for new Microsoft users. If role names are not visible, use the ID shown in the Roles page URL or database record.';
$lang['auth.microsoft.field.default_department_id'] = 'Default department ID';
$lang['auth.microsoft.field.default_department_id_hint'] = 'Enter the numeric department ID for new Microsoft users. If department names are not visible, use the ID shown in the Departments page URL or database record.';
$lang['auth.microsoft.redirect_uri_invalid'] = 'The Redirect URI field must contain a valid URL.';
$lang['auth.microsoft.field.invalid_id'] = 'The {field} field must be a number greater than zero.';
$lang['auth.microsoft.save.failure'] = 'The Microsoft authentication settings could not be saved. Please try again.';
$lang['auth.microsoft.save.validation_failed'] = 'Validation failed. Please correct the errors below and try again.';
$lang['auth.microsoft.save.success'] = 'The Microsoft authentication settings have been updated.';
$lang['auth.microsoft.sign_in_failed'] = 'Microsoft sign-in failed.';
$lang['auth.microsoft.not_enabled'] = 'Microsoft authentication is not enabled.';
$lang['auth.microsoft.error.discovery_failed'] = 'Could not discover Azure AD endpoints. Please verify the authority setting.';
$lang['auth.microsoft.error.discovery_invalid_response'] = 'Invalid Azure AD discovery response.';
$lang['auth.microsoft.error.discovery_missing_authorization_endpoint'] = 'Azure AD discovery response is missing the authorization endpoint.';
$lang['auth.microsoft.error.discovery_missing_token_endpoint'] = 'Azure AD discovery response is missing the token endpoint.';
$lang['auth.microsoft.error.discovery_missing_userinfo_endpoint'] = 'Azure AD discovery response is missing the userinfo endpoint.';
$lang['auth.microsoft.error.missing_email'] = 'Microsoft did not provide an email address.';
$lang['auth.microsoft.error.invalid_state'] = 'The sign-in request state is invalid or expired.';
$lang['auth.microsoft.error.missing_auth_code'] = 'Microsoft did not return an authorization code.';
$lang['auth.save'] = 'Save';

$lang['auth.ldap.error.demo_mode'] = 'Feature disabled in demo mode.';
$lang['auth.ldap.error.no_module'] = 'The PHP LDAP module is not installed or enabled.';
$lang['auth.ldap.error.no_server_or_port'] = 'No server and/or port supplied.';
$lang['auth.ldap.error.no_socket_connection'] = 'Connection error or timed out.';
$lang['auth.ldap.error.invalid_ldap_uri'] = 'Invalid LDAP connection URI.';
$lang['auth.ldap.error.no_username_or_password'] = 'No username and/or password.';
$lang['auth.ldap.error.bind_error'] = 'LDAP bind error or bad username and/or password.';
$lang['auth.ldap.error.search_error'] = 'LDAP search error.';
$lang['auth.ldap.error.search_num_results_error'] = 'LDAP search did not return exactly one result.';
$lang['auth.ldap.error.search_get_entry_error'] = 'LDAP get search result entry error.';
$lang['auth.ldap.error.search_get_attributes_error'] = 'LDAP get user attributes error.';
