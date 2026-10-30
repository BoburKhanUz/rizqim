// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_packaging_rule_remove_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidPackagingRuleRemoveResponse
_$PostV1PosOrdersUuidPackagingRuleRemoveResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidPackagingRuleRemoveResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data242.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidPackagingRuleRemoveResponseToJson(
  PostV1PosOrdersUuidPackagingRuleRemoveResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
