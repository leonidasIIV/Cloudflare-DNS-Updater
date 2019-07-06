# Cloudflare-DNS-Updater
A simple docker project to update DNS records with the current image's public ip address.

Usage:

To use, run the Docker container with your CloudFlare API credentials found on your CloudFlare account page:

docker run --net="host" --name="cloudflare dns updater" -e "CF_EMAIL=your@cloudflare_email.com" -e "CF_HOST=sub.domain.com" -e "CF_API=xxxxxxxxxxxxxx" leonidasiiv/cloudflare-dns-updater

To run in the background, add the -d switch after docker run.
