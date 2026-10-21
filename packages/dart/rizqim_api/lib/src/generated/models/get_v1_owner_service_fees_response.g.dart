// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_service_fees_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerServiceFeesResponse _$GetV1OwnerServiceFeesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerServiceFeesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data67.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerServiceFeesResponseToJson(
  GetV1OwnerServiceFeesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
