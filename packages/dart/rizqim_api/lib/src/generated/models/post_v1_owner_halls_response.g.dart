// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_halls_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerHallsResponse _$PostV1OwnerHallsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerHallsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data92.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerHallsResponseToJson(
  PostV1OwnerHallsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
