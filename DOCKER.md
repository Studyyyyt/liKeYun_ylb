# Docker 部署指南（群晖 NAS / 任意 Linux 服务器）

私域引流宝（liKeYun_ylb）v2.4.6 的 Docker Compose 部署教程。适用于群晖 NAS（x86_64）以及任何能跑 Docker 的 Linux 服务器。

> 本方案的特点：**源码 bind mount 进容器**——改代码不用重建镜像，刷新即生效；**所有持久化数据落在项目目录 `./data/`**——清数据 = 删文件夹。

## 一、文件说明

| 文件 | 作用 |
|---|---|
| `Dockerfile` | web 运行环境：PHP 7.4 + Apache + pdo_mysql + mysqli + rewrite |
| `docker-compose.yml` | 两个服务：`web`（本镜像）+ `db`（mysql:5.7，用 NAS 上已有镜像） |
| `.dockerignore` | 构建时排除无关文件 |
| `ylb-web_2.4.6.tar` | 预构建的 amd64 镜像包（不想在 NAS 上构建就用它） |

## 二、两种启动方式

### 方式 A：导入现成镜像（推荐，NAS 不联网构建）

1. 把整个项目目录（含 `ylb-web_2.4.6.tar`）上传到 NAS，例如 `/volume1/docker/ylb`
2. Container Manager → **映像** → 操作 → 导入 → 选择 `ylb-web_2.4.6.tar`
   （或 SSH 执行 `docker load -i ylb-web_2.4.6.tar`）
3. Container Manager → **项目** → 新增 → 选择 `/volume1/docker/ylb` → 自动识别 `docker-compose.yml` → 启动

### 方式 B：在 NAS 上直接构建

把 `docker-compose.yml` 里 web 服务改一下（去掉 `image:`、放开 `build:`）：

```yaml
  web:
    build: .
```

在项目里点构建/启动即可（需要 NAS 能拉取 `php:7.4-apache` 基础镜像）。

## 三、安装系统

1. 浏览器访问 `http://NAS的IP:8088/install/`（端口以 compose 里映射为准，默认写的 8080，被占用就换 8088）
2. 安装页填写：

| 字段 | 填写 | 说明 |
|---|---|---|
| 数据库服务器 | `db` | **必须填 compose 服务名**。容器内 localhost 指容器自己，填 localhost 或 NAS IP 都连不上 |
| 数据库名 | `ylb` | compose 已自动建库 |
| 数据库账号 | `root` | |
| 数据库密码 | `root123` | 对应 `MYSQL_ROOT_PASSWORD`，生产环境务必修改 |
| 管理员账号/密码 | 自定 | 密码不能含中文和特殊字符（安装器有校验） |
| 目录级别 | **根目录** | 项目挂载在 web 根，选根目录 |

3. 安装成功后**立刻删除项目目录里的 `install/` 文件夹**（不删别人可随时重装覆盖你的系统）
4. 访问后台：`http://NAS的IP:8088/console/index/`

## 四、数据存在哪里（全部在项目目录下）

| 宿主机路径 | 内容 |
|---|---|
| `./data/mysql/` | MySQL 数据库文件 |
| `./data/sessions/` | PHP 登录会话 |
| `./data/logs/apache2/` | Apache 访问/错误日志 |
| `./`（项目根） | 源码 + 安装生成的 `console/Db.php` + 二维码图片 `upload/` |

没有使用 Docker 命名卷，数据全在看得见的地方。

## 五、日常操作

```bash
cd /volume1/docker/ylb

# 启动 / 停止 / 重启
sudo docker compose up -d
sudo docker compose down
sudo docker compose restart

# 看日志
sudo docker compose logs -f web
cat data/logs/apache2/error.log
```

**改代码**：直接编辑项目目录里的 PHP/HTML/JS 文件，保存刷新即生效，不需要动容器、不需要重建镜像。
（只有改 `Dockerfile` 本身——比如加 PHP 扩展——才需要 `docker compose up -d --build`）

## 六、彻底重装

```bash
cd /volume1/docker/ylb
sudo docker compose down
sudo rm -rf data/ install/install.lock console/Db.php
sudo docker compose up -d
```

然后重新访问 `/install/` 安装，装完删 `install/` 目录。

## 七、常见问题

### 1. 启动报 `Bind for 0.0.0.0:8080 failed: port is already allocated`

8080 被其它容器/服务占用。改 `docker-compose.yml` 里 web 的端口映射（如 `"8088:80"`），重启项目。查占用：

```bash
sudo docker ps --format "table {{.Names}}\t{{.Ports}}" | grep 8080
```

### 2. 后台侧边栏中文乱码（`æ•°æ®` 这类乱码）

原因：安装器的 mysqli 连接未显式设置字符集，MySQL 服务端默认 latin1 导致中文入库即乱码。
本仓库的 `docker-compose.yml` 已通过 `--character-set-server=utf8mb4` 修复。
**但已经乱掉的数据不可逆，必须清库重装**（按第六节操作后重新安装）。

### 3. 提示「请勿重复安装」

安装锁未删。删除 `install/install.lock` 和 `console/Db.php` 后刷新安装页。

### 4. 安装页提示目录不可写

File Station 里给 `console/`、`upload/`（没有就建一个）目录设置可写权限；或 SSH：

```bash
cd /volume1/docker/ylb
chmod -R 777 console upload
```

### 5. 想改 MySQL root 密码

改 compose 里 `MYSQL_ROOT_PASSWORD` 后，需同时删除 `./data/mysql/` 重建（密码随初始化写入），并重装系统；或装完后在 MySQL 里改密码并同步修改 `console/Db.php`。

## 八、架构备注

- 镜像基于 `php:7.4-apache`（amd64），只含运行环境，不含业务代码
- 项目本身是纯 PHP 7.4 + MySQL，二维码由前端 `qrcode.min.js` 生成，服务端不需要 GD 库
- 如果 NAS 是 ARM 架构（DS220j 等 j 系列）：`mysql:5.7` 官方无 arm64 镜像，需将 db 换成 `mariadb:10.6`，并重新以 `--platform linux/arm64` 构建 web 镜像
