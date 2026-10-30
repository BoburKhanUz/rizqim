// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_stores_store_branches_branch_pickup_slots_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1StoresStoreBranchesBranchPickupSlotsResponse
_$GetV1StoresStoreBranchesBranchPickupSlotsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1StoresStoreBranchesBranchPickupSlotsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data287.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1StoresStoreBranchesBranchPickupSlotsResponseToJson(
  GetV1StoresStoreBranchesBranchPickupSlotsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
