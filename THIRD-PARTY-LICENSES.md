# 第三方许可清单

> 数据源：`composer.lock` + `package-lock.json`，非人工整理。
> 刷新方式：PHP 段 `composer licenses`；JS 段 `npm ls --license`。
> 更新日期：2026-10-08

## 结论

全部依赖为**宽松许可**，无 GPL / AGPL / LGPL / SSPL / Elastic / BSL 等传染性或商业限制许可，本项目不因依赖而承担开源义务。

需履行的义务只有两条：MIT / BSD / ISC 要求保留版权与许可声明；Apache-2.0 额外要求附带 LICENSE 副本、NOTICE 文件并标注改动过的文件。本仓库公开完整源码，即满足上述 notice 要求。

## 协议分布

| 协议 | 说明 | PHP | JS |
| --- | --- | --- | --- |
| [MIT](https://spdx.org/licenses/MIT.html) | 宽松，保留声明即可 | 83 | 134 |
| [BSD-3-Clause](https://spdx.org/licenses/BSD-3-Clause.html) | 宽松，保留声明即可 | 31 | 1 |
| [ISC](https://spdx.org/licenses/ISC.html) | 宽松，保留声明即可 | 0 | 6 |
| [Apache-2.0](https://spdx.org/licenses/Apache-2.0.html) | 宽松，保留声明即可；需附 LICENSE + NOTICE | 1 | 2 |
| [MPL-2.0](https://spdx.org/licenses/MPL-2.0.html) | 宽松，保留声明即可 | 0 | 12 |
| [0BSD](https://spdx.org/licenses/0BSD.html) | 宽松，保留声明即可 | 0 | 1 |
| BSD-3 **或** GPL-2.0/3.0 任选 | 双许可，本项目走 BSD-3 分支，无传染性 | 2 | 0 |

## PHP 依赖（运行时，117 包）

### [Apache-2.0](https://spdx.org/licenses/Apache-2.0.html)（1）

| 包 | 版本 |
| --- | --- |
| `phpoption/phpoption` | 1.10.0 |

### [BSD-3-Clause](https://spdx.org/licenses/BSD-3-Clause.html)（31）

| 包 | 版本 |
| --- | --- |
| `hamcrest/hamcrest-php` | v3.0.0 |
| `league/commonmark` | 2.10.0 |
| `league/config` | v1.2.0 |
| `mockery/mockery` | 1.6.15 |
| `nikic/php-parser` | v5.8.0 |
| `phar-io/manifest` | 2.0.4 |
| `phar-io/version` | 3.2.1 |
| `phpunit/php-code-coverage` | 11.0.12 |
| `phpunit/php-file-iterator` | 5.1.1 |
| `phpunit/php-invoker` | 5.0.1 |
| `phpunit/php-text-template` | 4.0.1 |
| `phpunit/php-timer` | 7.0.1 |
| `phpunit/phpunit` | 11.5.56 |
| `sebastian/cli-parser` | 3.0.2 |
| `sebastian/code-unit` | 3.0.3 |
| `sebastian/code-unit-reverse-lookup` | 4.0.1 |
| `sebastian/comparator` | 6.3.3 |
| `sebastian/complexity` | 4.0.1 |
| `sebastian/diff` | 6.0.2 |
| `sebastian/environment` | 7.2.1 |
| `sebastian/exporter` | 6.3.2 |
| `sebastian/global-state` | 7.0.2 |
| `sebastian/lines-of-code` | 3.0.1 |
| `sebastian/object-enumerator` | 6.0.1 |
| `sebastian/object-reflector` | 4.0.1 |
| `sebastian/recursion-context` | 6.0.3 |
| `sebastian/type` | 5.1.3 |
| `sebastian/version` | 5.0.2 |
| `theseer/tokenizer` | 1.3.1 |
| `tijsverkoyen/css-to-inline-styles` | v2.4.0 |
| `vlucas/phpdotenv` | v5.7.0 |

### BSD-3-Clause / GPL-2.0-only / GPL-3.0-only（2）

| 包 | 版本 |
| --- | --- |
| `nette/schema` | v1.3.6 |
| `nette/utils` | v4.1.5 |

### [MIT](https://spdx.org/licenses/MIT.html)（83）

| 包 | 版本 |
| --- | --- |
| `blade-ui-kit/blade-icons` | 1.10.1 |
| `brick/math` | 0.14.8 |
| `carbonphp/carbon-doctrine-types` | 3.2.0 |
| `dflydev/dot-access-data` | v3.0.3 |
| `doctrine/inflector` | 2.1.0 |
| `doctrine/lexer` | 3.0.1 |
| `dragonmantank/cron-expression` | v3.6.0 |
| `egulias/email-validator` | 4.0.4 |
| `fakerphp/faker` | v1.24.1 |
| `filp/whoops` | 2.18.4 |
| `fruitcake/php-cors` | v1.4.0 |
| `graham-campbell/result-type` | v1.2.0 |
| `guzzlehttp/guzzle` | 7.15.5 |
| `guzzlehttp/promises` | 2.5.3 |
| `guzzlehttp/psr7` | 2.13.1 |
| `guzzlehttp/uri-template` | v1.0.11 |
| `laravel/framework` | v12.68.0 |
| `laravel/pail` | v1.2.7 |
| `laravel/pint` | v1.30.4 |
| `laravel/prompts` | v0.3.24 |
| `laravel/sail` | v1.67.0 |
| `laravel/serializable-closure` | v2.0.16 |
| `laravel/tinker` | v2.11.1 |
| `league/flysystem` | 3.35.3 |
| `league/flysystem-local` | 3.35.3 |
| `league/mime-type-detection` | 1.17.0 |
| `league/uri` | 7.8.1 |
| `league/uri-interfaces` | 7.8.1 |
| `mallardduck/blade-lucide-icons` | 2.0.9 |
| `monolog/monolog` | 3.10.0 |
| `myclabs/deep-copy` | 1.14.0 |
| `nesbot/carbon` | 3.13.2 |
| `nunomaduro/collision` | v8.9.5 |
| `nunomaduro/termwind` | v2.4.0 |
| `psr/clock` | 1.0.0 |
| `psr/container` | 2.0.2 |
| `psr/event-dispatcher` | 1.0.0 |
| `psr/http-client` | 1.0.3 |
| `psr/http-factory` | 1.1.0 |
| `psr/http-message` | 2.0 |
| `psr/log` | 3.0.2 |
| `psr/simple-cache` | 3.0.0 |
| `psy/psysh` | v0.12.24 |
| `ralouphie/getallheaders` | 3.0.3 |
| `ramsey/collection` | 2.1.1 |
| `ramsey/uuid` | 4.9.3 |
| `staabm/side-effects-detector` | 1.0.5 |
| `symfony/clock` | v7.4.8 |
| `symfony/console` | v7.4.18 |
| `symfony/css-selector` | v7.4.18 |
| `symfony/deprecation-contracts` | v3.7.1 |
| `symfony/error-handler` | v7.4.17 |
| `symfony/event-dispatcher` | v7.4.17 |
| `symfony/event-dispatcher-contracts` | v3.7.1 |
| `symfony/finder` | v7.4.17 |
| `symfony/http-foundation` | v7.4.18 |
| `symfony/http-kernel` | v7.4.18 |
| `symfony/mailer` | v7.4.17 |
| `symfony/mime` | v7.4.18 |
| `symfony/polyfill-ctype` | v1.37.0 |
| `symfony/polyfill-intl-grapheme` | v1.41.0 |
| `symfony/polyfill-intl-idn` | v1.42.0 |
| `symfony/polyfill-intl-normalizer` | v1.42.0 |
| `symfony/polyfill-mbstring` | v1.38.2 |
| `symfony/polyfill-php80` | v1.37.0 |
| `symfony/polyfill-php83` | v1.41.0 |
| `symfony/polyfill-php84` | v1.38.1 |
| `symfony/polyfill-php85` | v1.41.0 |
| `symfony/polyfill-uuid` | v1.37.0 |
| `symfony/process` | v7.4.18 |
| `symfony/routing` | v7.4.18 |
| `symfony/service-contracts` | v3.7.3 |
| `symfony/string` | v7.4.15 |
| `symfony/translation` | v7.4.17 |
| `symfony/translation-contracts` | v3.7.1 |
| `symfony/uid` | v7.4.17 |
| `symfony/var-dumper` | v7.4.18 |
| `symfony/yaml` | v7.4.18 |
| `voku/portable-ascii` | 2.1.1 |
| `yansongda/artful` | v1.1.5 |
| `yansongda/laravel-pay` | v3.7.4 |
| `yansongda/pay` | v3.7.20 |
| `yansongda/supports` | v4.0.12 |

## JS 构建依赖（156 包）

全部为 **devDependencies，仅构建期使用，不随站点分发**——SSR 模式无客户端 JS bundle，产物只是 minified CSS。故该段无运行时 notice 义务。

### [0BSD](https://spdx.org/licenses/0BSD.html)（1）

`tslib@2.8.1`

### [Apache-2.0](https://spdx.org/licenses/Apache-2.0.html)（2）

`detect-libc@2.1.2` · `rxjs@7.8.2`

### [BSD-3-Clause](https://spdx.org/licenses/BSD-3-Clause.html)（1）

`source-map-js@1.2.1`

### [ISC](https://spdx.org/licenses/ISC.html)（6）

`cliui@8.0.1` · `get-caller-file@2.0.5` · `graceful-fs@4.2.11` · `picocolors@1.1.1` · `y18n@5.0.8` · `yargs-parser@21.1.1`

### [MIT](https://spdx.org/licenses/MIT.html)（134）

`@esbuild/aix-ppc64@0.28.2` · `@esbuild/android-arm@0.28.2` · `@esbuild/android-arm64@0.28.2` · `@esbuild/android-x64@0.28.2` · `@esbuild/darwin-arm64@0.28.2` · `@esbuild/darwin-x64@0.28.2` · `@esbuild/freebsd-arm64@0.28.2` · `@esbuild/freebsd-x64@0.28.2` · `@esbuild/linux-arm@0.28.2` · `@esbuild/linux-arm64@0.28.2` · `@esbuild/linux-ia32@0.28.2` · `@esbuild/linux-loong64@0.28.2` · `@esbuild/linux-mips64el@0.28.2` · `@esbuild/linux-ppc64@0.28.2` · `@esbuild/linux-riscv64@0.28.2` · `@esbuild/linux-s390x@0.28.2` · `@esbuild/linux-x64@0.28.2` · `@esbuild/netbsd-arm64@0.28.2` · `@esbuild/netbsd-x64@0.28.2` · `@esbuild/openbsd-arm64@0.28.2` · `@esbuild/openbsd-x64@0.28.2` · `@esbuild/openharmony-arm64@0.28.2` · `@esbuild/sunos-x64@0.28.2` · `@esbuild/win32-arm64@0.28.2` · `@esbuild/win32-ia32@0.28.2` · `@esbuild/win32-x64@0.28.2` · `@jridgewell/gen-mapping@0.3.13` · `@jridgewell/remapping@2.3.5` · `@jridgewell/resolve-uri@3.1.2` · `@jridgewell/sourcemap-codec@1.6.0` · `@jridgewell/trace-mapping@0.3.31` · `@napi-rs/lzma-linux-x64-gnu@1.5.1` · `@rollup/rollup-android-arm-eabi@4.63.1` · `@rollup/rollup-android-arm64@4.63.1` · `@rollup/rollup-darwin-arm64@4.63.1` · `@rollup/rollup-darwin-x64@4.63.1` · `@rollup/rollup-freebsd-arm64@4.63.1` · `@rollup/rollup-freebsd-x64@4.63.1` · `@rollup/rollup-linux-arm-gnueabihf@4.63.1` · `@rollup/rollup-linux-arm-musleabihf@4.63.1` · `@rollup/rollup-linux-arm64-gnu@4.63.1` · `@rollup/rollup-linux-arm64-musl@4.63.1` · `@rollup/rollup-linux-loong64-gnu@4.63.1` · `@rollup/rollup-linux-loong64-musl@4.63.1` · `@rollup/rollup-linux-ppc64-gnu@4.63.1` · `@rollup/rollup-linux-ppc64-musl@4.63.1` · `@rollup/rollup-linux-riscv64-gnu@4.63.1` · `@rollup/rollup-linux-riscv64-musl@4.63.1` · `@rollup/rollup-linux-s390x-gnu@4.63.1` · `@rollup/rollup-linux-x64-gnu@4.63.1` · `@rollup/rollup-linux-x64-musl@4.63.1` · `@rollup/rollup-openbsd-x64@4.63.1` · `@rollup/rollup-openharmony-arm64@4.63.1` · `@rollup/rollup-win32-arm64-msvc@4.63.1` · `@rollup/rollup-win32-ia32-msvc@4.63.1` · `@rollup/rollup-win32-x64-gnu@4.63.1` · `@rollup/rollup-win32-x64-msvc@4.63.1` · `@tailwindcss/node@4.3.3` · `@tailwindcss/oxide@4.3.3` · `@tailwindcss/oxide-android-arm64@4.3.3` · `@tailwindcss/oxide-darwin-arm64@4.3.3` · `@tailwindcss/oxide-darwin-x64@4.3.3` · `@tailwindcss/oxide-freebsd-x64@4.3.3` · `@tailwindcss/oxide-linux-arm-gnueabihf@4.3.3` · `@tailwindcss/oxide-linux-arm64-gnu@4.3.3` · `@tailwindcss/oxide-linux-arm64-musl@4.3.3` · `@tailwindcss/oxide-linux-x64-gnu@4.3.3` · `@tailwindcss/oxide-linux-x64-musl@4.3.3` · `@tailwindcss/oxide-wasm32-wasi@4.3.3` · `@tailwindcss/oxide-win32-arm64-msvc@4.3.3` · `@tailwindcss/oxide-win32-x64-msvc@4.3.3` · `@tailwindcss/vite@4.3.3` · `@types/estree@1.0.9` · `agent-base@6.0.2` · `ansi-regex@5.0.1` · `ansi-styles@4.3.0` · `asynckit@0.4.0` · `axios@1.20.0` · `call-bind-apply-helpers@1.0.2` · `chalk@4.1.2` · `supports-color@8.1.1` · `color-convert@2.0.1` · `color-name@1.1.4` · `combined-stream@1.0.8` · `concurrently@9.2.4` · `debug@4.4.3` · `delayed-stream@1.0.0` · `dunder-proto@1.0.1` · `emoji-regex@8.0.0` · `enhanced-resolve@5.24.5` · `es-define-property@1.0.1` · `es-errors@1.3.0` · `es-object-atoms@1.1.2` · `es-set-tostringtag@2.1.0` · `esbuild@0.28.2` · `escalade@3.2.0` · `fdir@6.5.0` · `follow-redirects@1.16.0` · `form-data@4.0.6` · `fsevents@2.3.3` · `function-bind@1.1.2` · `get-intrinsic@1.3.0` · `get-proto@1.0.1` · `gopd@1.2.0` · `has-flag@4.0.0` · `has-symbols@1.1.0` · `has-tostringtag@1.0.2` · `hasown@2.0.4` · `https-proxy-agent@5.0.1` · `is-fullwidth-code-point@3.0.0` · `jiti@2.7.0` · `laravel-vite-plugin@2.1.0` · `magic-string@0.30.21` · `math-intrinsics@1.1.0` · `mime-db@1.52.0` · `mime-types@2.1.35` · `ms@2.1.3` · `nanoid@3.3.18` · `picomatch@2.3.2` · `postcss@8.5.26` · `proxy-from-env@2.1.0` · `require-directory@2.1.1` · `rollup@4.63.1` · `shell-quote@1.9.0` · `string-width@4.2.3` · `strip-ansi@6.0.1` · `tailwindcss@4.3.3` · `tapable@2.3.3` · `tinyglobby@0.2.17` · `tree-kill@1.2.2` · `vite@7.3.6` · `vite-plugin-full-reload@1.2.0` · `wrap-ansi@7.0.0` · `yargs@17.7.2`

### [MPL-2.0](https://spdx.org/licenses/MPL-2.0.html)（12）

`lightningcss@1.32.0` · `lightningcss-android-arm64@1.32.0` · `lightningcss-darwin-arm64@1.32.0` · `lightningcss-darwin-x64@1.32.0` · `lightningcss-freebsd-x64@1.32.0` · `lightningcss-linux-arm-gnueabihf@1.32.0` · `lightningcss-linux-arm64-gnu@1.32.0` · `lightningcss-linux-arm64-musl@1.32.0` · `lightningcss-linux-x64-gnu@1.32.0` · `lightningcss-linux-x64-musl@1.32.0` · `lightningcss-win32-arm64-msvc@1.32.0` · `lightningcss-win32-x64-msvc@1.32.0`

## 其他

- **字体**：未自托管任何字体文件，也无 CDN 引入，`--font-serif` / `--font-sans` 栈中的字体名加载不到时走系统 fallback，故无字体许可义务（Noto / OFL 亦为宽松许可）。
- **图片**：`public/` 下无第三方图片；用户上传内容走 `storage/`。
- **图标**：Lucide 图标数据随 `mallardduck/blade-lucide-icons`（MIT）分发，Lucide 本体为 ISC。
- **CI**：`actions/checkout`、`actions/setup-node`、`shivammathur/setup-php` 均为 MIT。
