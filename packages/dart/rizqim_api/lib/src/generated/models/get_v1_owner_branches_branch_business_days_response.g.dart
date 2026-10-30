// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_branch_business_days_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesBranchBusinessDaysResponse
_$GetV1OwnerBranchesBranchBusinessDaysResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchesBranchBusinessDaysResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data43.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBranchesBranchBusinessDaysResponseToJson(
  GetV1OwnerBranchesBranchBusinessDaysResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
