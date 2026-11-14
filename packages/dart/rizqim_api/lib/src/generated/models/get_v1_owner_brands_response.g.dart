// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_brands_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBrandsResponse _$GetV1OwnerBrandsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerBrandsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data59.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerBrandsResponseToJson(
  GetV1OwnerBrandsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
