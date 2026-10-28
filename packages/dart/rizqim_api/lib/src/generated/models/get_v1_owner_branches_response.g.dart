// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_branches_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBranchesResponse _$GetV1OwnerBranchesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBranchesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data37.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerBranchesResponseToJson(
  GetV1OwnerBranchesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
