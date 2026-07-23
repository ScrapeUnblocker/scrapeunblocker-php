# Changelog

## 0.1.6 - 2026-07-23

Version jumps from 0.1.2 to 0.1.6 so all four official SDKs (Python, Node.js, Ruby, PHP) share one version number from here on. Nothing was skipped - 0.1.3 to 0.1.5 were never released for PHP.

- Added `PaymentRequiredException` for HTTP 402, which previously surfaced as a bare `ApiException` with no explanation. The three billing blocks now each get their own subclass, picked from the response body: `QuotaExceededException` (`Quota exceeded`), `CreditLimitExceededException` (`Credit limit exceeded`) and `PaymentFailedException` (`Payment failed - update payment method`). Catch `PaymentRequiredException` to handle all three.
- Added `NoSubscriptionException`, a subclass of `AuthenticationException`, for the 401 that means "the key is fine, the account has no active plan" (`No valid subscription`) as opposed to an unrecognised key.
- Added typed exceptions for the remaining documented status codes: `NotFoundException` (404), `BrowserTimeoutException` (408), `UnsupportedContentException` (415) and `ValidationException` (422). All previously threw a bare `ApiException`.
- Error messages now describe every documented status code accurately - notably 400, which also covers a missing `x-scrapeunblocker-key` header, not just a bad URL.
- Documented the full exception hierarchy in the README, including which errors are retried, which are billed, and how each 402 clears.
- Fixed the README and `oopbuySearch()` docblock claim that Oopbuy brand keywords return HTTP 422. They return a successful `200` with `keywordRejected: true` and an empty `results` array.

No breaking changes: every new class extends `ApiException`, so existing `catch (ApiException)` / `catch (ScrapeUnblockerException)` handlers keep working unchanged.

## 0.1.2 - 2026-07-22

- Added `oopbuySearch()` for the Oopbuy goods search plugin (`/goods/oopbuy-search`) - search 1688/Taobao/official channels and get products with USD and CNY prices, images and monthly sales.

## 0.1.1 - 2026-07-21

- Added `googleLocal()` for the Google Local (Maps) plugin (`/maps/google-local`).

## 0.1.0 - 2026-07-16

- Initial release: `getPageSource()`, `getParsed()`, `getPageWithCookies()`, `serp()`, `getImage()`, Skyscanner flights/hotels/car-hire plugins, typed exceptions with automatic retries.
