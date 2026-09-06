# 私域引流宝 运行环境
# PHP 7.4 + Apache，项目代码通过 bind mount 挂载，镜像内不含业务代码
FROM php:7.4-apache

# 项目使用 PDO(pdo_mysql) 和 mysqli（安装器用 mysqli 建表）
RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite
