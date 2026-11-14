import '../http/pos_http.dart';
import 'ingredient_models.dart';

/// P6-55 / B9: ingredient bo'yicha tez stop ("Tovuq tugadi") — kassa va KDS uchun (ekranlari 2-bosqichda). Yo'llar
/// `owner` guruhida: terminalning xodim PIN sessiyasi bilan ham ishlaydi, ruxsat `stoplist.manage`; filialga bog'langan
/// xodim faqat o'z filialida. Modul (`ingredient_stoplist`) o'chiq bo'lsa to'xtatish 403 `module_disabled`
/// ([PosModuleDisabled]); qayta ochish modul so'ramaydi.
class PosIngredientRepository {
  PosIngredientRepository(this._http);

  final PosHttp _http;

  /// `GET /owner/ingredients?branch_id`: ingredientlar, bog'langan taomlar va `stopped_here`.
  Future<List<PosIngredient>> list({int? branchId}) => _http.get(
    '/v1/owner/ingredients',
    (Object? data) =>
        dataObject(data).objects('ingredients', PosIngredient.fromJson),
    query: <String, Object?>{'branch_id': ?branchId},
  );

  /// `POST /owner/ingredients/{id}/stop {branch_id?, reason?, until?}` `Idempotency-Key` bilan: bog'langan hamma taom
  /// filialda bir vaqtda yopiladi (hamma brend va kanalda); qayta yuborilsa sabab va vaqt yangilanadi.
  Future<PosIngredientStopResult> stop(
    int ingredientId, {
    int? branchId,
    String? reason,
    DateTime? until,
    String? idempotencyKey,
  }) => _http.mutate(
    'POST',
    '/v1/owner/ingredients/$ingredientId/stop',
    (Object? data) => PosIngredientStopResult.fromJson(dataObject(data)),
    idempotencyKey: idempotencyKey,
    body: <String, Object?>{
      'branch_id': ?branchId,
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      if (until != null) 'until': until.toUtc().toIso8601String(),
    },
  );

  /// `DELETE /owner/ingredients/{id}/stop?branch_id`: qayta ochadi (o'z yozuvi bilan to'xtatilgan taom yopiq qoladi).
  Future<void> reopen(
    int ingredientId, {
    int? branchId,
    String? idempotencyKey,
  }) => _http.mutate(
    'DELETE',
    '/v1/owner/ingredients/$ingredientId/stop',
    (Object? _) {},
    idempotencyKey: idempotencyKey,
    query: <String, Object?>{'branch_id': ?branchId},
  );
}
