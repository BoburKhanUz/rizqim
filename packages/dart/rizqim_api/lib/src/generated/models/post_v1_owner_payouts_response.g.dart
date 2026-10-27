// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_payouts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerPayoutsResponse _$PostV1OwnerPayoutsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerPayoutsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data109.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerPayoutsResponseToJson(
  PostV1OwnerPayoutsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
