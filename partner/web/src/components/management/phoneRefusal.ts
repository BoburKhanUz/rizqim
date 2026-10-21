// A login phone the member proved by SMS themselves cannot be replaced or cleared by the owner or a manager: PATCH
// /owner/staff/{id} answers 422 on `phone` with a message that begins "Bu raqamni xodimning o'zi SMS kod bilan
// tasdiqlagan ..." (StaffPhoneLoginService::refusalForStoreVouch, docs/AUTH.md). The API does not say beforehand which kind of
// number a member has (`phone_verified_via` is not in the staff list: an API gap), so the form learns it from this refusal and
// from then on shows the number as fixed. A message in other words (the phone is another store's SMS-proven one, taken by
// a colleague ...) is a plain field error and leaves the field editable.
export function isSmsProvenRefusal(message: string | undefined): boolean {
  return typeof message === 'string' && /xodimning o['‘’`ʻʼ]?zi SMS kod bilan tasdiqlagan/i.test(message);
}
