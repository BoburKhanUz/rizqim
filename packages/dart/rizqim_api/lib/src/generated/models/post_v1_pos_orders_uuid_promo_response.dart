// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data268.dart';

part 'post_v1_pos_orders_uuid_promo_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidPromoResponse {
  const PostV1PosOrdersUuidPromoResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidPromoResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidPromoResponseFromJson(json);

  final bool success;
  final String message;
  final Data268 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidPromoResponseToJson(this);
}
