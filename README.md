<a href="https://endurance.com/">
    <img src="https://bluehost.com/resources/logos/endurance.svg" alt="Endurance Logo" title="Endurance" align="right" height="42" />
</a>

# Endurance Page Cache
[![Version Number](https://img.shields.io/github/v/release/bluehost/endurance-page-cache?color=21a0ed&labelColor=333333)](https://github.com/bluehost/endurance-page-cache/releases)
[![License](https://img.shields.io/github/license/bluehost/endurance-page-cache?labelColor=333333&color=666666)](https://raw.githubusercontent.com/bluehost/endurance-page-cache/master/license.txt)

Endurance Page Cache adds basic file-based caching to WordPress, as well as more advanced caching solutions in nginx and Cloudflare. EPC is designed to run best on Endurance systems and your mileage may vary.

## Installation

- Run `git clone git@github.com:bluehost/endurance-page-cache.git` to pull down the code.
- Run `composer install` to install PHP dev dependencies.
- Run `npm install` to install JS dev dependencies.

## Running linting

- Run `composer run lint`

## Running tests

- Run `npm run wp-env start` to boot the WordPress environment
- Run `npm run test` to run the tests

## Tagging a new release

- Bump the version number in the header of the `endurance-page-cache.php` file (also the `EPC_VERSION` constant).
- Push all changes to `master`.
- Merge all changes to `production`.
- Create a release on GitHub for the new version.
- Log into DigitalOcean and update the [`mu-plugins.json`](https://cdn.hiive.space/bluehost/mu-plugins.json) file on the CDN to reflect the new version number.
