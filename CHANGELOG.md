# Changelog

## 0.9.0 (2026-10-07)

- New `BudgetExceededException` (extends `PaymentRequiredException`): thrown when the API answers a 402 with `User set budget exceeded` - this billing period's spend has reached the monthly budget limit you set in your [profile](https://app.scrapeunblocker.com/dashboard/profile) (EUR, excluding VAT). The key works again at the start of the next billing period, or within about a minute after you raise or remove the limit. Like the other billing blocks it is not billed and never retried.

No breaking changes: `catch (PaymentRequiredException $e)` still catches it. Before, this body fell back to a plain `PaymentRequiredException`.

## 0.8.0 (2026-10-06)

- `getParsed()` on a page that rendered but held no structured data now returns a `ParsedPage` instead of throwing. The API answers this with a 200 again (billed like `getPageSource()`), and `ParsedPage` carries the new properties `dataExtracted` (false here), `detail` (the API's explanation) and `html` (the rendered page). A normal parse has `dataExtracted` true and `html` null. The same answer now covers a parser failure on the API side.
- `NoDataExtractedException` is deprecated: the API no longer sends the 422 `no_data_extracted`. The class stays in the package so existing `catch` blocks still resolve.

Behaviour change: code that caught `NoDataExtractedException` from `getParsed()` should check `$result->dataExtracted` instead.

## 0.7.1 (2026-09-30)

- New `NoDataExtractedException` (extends `ValidationException`): thrown by `getParsed()` when the page rendered but no structured data could be extracted from it. The API answers 422 with `{"error": "no_data_extracted", "detail": ...}`; the exception carries `detail`. The call is not billed and is never retried - use `getPageSource()` for the HTML. Before, this came back as a billed 200 with empty `data`.
- A dead URL fetched with `getParsed()` throws `TargetNotFoundException` with `html` null (the body is the parsed-data JSON, on `body`).

## 0.7.0 (2026-09-30)

- New `TargetNotFoundException` (extends `NotFoundException`): thrown by `getPageSource()`, `getParsed()` and `getPageWithCookies()` when the target page itself answers 404 or 410. The API now passes the target's own status through instead of a 200, marked with the `X-Origin-Status` header. The exception carries `originStatus`, the not-found page on `html` and `destinationUrl`. It is never retried, and the call is billed like any delivered page.
- A custom `transport` may return `headers` alongside `status` and `body`.

Behaviour change: a dead target URL used to return its not-found page as a normal string; it now throws `TargetNotFoundException`. `catch (NotFoundException $e)` still catches it. A 404 without `X-Origin-Status` is the API's own and stays a plain `NotFoundException`.

## 0.6.0 (2026-09-24)

- `googleImages()` takes a `pages` option (1-5): fetch up to five Google Images result pages of ~100 results each in one call. Each page fetched is billed as one request; the response's `pagesFetched` says how many. The Google market now follows `proxy_country` automatically, so `gl` is only an optional override, and `max_results` is an optional cap up to 500.

No breaking changes.

## 0.5.0 (2026-09-17)

- Added `$su->googleImages($keyword, [...])` for the new Google Images plugin (`POST /images/google-search`): returns Google Images results as an array - each with the full-size `imageUrl` and its `sourceDomain`, plus the source page URL, title, source name, thumbnail URL, pixel dimensions and file size. Options: `gl` (ISO-2 lowercase market), `max_results` (1-100) and `proxy_country` (ISO-2).

No breaking changes.

## 0.4.0 (2026-09-16)

- Added the Southwest Airlines flights plugin: `$su->southwest->flights([...])` posts to `/flights/southwest-quotes` and returns the raw booking / shopping JSON. Parameters: `origin` and `dest` (IATA codes), `depart_date` (`YYYY-MM-DD`), optional `return_date` (omit for one-way), `adults` (1-8, default 1), `fare_type` (`dollars` or `points`, default `dollars`), `proxy_country` (default `US`) and `max_attempts` (1-5, default 3). Mirrors the existing `skyscanner` namespace.

No breaking changes.

## 0.3.0 (2026-09-08)

- Added `tiktokProfile()`, `tiktokVideo()`, `tiktokHashtag()`, `tiktokSearch()` and `tiktokComments()` for the new TikTok plugin: a creator's exact follower / like / video counts with their newest videos (up to 200), any video or photo post with exact plays, likes, comments, shares, saves and reposts, hashtags, music, play / download URLs, subtitle tracks and an optional transcript, a hashtag's total views and videos with its videos, keyword search in TikTok's own ranking, and the comments of any post. No login.

## 0.2.1 (2026-09-02)

- Added `metaAdLibrary()` for the new Meta Ad Library plugin (`/ads/meta-ad-library`). `metaAdLibrary($advertiser, $options)` returns an advertiser's Meta (Facebook) Ad Library ads as an array. Options: `country`, `active_status`, `media_type` and `max_ads`; omitted options are dropped and the API applies its own defaults.

No breaking changes.

## 0.2.0 (2026-08-29)

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
