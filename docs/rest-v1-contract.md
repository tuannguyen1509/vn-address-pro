# REST API v1 — frozen Phase 1 contract

Captured from the original VN Address API 2025+ 1.0.0 source before the Phase 1 bootstrap change. Plugin release 2.0.0 preserves this contract, including legacy quirks. Examples below use fixture records and are not an authoritative administrative dataset.

## Common rules

- Namespace: `vn-address/v1`. Obtain the base URL using `rest_url('vn-address/v1/')`. Examples show `/wp-json/`, but installations without pretty permalinks, subdirectory installs and filtered REST URLs may differ.
- All four routes are registered as `WP_REST_Server::READABLE` (GET) with `__return_true` permission. No authentication or nonce required. WordPress itself supplies REST discovery/OPTIONS and transport behavior; no additional method is introduced by the plugin.
- Successful responses use HTTP 200 and `success: true`. Empty search results remain HTTP 200. No pagination.
- Error responses use WordPress REST `code`, `message`, `data`, not a `success: false` envelope.
- No endpoint mutates an address. Cache writes occur internally on successful upstream fetches.
- Administrative codes are JSON integers; leading zero formatting is not retained.
- Parameter declarations do not currently include JSON Schema `type`. Documented intended scalar types below must not be mistaken for strict existing validation.

## Shared record schemas

Province (the exact key set):

```json
{"code":1,"name":"Thành phố Hà Nội","short_name":"Hà Nội","division_type":"thành phố trung ương","codename":"ha_noi","phone_code":24}
```

`code` is integer, `phone_code` integer or null, all other fields strings. The prefix `Thành phố` or `Tỉnh` is stripped and rebuilt. Codes `[1,31,46,48,79,92]` are classified as cities; all others as provinces, independently of upstream `division_type`. Missing code becomes 0, missing codename/name becomes an empty string before prefix rebuilding.

Ward (the exact key set):

```json
{"code":1,"name":"Phường A","division_type":"phường","codename":"phuong_a","province_code":1}
```

`code` and `province_code` are integers (default 0); other fields strings (default empty). Ward names/types are passed through as strings. There is no district layer.

## GET /provinces

URL: `/wp-json/vn-address/v1/provinces`.

| Parameter | Intended type | Required | Existing handling |
| --- | --- | --- | --- |
| `search` | string | no | `sanitize_text_field`, cast to string, trim; absent/empty means all |

Success, HTTP 200:

```json
{"success":true,"count":1,"data":[{"code":1,"name":"Thành phố Hà Nội","short_name":"Hà Nội","division_type":"thành phố trung ương","codename":"ha_noi","phone_code":24}]}
```

`success`: boolean; `count`: integer of returned records; `data`: array of province records.

Search: substring of `name + ' ' + short_name`, case insensitive using `mb_stripos(..., 'UTF-8')` when available, else `stripos`. Accent sensitive; does not search `codename` or map aliases. `ha noi` does not match `Hà Nội`. No fuzzy search.

Sorting: numeric `code` ascending, after filtering.

Cache: raw response of `https://provinces.open-api.vn/api/v2/p/`, key `vn_address_provinces_v2`. Search is applied after fetching, not included in the cache key.

Error example, HTTP 502:

```json
{"code":"vn_address_invalid_json","message":"Dữ liệu JSON từ nguồn không hợp lệ.","data":{"status":502}}
```

Other errors: shared transport/HTTP errors below.

## GET /wards

URL: `/wp-json/vn-address/v1/wards?province_code=1`.

| Parameter | Intended type | Required | Existing handling |
| --- | --- | --- | --- |
| `province_code` | positive integer | yes | validation `absint(value) > 0`, sanitization `absint` |
| `search` | string | no | `sanitize_text_field`, string cast and trim |

Legacy quirk: `-1` passes validation and becomes `1`. Do not replace coercion with strict validation in Phase 1. Malformed non-scalar input is not a supported contract.

Success, HTTP 200:

```json
{"success":true,"province":{"code":1,"name":"Thành phố Hà Nội","short_name":"Hà Nội","division_type":"thành phố trung ương","codename":"ha_noi","phone_code":24},"count":1,"data":[{"code":1,"name":"Phường A","division_type":"phường","codename":"phuong_a","province_code":1}]}
```

`province`: province record; `count`: returned ward count; `data`: array of ward records; `success`: boolean.

Search: same case/accent behavior as `/provinces`, but searches ward `name` only. No match gives `count: 0, data: []`, with province retained.

Sorting: `strnatcasecmp` ascending by ward name; not Vietnamese locale collation.

Cache: raw response of `/api/v2/p/{absint(province_code)}?depth=2`, key `vn_address_province_{code}_depth2`. Shared with `/province/{code}`; no search-specific key.

Error example, HTTP 400 (WordPress-generated; message language depends on site locale):

```json
{"code":"rest_missing_callback_param","message":"Missing parameter(s): province_code","data":{"status":400,"params":["province_code"]}}
```

`province_code=0` gives `rest_invalid_param`, HTTP 400. Other errors: shared 502s and the conditional not-found 404 below.

## GET /province/{code}

URL: `/wp-json/vn-address/v1/province/1`.

| Parameter | Intended type | Required | Existing handling |
| --- | --- | --- | --- |
| `code` | digit path segment | yes | route `(?P<code>\d+)`, sanitization `absint`; no positive/existence validator |

Success, HTTP 200:

```json
{"success":true,"data":{"code":1,"name":"Thành phố Hà Nội","short_name":"Hà Nội","division_type":"thành phố trung ương","codename":"ha_noi","phone_code":24,"wards":[{"code":1,"name":"Phường A","division_type":"phường","codename":"phuong_a","province_code":1}],"ward_count":1}}
```

`data`: province record plus `wards` array and integer `ward_count`. `success`: boolean.

Search: none; `search` is not declared or used. Sorting: wards preserve upstream order, unlike `/wards`.

Cache: same detail URL and key as `/wards`. Missing `wards` is treated as an empty array; no schema redesign in Phase 1.

Error example, HTTP 404, only when a successful decoded upstream payload has empty/missing `code`:

```json
{"code":"vn_address_not_found","message":"Không tìm thấy tỉnh/thành phố.","data":{"status":404}}
```

Upstream HTTP 404 instead becomes the shared HTTP **502** error. A non-digit route path does not match; WordPress returns its own route error (normally `rest_no_route`, 404). Other errors: shared 502s below.

## GET /health

URL: `/wp-json/vn-address/v1/health`. Parameters: none. Search: none. Sorting: none. Cache: none. No upstream call.

Success, HTTP 200, exact payload:

```json
{"success":true,"service":"VN Address API 2025+","version":"1.0.0","administrative_model":"2-level"}
```

`success`: boolean; remaining fields: strings. The service/version values are deliberately frozen legacy API compatibility values. The plugin display name is VN Address Pro and its release is derived separately from the plugin header via `VNAP_VERSION`.

Error example: no plugin-defined error branch for this GET endpoint. For a nonmatching route, WordPress may return:

```json
{"code":"rest_no_route","message":"No route was found matching the URL and request method.","data":{"status":404}}
```

Health reports handler availability, not upstream health or data freshness.

## Shared upstream errors and cache behavior

Transport failure, HTTP 502 (`detail` varies by HTTP client failure):

```json
{"code":"vn_address_upstream_error","message":"Không thể kết nối nguồn dữ liệu địa giới hành chính.","data":{"status":502,"detail":"Fixture transport failure"}}
```

Any non-2xx upstream HTTP, HTTP 502 (`upstream_status` varies):

```json
{"code":"vn_address_upstream_http_error","message":"Nguồn dữ liệu trả về lỗi HTTP.","data":{"status":502,"upstream_status":404}}
```

Invalid JSON or a decoded value that is not an array, HTTP 502: `vn_address_invalid_json` as above. JSON objects decode into PHP arrays and are not otherwise validated, an existing limitation.

Upstream timeout: 15 seconds; redirect limit: 3; headers `Accept: application/json`, `User-Agent: WordPress/VN-Address-API-2025`. Provider, mappings and HTTP settings stay unchanged.

Transients store **raw** decoded arrays for `DAY_IN_SECONDS`. `false` means miss; even an empty array is a hit. Errors are not cached, but schema-invalid arrays may be cached. Detail payloads with missing code are cached before the callback returns 404. No refresh lock, retry/backoff, stale fallback, dataset sync or custom HTTP-cache policy exists. Cache hits still normalize, filter and sort on every request.

The 24-hour TTL is a maximum lifetime; WordPress can evict transients sooner and may store them in persistent object cache instead of the database: [Transients API](https://developer.wordpress.org/apis/transients/).

## Regression baseline

`tests/Contract/fixtures/rest-v1.json` was recorded against the original implementation using a real WordPress REST dispatcher with deterministic HTTP fixtures. Preserve field types, response keys, errors, searches, order, route declarations, cache sharing and outbound settings. See [test instructions](../tests/README.md).
