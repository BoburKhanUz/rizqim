// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data243.dart';

part 'post_v1_pos_orders_uuid_packaging_rule_restore_response.g.dart';

@JsonSerializable()
class PostV1PosOrdersUuidPackagingRuleRestoreResponse {
  const PostV1PosOrdersUuidPackagingRuleRestoreResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosOrdersUuidPackagingRuleRestoreResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosOrdersUuidPackagingRuleRestoreResponseFromJson(json);

  final bool success;
  final String message;
  final Data243 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosOrdersUuidPackagingRuleRestoreResponseToJson(this);
}
