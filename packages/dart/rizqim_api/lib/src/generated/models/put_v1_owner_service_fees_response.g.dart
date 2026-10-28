// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_service_fees_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerServiceFeesResponse _$PutV1OwnerServiceFeesResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerServiceFeesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data106.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerServiceFeesResponseToJson(
  PutV1OwnerServiceFeesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
