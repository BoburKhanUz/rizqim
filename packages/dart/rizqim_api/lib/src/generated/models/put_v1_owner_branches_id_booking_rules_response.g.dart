// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_branches_id_booking_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBranchesIdBookingRulesResponse
_$PutV1OwnerBranchesIdBookingRulesResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerBranchesIdBookingRulesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data54.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerBranchesIdBookingRulesResponseToJson(
  PutV1OwnerBranchesIdBookingRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
