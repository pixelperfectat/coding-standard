# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.8] - 2026-09-25

### Fixed
- The module boundaries check rejected a dependency that was properly declared, whenever the package slug and the Magento module name differed; the installed package is now asked for its own name instead of it being guessed from the slug (fdc0a44)
