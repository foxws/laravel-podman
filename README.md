# This is my package laravel-podman

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-podman/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/foxws/laravel-podman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-podman/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/foxws/laravel-podman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-podman.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-podman)

This aims to be a [Laravel Sail](https://github.com/laravel/sail) clone, with a few additional changes:

- Only [Podman](https://podman.io/) is supported
- It works in rootless mode
- It does require a certificate (free, open-source and easy using `mkcert`)
- It comes with a clone of `sail` utility, named `pod`
- It can run multiple sites because it's using Nginx as webserver
- It's build upon [Alpine Linux](https://www.alpinelinux.org/) instead of Ubuntu, making it faster to build and more lightweight
- By default it includes Nginx, PHP 8.3, NPM, Mariadb, Meilisearch, Redis, Soketi, and Mailpit. However, one can easily modify `docker-compose.yml` and `Dockerfile`'s'.

## Installation

You can install the package via composer:

```bash
composer require foxws/laravel-podman
```

## Usage

### mkcert

> **NOTE:** See <https://github.com/FiloSottile/mkcert> for details and installation.

Create a script to manage your local certificates, e.g. `~/Code/dev/cert.sh`, and replace `192.168.1.100` with the device IP-addresses:

```bash
#!/bin/sh
mkcert -install \
&& mkcert -key-file key.pem -cert-file cert.pem \
  laravel.test *.laravel.test \
  192.168.1.100 \
  127.0.0.1 ::1
```

Execute the script:

```bash
chmod +x ~/Code/dev/cert.sh
./cert.sh
```

Generate an one-time `dhparam.pem` file:

```bash
openssl dhparam -out dhparam.pem 2048
```

> **TIP:** You may want to setup [mobile devices](https://github.com/FiloSottile/mkcert#mobile-devices).

Copy the generated `key.pem`, `dhparam.pem` and `cert.pem` to the `docker/ssl` directory of your projects root.

### Interact

Use the `pod` utility to control the Podman instances:

```bash
bin/pod help
bin/pod build --no-cache
bin/pod up -d
bin/pod shell
```

When using zsh, one may want to configure an alias in `~/.zshrc`:

```zsh
alias pod='[ -f pod ] && sh pod || sh bin/pod'
```

Your project should now listen on <https://laravel.test>.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [francoism90](https://github.com/foxws)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
