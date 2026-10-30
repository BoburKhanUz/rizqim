// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/patch_v1_owner_products_id_pos_service_response.dart';
import '../models/v1_owner_products_id_pos_service_request_body.dart';

part 'owner_menu_api.g.dart';

@RestApi()
abstract class OwnerMenuApi {
  factory OwnerMenuApi(Dio dio, {String? baseUrl}) = _OwnerMenuApi;

  /// A dish's skewer buttons, grill time and weight rule.
  ///
  /// Only what is sent changes; null clears it. `quick_multipliers`: the ×N buttons (2–10, each once, up to 9; module.
  /// `skewer_buttons`), `wait_minutes`: the grill's time (0–240; module `skewer_buttons`), `weight_mode`: `raw` |.
  /// `cooked` — the dish is weighed after it is chosen and its kg price is for that weight (module `weight_later`; only.
  /// a kg/l dish not sold from a kazan batch). Setting a value whose module is off for every branch of the store: 403.
  /// `module_disabled` (clearing never is). Audited. menu.manage.
  @PATCH('/v1/owner/products/{id}/pos-service')
  Future<PatchV1OwnerProductsIdPosServiceResponse> productPosServiceUpdate({
    @Path('id') required int id,
    @Body() V1OwnerProductsIdPosServiceRequestBody? body,
  });
}
