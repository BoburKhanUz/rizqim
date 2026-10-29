// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_pos_service_charge_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerPosServiceChargeRulesResponse
_$PutV1OwnerPosServiceChargeRulesResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerPosServiceChargeRulesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data114.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerPosServiceChargeRulesResponseToJson(
  PutV1OwnerPosServiceChargeRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
