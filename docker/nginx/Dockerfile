FROM nginx:stable-alpine

# In ECS this container shares the task's network namespace with php-fpm,
# so php-fpm is reachable on 127.0.0.1:9000 (see ecs.conf).
COPY ecs.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
