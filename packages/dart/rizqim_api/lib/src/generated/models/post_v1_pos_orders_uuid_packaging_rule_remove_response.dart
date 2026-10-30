// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data242.dart';

part 'post_v1_pos_orders_uuid_packaging_rule_remove_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidPackagingRuleRemoveResponse {
  const PostV1PosOrdersUuidPackagingRuleRemoveResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidPackagingRuleRemoveResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidPackagingRuleRemoveResponseFromJson(json);

  final bool success;
  final String message;
  final Data242 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidPackagingRuleRemoveResponseToJson(this);
}
