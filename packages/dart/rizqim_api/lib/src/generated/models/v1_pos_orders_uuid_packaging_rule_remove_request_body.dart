// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_packaging_rule_remove_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidPackagingRuleRemoveRequestBody {
  const V1PosOrdersUuidPackagingRuleRemoveRequestBody({
    required this.version,
    required this.reason,
  });

  factory V1PosOrdersUuidPackagingRuleRemoveRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidPackagingRuleRemoveRequestBodyFromJson(json);

  final int version;
  final String reason;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidPackagingRuleRemoveRequestBodyToJson(this);
}
