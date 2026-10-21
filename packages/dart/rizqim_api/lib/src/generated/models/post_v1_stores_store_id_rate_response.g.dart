// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_stores_store_id_rate_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1StoresStoreIdRateResponse _$PostV1StoresStoreIdRateResponseFromJson(
  Map<String, dynamic> json,
) => PostV1StoresStoreIdRateResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data86.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1StoresStoreIdRateResponseToJson(
  PostV1StoresStoreIdRateResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
