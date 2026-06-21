FROM nginx:latest
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/traffic_collect.sh /traffic_collect.sh
RUN chmod +x /traffic_collect.sh
