# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-05

### Added

- Service provider with auto-discovery, publishable `config/taqnyat.php`.
- `Taqnyat` facade and service: default sender, templates from config, `sendText()`, `sendTemplate()`,
  `message()`, `send()`, `balance()`, `senders()`, `status()`, `delete()`.
- Notification channel (`taqnyat`) with fluent `TaqnyatMessage`.
- `Taqnyat::fake()` with assertions and failure simulation.
- `MessageSending` and `MessageSent` events.
- Artisan commands: `taqnyat:send`, `taqnyat:balance`, `taqnyat:senders`, `taqnyat:status`.
- Support for Laravel 8 – 13 and PHP 7.4 – 8.5.
