// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_admin_auth_invite_accept_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AdminAuthInviteAcceptResponse
_$PostV1AdminAuthInviteAcceptResponseFromJson(Map<String, dynamic> json) =>
    PostV1AdminAuthInviteAcceptResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data8.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1AdminAuthInviteAcceptResponseToJson(
  PostV1AdminAuthInviteAcceptResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
