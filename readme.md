<p align="center"><img src="https://lab.youthol.cn/default/static/media/youthol_logo_md@300x300.9309bfce.png" width="80px;"></p>


## 项目概述

+ 项目名称：youthAPI
+ 项目介绍：随着互联网的发展，前后端分离已是大势所趋。青春在线API是为青春在线各功能提供服务的一个API，它整合了青春在线的几乎所有后端程序。
+ 官方地址：https://youthapi.sdut.edu.cn/

## 运行环境

+ Nginx 1.8+ / Apache 2.4+
+ PHP 7.0+
+ Mysql 5.7+

## 开发环境部署/安装

本项目代码使用 PHP 框架 [Laravel 5.5](https://learnku.com/docs/laravel/5.5) 开发，依赖于[Composer](https://getcomposer.org/)

如果未安装Composer请先安装Composer

**1.克隆代码**

```bash
$ git clone https://github.com/youthol/youthAPI.git
```

**2.安装扩展包依赖**

```bash
$ composer install
```

**3.生成配置文件**

```bash
$ cp .env.example .env
```

**4.生成密钥**

```bash
$ php artisan key:generate
```

```bash
$ php artisan jwt:secret
```

## 功能

+ [事务中心预约系统](https://github.com/youthol/swzx-preordain) **(/preordain/~)**
+ 问卷调查系统 **(/ques/~)**
  + [用户端](https://github.com/youthol/sdut-survey)
  + [管理端](https://github.com/youthol/sdut-survey-admin)
+ [网上办公系统](https://github.com/youthol/youthoa) **(/oa/~)**
+ 学生服务 **(/service/~)**
  + [微信绑定](https://github.com/yhlchao/wechat_binding) **(/service/authorization    +       /service/user)**
  + [用电查询](https://github.com/yhlchao/wechat_elec-use) **(/service/elec)**
  + [宿舍卫生](https://github.com/yhlchao/wechat_hygiene) **(/service/hygiene)**
  + [考试时间](https://github.com/yhlchao/wechat_exam) **(/service/exam)**
  + [四六级查询](https://github.com/yhlchao/wechat_cet-score) **(/service/cet)**
  + [新生信息查询](https://github.com/youthol/newStudent) **(/service/cet)**

## 扩展包描述

| 扩展包                                                       | 一句话描述                         | 在本项目中的使用案例                                         |
| ------------------------------------------------------------ | ---------------------------------- | ------------------------------------------------------------ |
| [dingo/api](https://learnku.com/docs/dingo-api/2.0.0)        | API处理包                          | 全部                                                         |
| [tymon/jwt-auth](https://packagist.org/packages/tymon/jwt-auth) | 前后端交互时传递数据的格式         | 事务中心预约、问卷调查、办公系统、学生服务等功能的登录       |
| [guzzlehttp/guzzle](https://guzzle-cn.readthedocs.io/zh_CN/latest/) | 发送Http请求（用于爬虫和模拟登陆） | 学生服务的用电查询、教务处登录和网上服务大厅登录             |
| [jaeger/querylist](https://www.querylist.cc/)                | 内容采集工具，解析HTML DOM         | 学生服务的用电查询、教务处登录和网上服务大厅登录             |
| [maatwebsite/excel](https://laravel-excel.com/)              | Excel文件导入导出                  | 事务中心预约导出、调查问卷导出、办公系统用户导入导出、卫生成绩导入等 |
| [phpseclib/phpseclib](https://github.com/phpseclib/phpseclib) | 各种加密功能（本项目用于RSA加密）  | 教务处登录所用（获取Module和Exponent生成RSA公钥进行密码加密） |
| socialiteproviders/weixin                                    | 微信登录包                         | 微信登录绑定                                                 |
| [spatie/laravel-permission](https://github.com/laravel/socialite) | 权限管理系统                       | 办公系统                                                     |

