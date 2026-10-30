// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_promo_validate_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PromoValidateResponse _$PostV1PromoValidateResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PromoValidateResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data277.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PromoValidateResponseToJson(
  PostV1PromoValidateResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
