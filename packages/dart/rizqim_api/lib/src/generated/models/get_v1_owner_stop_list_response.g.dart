// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_stop_list_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerStopListResponse _$GetV1OwnerStopListResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerStopListResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data114.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerStopListResponseToJson(
  GetV1OwnerStopListResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
