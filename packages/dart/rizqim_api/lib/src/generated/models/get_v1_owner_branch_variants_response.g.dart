// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branch_variants_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchVariantsResponse _$GetV1OwnerBranchVariantsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchVariantsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data36.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerBranchVariantsResponseToJson(
  GetV1OwnerBranchVariantsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
