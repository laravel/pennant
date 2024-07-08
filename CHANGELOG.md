# Release Notes

## [Unreleased](https://github.com/laravel/pennant/compare/v1.8.3...1.x)

## [v1.8.3](https://github.com/laravel/pennant/compare/v1.8.2...v1.8.3) - 2024-06-27

* [1.x] Use constant for updated_at column name by [@thijsvdanker](https://github.com/thijsvdanker) in https://github.com/laravel/pennant/pull/108
* [1.x] Fixes wrong column reference by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/109

## [v1.8.2](https://github.com/laravel/pennant/compare/v1.8.1...v1.8.2) - 2024-06-20

* [1.x] Ensure values returns feature name by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/107

## [v1.8.1](https://github.com/laravel/pennant/compare/v1.8.0...v1.8.1) - 2024-05-29

* [1.x] Extract insert all by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/105

## [v1.8.0](https://github.com/laravel/pennant/compare/v1.7.1...v1.8.0) - 2024-05-21

* [1.x] Improve unique constraint handling for high traffic applications by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/104

## [v1.7.1](https://github.com/laravel/pennant/compare/v1.7.0...v1.7.1) - 2024-05-06

* [1.x] Make commands lazy by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/96

## [v1.7.0](https://github.com/laravel/pennant/compare/v1.6.0...v1.7.0) - 2024-03-04

* Make timestamp column names easy to change by [@thijsvdanker](https://github.com/thijsvdanker) in https://github.com/laravel/pennant/pull/90
* [1.x] Allow excluding features from purge command by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/89
* Ensure strings by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/91

## [v1.6.0](https://github.com/laravel/pennant/compare/v1.5.1...v1.6.0) - 2024-01-15

* [1.x] Ensure cache holds serialized values only by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/83
* [1.x] Respect configuration changes by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/81
* [1.x] Laravel v11 support by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/pennant/pull/84

## [v1.5.1](https://github.com/laravel/pennant/compare/v1.5.0...v1.5.1) - 2023-12-14

* Alias command by [@taylorotwell](https://github.com/taylorotwell) in https://github.com/laravel/pennant/commit/6464d2aeb6cd8d1ad493b6cb101cdea552451f67

## [v1.5.0](https://github.com/laravel/pennant/compare/v1.4.0...v1.5.0) - 2023-08-31

- [1.x] Added "all" method to "EnsureFeaturesAreActive" middleware. by [@ash-jc-allen](https://github.com/ash-jc-allen) in https://github.com/laravel/pennant/pull/70
- [1.x] Rename to match framework by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/72

## [v1.4.0](https://github.com/laravel/pennant/compare/v1.3.1...v1.4.0) - 2023-06-21

- [1.x] Add feature blade directive tests by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/66
- [1.x] Provide hook to use morph map while serializing models by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/pennant/pull/64

## [v1.3.1](https://github.com/laravel/pennant/compare/v1.3.0...v1.3.1) - 2023-05-17

- Fixes typo in `PendingScopeFeatureInteraction@values()` by @cosmastech in https://github.com/laravel/pennant/pull/61

## [v1.3.0](https://github.com/laravel/pennant/compare/v1.2.1...v1.3.0) - 2023-05-04

- Events for Feature Updates and More by @tylernathanreed in https://github.com/laravel/pennant/pull/59

## [v1.2.1](https://github.com/laravel/pennant/compare/v1.2.0...v1.2.1) - 2023-03-17

- Not call whenInactive when it is null by @kafkiansky in https://github.com/laravel/pennant/pull/48
- Fix flushCache() on null error by @mbukovy in https://github.com/laravel/pennant/pull/56

## [v1.2.0](https://github.com/laravel/pennant/compare/v1.1.1...v1.2.0) - 2023-02-24

- Fixed phpdoc type syntax by @korkoshko in https://github.com/laravel/pennant/pull/38
- Adds timestamps to bulk updates by @timacdonald in https://github.com/laravel/pennant/pull/37
- Improved manager by @taylorotwell in https://github.com/laravel/pennant/pull/44
- Ensure global helper does not conflict with another function by @ChenRuiWang in https://github.com/laravel/pennant/pull/46

## [v1.1.1](https://github.com/laravel/pennant/compare/v1.1.0...v1.1.1) - 2023-02-16

- Fix table name not being used and migration error by @bankorh in https://github.com/laravel/pennant/pull/36

## [v1.1.0](https://github.com/laravel/pennant/compare/v1.0.0...v1.1.0) - 2023-02-15

- Gracefully handles unexpected `null` scope by @timacdonald in https://github.com/laravel/pennant/pull/31

## v1.0.0 - 2023-02-14

Initial release of Pennant. For more information, please consult the [Pennant documentation](https://laravel.com/docs/pennant).
