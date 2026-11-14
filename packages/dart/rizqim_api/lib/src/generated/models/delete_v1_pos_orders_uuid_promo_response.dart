// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data320.dart';

part 'delete_v1_pos_orders_uuid_promo_response.g.dart';

@JsonSerializable()
class DeleteV1PosOrdersUuidPromoResponse {
  const DeleteV1PosOrdersUuidPromoResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1PosOrdersUuidPromoResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1PosOrdersUuidPromoResponseFromJson(json);

  final bool success;
  final String message;
  final Data320 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1PosOrdersUuidPromoResponseToJson(this);
}
