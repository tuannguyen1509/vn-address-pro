<?php
/** Existing REST v1 implementation; intentionally preserved for compatibility. */
defined( 'ABSPATH' ) || exit;

final class VN_Address_API_2025 {
    const NS = 'vn-address/v1';
    const UPSTREAM = 'https://provinces.open-api.vn/api/v2';
    const CACHE_TTL = DAY_IN_SECONDS;

    // 6 thành phố trực thuộc Trung ương hiện hành.
    private static array $central_cities = [1, 31, 46, 48, 79, 92];

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(self::NS, '/provinces', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_provinces'],
            'permission_callback' => '__return_true',
            'args' => [
                'search' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NS, '/wards', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_wards'],
            'permission_callback' => '__return_true',
            'args' => [
                'province_code' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn($value) => absint($value) > 0,
                ],
                'search' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NS, '/province/(?P<code>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_province'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::NS, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static function () {
                return rest_ensure_response([
                    'success' => true,
                    'service' => 'VN Address API 2025+',
                    'version' => '1.0.0',
                    'administrative_model' => '2-level',
                ]);
            },
            'permission_callback' => '__return_true',
        ]);
    }

    private static function fetch_json(string $url, string $cache_key) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/VN-Address-API-2025',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'vn_address_upstream_error',
                'Không thể kết nối nguồn dữ liệu địa giới hành chính.',
                ['status' => 502, 'detail' => $response->get_error_message()]
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            return new WP_Error(
                'vn_address_upstream_http_error',
                'Nguồn dữ liệu trả về lỗi HTTP.',
                ['status' => 502, 'upstream_status' => $status]
            );
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new WP_Error(
                'vn_address_invalid_json',
                'Dữ liệu JSON từ nguồn không hợp lệ.',
                ['status' => 502]
            );
        }

        set_transient($cache_key, $data, self::CACHE_TTL);
        return $data;
    }

    private static function strip_prefix(string $name): string {
        $name = preg_replace('/^(Thành phố|Tỉnh)\s+/u', '', trim($name));
        return trim((string)$name);
    }

    private static function normalize_province(array $p): array {
        $code = isset($p['code']) ? (int)$p['code'] : 0;
        $base_name = self::strip_prefix((string)($p['name'] ?? ''));
        $is_city = in_array($code, self::$central_cities, true);

        return [
            'code' => $code,
            'name' => ($is_city ? 'Thành phố ' : 'Tỉnh ') . $base_name,
            'short_name' => $base_name,
            'division_type' => $is_city ? 'thành phố trung ương' : 'tỉnh',
            'codename' => (string)($p['codename'] ?? ''),
            'phone_code' => isset($p['phone_code']) ? (int)$p['phone_code'] : null,
        ];
    }

    private static function normalize_ward(array $w): array {
        return [
            'code' => isset($w['code']) ? (int)$w['code'] : 0,
            'name' => (string)($w['name'] ?? ''),
            'division_type' => (string)($w['division_type'] ?? ''),
            'codename' => (string)($w['codename'] ?? ''),
            'province_code' => isset($w['province_code']) ? (int)$w['province_code'] : 0,
        ];
    }

    private static function contains_ci(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
        }
        return stripos($haystack, $needle) !== false;
    }

    public static function get_provinces(WP_REST_Request $request) {
        $data = self::fetch_json(self::UPSTREAM . '/p/', 'vn_address_provinces_v2');
        if (is_wp_error($data)) return $data;

        $search = trim((string)$request->get_param('search'));
        $items = [];

        foreach ($data as $p) {
            $item = self::normalize_province($p);
            if ($search !== '' && !self::contains_ci($item['name'] . ' ' . $item['short_name'], $search)) {
                continue;
            }
            $items[] = $item;
        }

        usort($items, static fn($a, $b) => $a['code'] <=> $b['code']);

        return rest_ensure_response([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    public static function get_wards(WP_REST_Request $request) {
        $province_code = absint($request->get_param('province_code'));
        $search = trim((string)$request->get_param('search'));

        $url = self::UPSTREAM . '/p/' . $province_code . '?depth=2';
        $data = self::fetch_json($url, 'vn_address_province_' . $province_code . '_depth2');
        if (is_wp_error($data)) return $data;

        if (empty($data['code'])) {
            return new WP_Error('vn_address_not_found', 'Không tìm thấy tỉnh/thành phố.', ['status' => 404]);
        }

        $province = self::normalize_province($data);
        $wards = [];

        foreach (($data['wards'] ?? []) as $w) {
            $item = self::normalize_ward($w);
            if ($search !== '' && !self::contains_ci($item['name'], $search)) {
                continue;
            }
            $wards[] = $item;
        }

        usort($wards, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return rest_ensure_response([
            'success' => true,
            'province' => $province,
            'count' => count($wards),
            'data' => $wards,
        ]);
    }

    public static function get_province(WP_REST_Request $request) {
        $code = absint($request['code']);
        $url = self::UPSTREAM . '/p/' . $code . '?depth=2';
        $data = self::fetch_json($url, 'vn_address_province_' . $code . '_depth2');
        if (is_wp_error($data)) return $data;

        if (empty($data['code'])) {
            return new WP_Error('vn_address_not_found', 'Không tìm thấy tỉnh/thành phố.', ['status' => 404]);
        }

        $province = self::normalize_province($data);
        $province['wards'] = array_map([__CLASS__, 'normalize_ward'], $data['wards'] ?? []);
        $province['ward_count'] = count($province['wards']);

        return rest_ensure_response([
            'success' => true,
            'data' => $province,
        ]);
    }
}
