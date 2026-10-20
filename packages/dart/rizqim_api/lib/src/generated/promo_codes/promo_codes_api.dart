// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/post_v1_promo_validate_response.dart';
import '../models/v1_promo_validate_request_body.dart';

part 'promo_codes_api.g.dart';

@RestApi()
abstract class PromoCodesApi {
  factory PromoCodesApi(Dio dio, {String? baseUrl}) = _PromoCodesApi;

  /// Check a promo code.
  ///
  /// Preview only: the discount is recalculated and the limits re-checked under lock when the order is placed.
  /// `discount_amount` (UZS) is null when no `subtotal` was sent. 4xx with the reason when the.
  /// code cannot be used.
  @POST('/v1/promo/validate')
  Future<PostV1PromoValidateResponse> promoCodeValidateCode({
    @Body() required V1PromoValidateRequestBody body,
  });
}
