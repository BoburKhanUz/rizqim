// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_approvals_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosApprovalsResponse _$PostV1PosApprovalsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosApprovalsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data182.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosApprovalsResponseToJson(
  PostV1PosApprovalsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
