<?php
/** WordPress CLI; no Woo installation or database fixtures required. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
use Tunlamweb\VNAddressPro\Migration\{AddressNormalizer,ProvinceMatcher,WardMatcher,NameMatcher,LegacyAddressAnalyzer};
use Tunlamweb\VNAddressPro\Contracts\AddressRepositoryInterface;
$count = 0; $check = static function ( $ok, $label ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$count; WP_CLI::line( 'PASS ' . $label ); };
$n = new AddressNormalizer();
foreach ( array( ' Hồ Chí Minh ', 'HO CHI MINH', "Ho\u{0302}\u{0300} Chi\u{0301} Minh", "Hồ\tChí\nMinh", 'Hồ--Chí,Minh' ) as $value ) { $check( $n->normalize( $value ) === 'ho chi minh', 'Predictable whitespace/case/punctuation/diacritic/Unicode comparison' ); }
foreach ( array( 'TP. HCM' => 'hcm', 'Thành phố Hồ Chí Minh' => 'ho chi minh', 'Tỉnh Bắc An' => 'bac an' ) as $raw => $expected ) { $check( $n->normalize( $raw, 'province' ) === $expected, 'Centralized province prefixes: ' . $raw ); }
foreach ( array( 'P. An Bình','Phường An Bình','Xã An Bình','Thị trấn An Bình','Đặc khu An Bình' ) as $raw ) { $check( $n->normalize( $raw, 'ward' ) === 'an binh', 'Centralized ward prefixes: ' . $raw ); }
$repo = new class implements AddressRepositoryInterface {
    public function has_usable_dataset(): bool { return true; }
    public function get_provinces(): array|WP_Error { return array( array( 'code' => 1, 'name' => 'Tỉnh Bắc An' ), array( 'code' => 79, 'name' => 'Thành phố Hồ Chí Minh' ) ); }
    public function get_province( int $code ): array|WP_Error { return $this->get_wards( $code ); }
    public function get_wards( int $code ): array|WP_Error { return array( 'wards' => $code === 79 ? array( array( 'code' => 101,'province_code' => 79,'name' => 'Phường Bến Thành' ) ) : array( array( 'code' => 201,'province_code' => 1,'name' => 'Xã An Bình' ), array( 'code' => 202,'province_code' => 1,'name' => 'Phường An Bình' ) ) ); }
    public function get_ward( int $code ): array|WP_Error { return new WP_Error( 'not_needed' ); }
};
$p = new ProvinceMatcher(); $w = new WardMatcher(); $provinces = $repo->get_provinces(); $wards = $repo->get_wards( 79 )['wards'];
$check( $p->match( 'VN-79', $provinces )['strategy'] === 'EXACT_CODE', 'Canonical state code strategy' );
$check( $p->match( 'Thành phố Hồ Chí Minh', $provinces )['strategy'] === 'EXACT_CANONICAL_NAME', 'Canonical province name strategy' );
$check( $p->match( 'thanh pho ho chi minh', $provinces )['strategy'] === 'EXACT_NORMALIZED_NAME', 'Full normalized province strategy' );
$check( $p->match( 'Ho Chi Minh', $provinces )['strategy'] === 'UNIQUE_NORMALIZED_MATCH', 'Unique accent-insensitive short province name' );
foreach ( array( 'TP HCM','TP.HCM','HCM','HCMC' ) as $alias ) { $m = $p->match( $alias, $provinces ); $check( $m['candidate']['code'] === 79 && NameMatcher::confidence( $m['score'] ) === 'MEDIUM', 'Known alias remains reviewed MEDIUM: ' . $alias ); }
$check( NameMatcher::confidence( $p->match( '79', $provinces )['score'] ) === 'MEDIUM', 'Bare legacy numeric province code is not assumed authoritative' );
foreach ( array( '', 'VN-079','VN-999','old-extension-79','VN-79suffix' ) as $raw ) { $check( ! $p->match( $raw, $provinces )['matched'], 'No unverified extension-code conversion: ' . $raw ); }
$check( $w->match( 'Phường Bến Thành', 79, $wards )['strategy'] === 'EXACT_CANONICAL_NAME', 'Exact ward within province' );
$check( $w->match( 'ben thanh', 79, $wards )['matched'], 'Unique normalized ward under confirmed province' );
$check( ! $w->match( 'Bến Thành', 1, $wards )['matched'], 'Wrong-parent ward cannot enter candidates' );
$check( ! $w->match( 'Bến Thành', 79, $wards, '201' )['matched'], 'Foreign province canonical metadata cannot be overridden by display name' );
foreach ( array( 'Quận 1','Huyện An Bình','Q. 1','Thành phố Thủ Đức','TP. Thủ Đức' ) as $raw ) { $check( ( $w->match( $raw, 79, $wards )['warning'] ?? '' ) === 'DISTRICT_ONLY', 'District-like city is not converted to a ward' ); }
$check( $w->match( 'An Bình', 1, $repo->get_wards( 1 )['wards'] )['strategy'] === 'AMBIGUOUS', 'Duplicate normalized ward names remain ambiguous' );
$check( $w->match( 'Xã Bến Thành', 79, $wards )['score'] < 0.95, 'Changed administrative type is not HIGH confidence' );
$empty = array( 'exists' => false,'value' => '' ); $s = array( 'country' => 'VN','state' => 'VN-79','city' => 'Phường Bến Thành','province_meta' => $empty,'ward_meta' => $empty,'province_name_meta' => $empty );
$analyzer = new LegacyAddressAnalyzer( $repo );
$r = $analyzer->analyze( 'customer', 1, 'billing', $s )->to_array();
$check( $r['status'] === 'MIGRATABLE_HIGH' && $r['target']['ward_meta']['value'] === '101', 'Partial canonical address proposes explicit code enrichment' );
$check( $r['original'] === $s, 'Original values retained verbatim; comparison text never replaces source' );
$check( $r['target']['city'] === 'Phường Bến Thành', 'Canonical accented display preserved' );
$check( $analyzer->analyze( 'customer', 1, 'billing', $r['target'] )->data['status'] === 'ALREADY_CANONICAL', 'Canonical data requires no rewrite' );
$old = $s; $old['state'] = 'TP.HCM'; $old['city'] = 'Bến Thành';
$r = $analyzer->analyze( 'order', 1, 'billing', $old )->data;
$check( $r['status'] === 'MIGRATABLE_MEDIUM' && $r['target']['state'] === 'TP.HCM' && $r['target']['city'] === 'Bến Thành', 'Default historical order migration enriches only codes' );
$check( $analyzer->analyze( 'order', 1, 'billing', $old, true )->data['target']['state'] === 'VN-79', 'Historical display canonicalization requires its separate preview mode' );
$district = $s; $district['city'] = 'Quận 1';
$check( $analyzer->analyze( 'customer', 1, 'billing', $district )->data['status'] === 'NEEDS_REVIEW', 'District-only legacy address requires review' );
$unknown = $s; $unknown['city'] = 'Unverified old place';
$check( $analyzer->analyze( 'customer', 1, 'billing', $unknown )->data['status'] === 'UNRESOLVED', 'No fuzzy or street parsing authority' );
$unknown['country'] = '';
$check( $analyzer->analyze( 'customer', 1, 'billing', $unknown )->data['status'] === 'NEEDS_REVIEW', 'Empty country never assumed VN' );
$unknown['country'] = 'US';
$check( $analyzer->analyze( 'customer', 1, 'billing', $unknown )->data['status'] === 'NON_VIETNAM', 'Foreign address is outside normalization scope' );
$bad = $s; $bad['city'] = array( 'hostile' );
$check( $analyzer->analyze( 'customer', 1, 'billing', $bad )->data['status'] === 'INVALID_DATA', 'Array payload rejected without a string cast' );
$bad = $s; $bad['province_meta'] = array( 'exists' => true,'value' => '1' );
$check( $analyzer->analyze( 'customer', 1, 'billing', $bad )->data['status'] === 'NEEDS_REVIEW', 'Conflicting province metadata prevents proposal' );
$ambiguous = $s; $ambiguous['state'] = 'VN-1'; $ambiguous['city'] = 'An Bình';
$check( $analyzer->analyze( 'customer', 1, 'billing', $ambiguous )->data['status'] === 'AMBIGUOUS', 'Structured analysis never accepts ambiguous ward' );
WP_CLI::success( $count . ' migration matching checks passed.' );
