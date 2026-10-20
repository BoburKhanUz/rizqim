// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_user_profile_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1UserProfileRequestBody _$V1UserProfileRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1UserProfileRequestBody(
  name: json['name'] as String,
  phone: json['phone'] as String?,
  address: json['address'] as String?,
);

Map<String, dynamic> _$V1UserProfileRequestBodyToJson(
  V1UserProfileRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'address': instance.address,
};
