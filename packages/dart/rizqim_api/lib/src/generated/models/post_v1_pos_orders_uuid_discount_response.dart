// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data217.dart';

part 'post_v1_pos_orders_uuid_discount_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidDiscountResponse {
  const PostV1PosOrdersUuidDiscountResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidDiscountResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidDiscountResponseFromJson(json);

  final bool success;
  final String message;
  final Data217 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidDiscountResponseToJson(this);
}
