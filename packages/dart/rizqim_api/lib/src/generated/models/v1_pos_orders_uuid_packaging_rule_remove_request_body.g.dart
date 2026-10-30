// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_packaging_rule_remove_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidPackagingRuleRemoveRequestBody
_$V1PosOrdersUuidPackagingRuleRemoveRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidPackagingRuleRemoveRequestBody(
  version: (json['version'] as num).toInt(),
  reason: json['reason'] as String,
);

Map<String, dynamic> _$V1PosOrdersUuidPackagingRuleRemoveRequestBodyToJson(
  V1PosOrdersUuidPackagingRuleRemoveRequestBody instance,
) => <String, dynamic>{'version': instance.version, 'reason': instance.reason};
