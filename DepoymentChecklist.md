# Deployment Checklist

## Production deployment checklist

- Set the application environment to production. Confirm PHP errors and database errors are logged privately rather than displayed to users; protect logs from web access and rotate them.
- Terminate HTTPS at the application server or a trusted reverse proxy. Configure the app and proxy so HTTPS detection is reliable; production session cookies are `Secure` and `HttpOnly`. Do not enable secure cookies on an HTTP-only production site as a workaround—deploy HTTPS.
- Configure the web server to deny direct requests for application/configuration, source, Composer metadata, local config, logs, sessions, and database artifacts. The project root is the document root in the development Compose file; Apache `.htaccess` rules do not protect an nginx deployment. Prefer a document root containing only public assets and the front controller when packaging permits.
- Configure security response headers at the TLS-terminating server: at minimum `X-Content-Type-Options: nosniff`, a suitable `Referrer-Policy`, and a Content Security Policy tested against the application’s inline scripts and integrations. Enable HSTS only after HTTPS is fully enforced for the hostname and subdomains in scope.
- Keep database backups and local configuration outside the public document root, restrict filesystem permissions on `local/`, `uploads/`, cache, session, and log directories, and test restoration procedures.
- Pin container images to maintained patch versions or digests. Run Composer dependency auditing and update the bundled CodeIgniter 3 runtime and libraries through the project’s supported release process; do not edit vendored files as a substitute for upgrades.

## Microsoft SSO rollout

- On the Authentication → Microsoft SSO setup page, configure the Microsoft Entra **tenant GUID**. The `common`, `organizations`, `consumers`, and domain-name authorities are intentionally rejected. The app uses the tenant-specific OAuth endpoints and Graph `/me` to bind an enabled local account to the immutable Microsoft user object ID.
- Before enabling sign-in, verify that the tenant GUID, client ID, redirect URI, required `User.Read` scope, and tenant app registration are correct. Existing accounts are never linked by matching email or username. An authorized user administrator must obtain each account’s Microsoft Object ID from the configured Entra tenant and enter it in that user’s edit page. New accounts may be provisioned through the configured automatic user-creation option. Review account mappings before rollout; subsequent sign-ins must match the stored Object ID.
- Run the supported application upgrade workflow to apply the `20261015200000_add_users_microsoft_identity` migration before enabling the updated SSO flow on an existing installation. Fresh installations include this column in the install schema. Back up the database before migrations and test the change in staging.

## CSRF and session behavior

- CSRF protection is enabled globally. Keep same-origin state changes as POST requests and use the framework form helper so the token is included. Do not add URI exclusions without a documented protocol requirement and review.
- Session IDs rotate at successful authentication. `Secure` cookies are enabled outside the development environment, and `HttpOnly` is enabled in all environments. Ensure any reverse proxy forwards the original HTTPS scheme only from trusted proxy addresses.

This checklist complements application-level authorization; it does not replace a deployment-specific penetration test or dependency scan.
