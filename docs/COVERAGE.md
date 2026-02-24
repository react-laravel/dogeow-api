# PHP 代码覆盖率检查

本项目要求 **100% 的代码覆盖率**，确保所有代码都经过测试。

## 确保已安装Xdebug扩展

```bash
# 检查Xdebug是否已安装
php -m | grep xdebug
```

## 快速开始

### 1. 运行测试并生成覆盖率报告

```bash
# 运行所有测试并生成覆盖率报告
composer run test:coverage
```

### 2. 检查覆盖率是否达到100%

```bash
# 检查覆盖率要求
composer run test:coverage-check
```

### 3. 一键运行测试和检查

```bash
# 运行测试并检查覆盖率
composer run test:coverage-full
```

## 覆盖率报告

运行测试后，覆盖率报告会生成在以下位置：

- **HTML报告**: `coverage/html/` - 可在浏览器中查看详细的覆盖率信息
- **Clover XML**: `coverage/clover.xml` - 用于CI/CD和代码质量工具
- **文本报告**: `coverage/coverage.txt` - 简单的文本格式报告

## 查看覆盖率报告

```bash
# 在浏览器中打开HTML报告
open coverage/html/index.html
```

## CI/CD 集成

项目已配置GitHub Actions来自动检查覆盖率：

- 每次推送到 `main` 或 `develop` 分支时会自动运行
- 每次创建Pull Request时会自动运行
- 如果覆盖率低于100%，CI会失败

## 覆盖率检查脚本

`scripts/check-coverage.php` 脚本会：

1. 检查覆盖率文件是否存在
2. 解析覆盖率数据
3. 计算总体覆盖率
4. 显示详细的覆盖率信息
5. 列出未完全覆盖的文件
6. 如果覆盖率低于100%，脚本会失败

## 排除的文件

以下目录和文件被排除在覆盖率检查之外：

- `app/Console/` - 控制台命令
- `app/Exceptions/` - 异常处理
- `app/Http/Middleware/` - 中间件
- `app/Providers/` - 服务提供者

## 提高覆盖率

如果覆盖率未达到100%：

1. 查看覆盖率报告，找出未覆盖的代码
2. 为未覆盖的代码编写测试
3. 重新运行测试和检查

## 常用命令

```bash
# 运行所有测试
composer run test

# 运行测试并生成覆盖率报告
composer run test:coverage

# 检查覆盖率要求
composer run test:coverage-check

# 运行测试并检查覆盖率
composer run test:coverage-full

# 只运行单元测试
vendor/bin/phpunit --testsuite=Unit

# 只运行功能测试
vendor/bin/phpunit --testsuite=Feature
```