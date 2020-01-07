# 本地环境队列运行容器+crontab
FROM harbor.uuzu.com/information/php:7.2-fpm-alpine

RUN sed -i 's/pm\.max_children\s=\s5/pm\.max_children=20/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm\.start_servers\s=\s2/pm\.start_servers=5/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/;pm\.max_requests\s=\s500/pm\.max_requests=5000/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm\.max_spare_servers\s=\s3/pm\.max_spare_servers=20/g' /usr/local/etc/php-fpm.d/www.conf

RUN echo 'upload_max_filesize = 100M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
RUN echo 'post_max_size = 150M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
