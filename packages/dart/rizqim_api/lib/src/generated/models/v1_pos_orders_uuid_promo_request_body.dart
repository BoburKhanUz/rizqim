// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_promo_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidPromoRequestBody {
  const V1PosOrdersUuidPromoRequestBody({
    required this.version,
    required this.code,
  });

  factory V1PosOrdersUuidPromoRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersUuidPromoRequestBodyFromJson(json);

  final int version;
  final String code;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidPromoRequestBodyToJson(this);
}
