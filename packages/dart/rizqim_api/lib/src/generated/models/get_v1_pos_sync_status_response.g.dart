// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_sync_status_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosSyncStatusResponse _$GetV1PosSyncStatusResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosSyncStatusResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data202.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosSyncStatusResponseToJson(
  GetV1PosSyncStatusResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
