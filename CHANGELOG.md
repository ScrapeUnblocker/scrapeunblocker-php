# Changelog

## 0.1.10 (2026-08-29)

- Added a `steps` option to `getPageSource()`: an ordered list of browser actions run in a real browser after the page loads - `wait_for`, `wait_for_text`, `wait`, `click`, `type`, `select`, `press_key` and `scroll` - so you can fill a form, submit it and wait for results in one call. Steps are JSON-encoded into the request. They run once and are non-idempotent; a failing step comes back as HTTP 422 and raises `ValidationException`, whose `$body` holds `{ error: "step_failed", step_index, action, reason, selector, html }`.
- Added `listElements()`, which sends `list_elements=true` and returns the page's elements as an array (`{ url, count, elements: [...] }`) instead of HTML. It takes the same browser options as `getPageSource()`, including `steps`.

No breaking changes.

## 0.1.9 (2026-08-28)

- Added `amazonProduct()` and `amazonSearch()` for the new Amazon plugin. `amazonProduct(['asin' => ...] or ['url' => ...])` returns one product - title, brand, numeric price and currency, list price and savings, availability, rating, review count, seller, feature bullets, categories and images. `amazonSearch($keyword, $options)` returns a keyword search's cards - asin, title, price, list price, rating, review count, a clean product URL, image and the sponsored/prime flags - on any of 20 regional marketplaces.
- Prices come back in the right currency automatically: `proxy_country` defaults to the marketplace's home country (amazon.com -> US, amazon.de -> DE), pinning the exit over our ISP pool.

## 0.1.8 (2026-07-31)

- Added `ebaySearch()` for the new eBay Search plugin: listings from any of the 19 regional eBay marketplaces as structured JSON - title, numeric price and currency, condition with a normalised `conditionCode`, seller username and feedback, shipping cost, sold/watcher/bid counts, image and a clean item URL.
- Filters map straight onto the plugin: `marketplace`, `condition`, `sort`, `listing_type`, `min_price`/`max_price`, `free_shipping`, `seller`, `category`, plus `page`/`page_size` (60, 120 or 240).
- The response carries `exactMatches`; it is `false` when eBay found no match for the keyword and answered with its own loosely-related suggestions.
- Fixed the `User-Agent` version, which still reported 0.1.6 after the 0.1.7 release.

No breaking changes.

## 0.1.7 (2026-07-27)

- Registry and README links to scrapeunblocker.com now carry UTM parameters so traffic from package registries is attributable. No functional changes.

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
