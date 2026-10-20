// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_promo_validate_request_body.g.dart';

@JsonSerializable()
class V1PromoValidateRequestBody {
  const V1PromoValidateRequestBody({
    required this.code,
    required this.storeId,
    this.subtotal,
  });

  factory V1PromoValidateRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PromoValidateRequestBodyFromJson(json);

  final String code;
  @JsonKey(name: 'store_id')
  final int storeId;
  final num? subtotal;

  Map<String, Object?> toJson() => _$V1PromoValidateRequestBodyToJson(this);
}
