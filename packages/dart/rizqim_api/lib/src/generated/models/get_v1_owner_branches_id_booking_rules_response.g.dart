// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_id_booking_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesIdBookingRulesResponse
_$GetV1OwnerBranchesIdBookingRulesResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerBranchesIdBookingRulesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data53.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerBranchesIdBookingRulesResponseToJson(
  GetV1OwnerBranchesIdBookingRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
