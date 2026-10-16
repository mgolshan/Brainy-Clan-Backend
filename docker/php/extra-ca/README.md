Extra CA certificates for the **development** image only.

If your machine inspects TLS traffic (antivirus "HTTPS scanning", a corporate proxy), PHP inside the
container cannot verify outbound HTTPS (`cURL error 60: unable to get local issuer certificate`)
because the inspecting product signs every site with its own root. Export that root certificate
(Base-64 / PEM) into this folder as `<name>.crt` and rebuild: `docker compose build app`, then
`docker compose --profile workers up -d`. Alternatively turn off HTTPS scanning for Docker.

`*.crt` files here are git-ignored and never used by the production image.
