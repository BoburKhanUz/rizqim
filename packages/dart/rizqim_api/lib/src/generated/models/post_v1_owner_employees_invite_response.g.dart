// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_employees_invite_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerEmployeesInviteResponse _$PostV1OwnerEmployeesInviteResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerEmployeesInviteResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data33.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerEmployeesInviteResponseToJson(
  PostV1OwnerEmployeesInviteResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
