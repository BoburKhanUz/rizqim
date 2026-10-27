// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_auth_phone_select_account_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AuthPhoneSelectAccountResponse
_$PostV1AuthPhoneSelectAccountResponseFromJson(Map<String, dynamic> json) =>
    PostV1AuthPhoneSelectAccountResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1AuthPhoneSelectAccountResponseToJson(
  PostV1AuthPhoneSelectAccountResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
