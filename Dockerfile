# Builds a docker image for CloudlFlare DYN-DNS
FROM phusion/baseimage:0.11
MAINTAINER Kevin Powell <shatterpoint2000@gmail.com>
# Based on the work of Mace Capri <macecapri@gmail.com>

#########################################
##        ENVIRONMENTAL CONFIG         ##
#########################################
# Set correct environment variables
ENV HOME="/root" LC_ALL="C.UTF-8" LANG="en_US.UTF-8" LANGUAGE="en_US.UTF-8"
ARG cf_email=your@email.com
ENV CF_EMAIL=$cf_email
ARG cf_host=domain.com
ENV CF_HOST=$cf_host
ARG cf_prefixes=,www,git
ENV CF_PREFIXES=$cf_prefixes
ARG cf_api=xxxxxxxxxxxxxxx
ENV CF_API=$cf_api
ARG cf_api_ca_origin=xxxxxxxxxxxxxxx
ENV CF_API_CA_ORIGIN=$cf_api_ca_origin


# Use baseimage-docker's init system
CMD ["/sbin/my_init"]

#########################################
##    RUN  ENVIORMENT INSTALL SCRIPT   ##
#########################################
COPY install.sh /tmp/
RUN chmod +x /tmp/install.sh && sleep 1 && /tmp/install.sh && rm /tmp/install.sh

#########################################
##      ADD CLOUDFLARE UPDATE API      ##
#########################################
ADD updateip.php /root/

#########################################
##       Clean up APT when done        ##
#########################################
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*