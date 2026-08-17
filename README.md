# Voyti Social Auth — social/OAuth2 login addon for Voyti

The optional social-login addon for [Voyti](https://github.com/YiiRocks/voyti), the Yii3 user-management extension. It adds sign-in and registration via Google, GitHub, Facebook, and any other provider supported by [yiisoft/yii-auth-client](https://github.com/yiisoft/yii-auth-client), plus a settings page for connecting/disconnecting linked accounts.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti-social-auth.svg)](https://packagist.org/packages/yiirocks/voyti-social-auth)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti-social-auth.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti-social-auth.svg)](https://packagist.org/packages/yiirocks/voyti-social-auth)
[![GitHub License](https://img.shields.io/github/license/yiirocks/voyti-social-auth.svg)](https://github.com/yiirocks/voyti-social-auth/blob/main/LICENSE.md)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/yiirocks/voyti-social-auth/build.yml?branch=main)](https://github.com/yiirocks/voyti-social-auth/actions)

Stats for Nerds

[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-social-auth%2Fbadges%2Fcoverage.json)](https://github.com/yiirocks/voyti-social-auth/tree/badges)
[![MSI](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-social-auth%2Fbadges%2Fmsi.json)](https://github.com/yiirocks/voyti-social-auth/tree/badges)
[![Tests](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-social-auth%2Fbadges%2Ftests.json)](https://github.com/yiirocks/voyti-social-auth/tree/badges)
[![Assertions](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-social-auth%2Fbadges%2Fassertions.json)](https://github.com/yiirocks/voyti-social-auth/tree/badges)

## Overview

This package is the OAuth2/social-login half of Voyti: it builds on `yiisoft/yii-auth-client` to let guests log in or register through a third-party provider, links provider identities to existing accounts, and owns the `user_social_account` table. Core stays fully functional without it — no social login buttons or routes appear until this package is installed.

## Installation

```bash
composer require yiirocks/voyti-social-auth
```

## Documentation

The complete reference guide is available at [Yii.Rocks](https://www.yii.rocks/voyti/social/).
