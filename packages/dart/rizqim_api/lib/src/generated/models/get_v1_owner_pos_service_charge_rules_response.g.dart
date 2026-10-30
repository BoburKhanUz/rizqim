// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_service_charge_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosServiceChargeRulesResponse
_$GetV1OwnerPosServiceChargeRulesResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosServiceChargeRulesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data113.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosServiceChargeRulesResponseToJson(
  GetV1OwnerPosServiceChargeRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
