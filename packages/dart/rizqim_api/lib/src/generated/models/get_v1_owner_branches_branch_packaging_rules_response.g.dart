// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_branch_packaging_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesBranchPackagingRulesResponse
_$GetV1OwnerBranchesBranchPackagingRulesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchesBranchPackagingRulesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data44.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerBranchesBranchPackagingRulesResponseToJson(
  GetV1OwnerBranchesBranchPackagingRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
