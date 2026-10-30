// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_packaging_rule_restore_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidPackagingRuleRestoreResponse
_$PostV1PosOrdersUuidPackagingRuleRestoreResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidPackagingRuleRestoreResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data243.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidPackagingRuleRestoreResponseToJson(
  PostV1PosOrdersUuidPackagingRuleRestoreResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
