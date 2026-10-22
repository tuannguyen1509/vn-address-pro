# Administrative address comparison

Phase 10 adds `Migration\AddressNormalizer` for migration comparison only. It does not change `Address\Normalizer`, public REST search/sort, checkout or readable historical values. Original state/city/code fields remain verbatim in every analysis; normalized text is never persisted as display text.

Pipeline: optional Unicode NFC (`intl` is optional), WordPress Vietnamese accent removal, combining-mark removal, case folding, punctuation/whitespace folding, then optional entity-specific prefix removal. Supported province prefixes: Tỉnh, Thành phố, TP. Ward prefixes: Phường/P, Xã, Thị trấn/TT, Đặc khu. Prefixes and the deliberately small HCM/HCMC alias list live in one class. No historical code mapping or third-party extension ID conversion is asserted.

| Strategy | Score | Policy |
| --- | --- | --- |
| EXACT_CODE | 1.00 | Current strict `VN-{code}`; ward code must belong to province |
| EXACT_CANONICAL_NAME | 1.00 | Unique full current name |
| EXACT_NORMALIZED_NAME | 0.98 | Unique full comparison name |
| UNIQUE_NORMALIZED_MATCH | 0.96 | Unique name after prefix removal |
| EXACT_ALIAS | 0.85 | Known province abbreviation; reviewed MEDIUM |
| Bare numeric province | 0.85 | Code candidate, not an authoritative legacy mapping |
| Changed administrative prefix | 0.82 | Warning; requires MEDIUM opt-in |
| AMBIGUOUS | 0.20 | Candidates only, never applicable |
| NO_MATCH | 0.00 | No proposal |

HIGH >=0.95; MEDIUM >=0.80; LOW >0; otherwise NONE. Overall eligibility requires both province and ward; score is their minimum. Ambiguous/unresolved analysis has no complete pair and overall NONE. No fuzzy scorer is implemented. `LEGACY_MAPPING` remains deferred until authoritative versioned mappings exist.

ProvinceMatcher reads only current local provinces. WardMatcher reads only wards of the matched province and checks each parent. Present non-empty ward metadata must resolve by code; a conflicting code is never silently replaced by text matching. Conflicting province metadata requires review. Without canonical ward metadata, city beginning Quận/Q, Huyện/H, Thị xã or Thành phố/TP is treated as district-like and never guessed as a ward. Street/address_1/address_2 are never parsed.

`LegacyAddressAnalyzer` returns original/target, province and ward results/candidates, individual and overall confidence, score, status, warnings, and exact proposed_changes. Statuses: ALREADY_CANONICAL, MIGRATABLE_HIGH, MIGRATABLE_MEDIUM, NEEDS_REVIEW, AMBIGUOUS, UNRESOLVED, NON_VIETNAM, INVALID_DATA. Unknown country requires review; foreign addresses are out of scope. Invalid scalar/UTF-8/oversized source metadata is redacted and ineligible. Candidate lists are bounded to ten.

Canonical metadata can identify current names despite an outdated display value: customer proposals restore canonical state/city, while historical orders retain their display snapshot by default. Changed administrative type alone never yields HIGH. Matching current text is not proof of historical boundary equivalence; administrators must review even HIGH results.

PlanCodec VERSION is the matcher/plan revision (currently 1). Any semantic matching change must bump this revision, invalidating old previews. See [migration workflow](legacy-migration.md).
