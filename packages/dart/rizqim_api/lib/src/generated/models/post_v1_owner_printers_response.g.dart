// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_printers_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPrintersResponse _$PostV1OwnerPrintersResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerPrintersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data92.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerPrintersResponseToJson(
  PostV1OwnerPrintersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
