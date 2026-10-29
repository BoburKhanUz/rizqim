// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_user_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1UserResponse _$GetV1UserResponseFromJson(Map<String, dynamic> json) =>
    GetV1UserResponse(
      success: json['success'] as bool,
      data: Data269.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1UserResponseToJson(GetV1UserResponse instance) =>
    <String, dynamic>{'success': instance.success, 'data': instance.data};
