// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_sync_errors_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosSyncErrorsResponse _$GetV1PosSyncErrorsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosSyncErrorsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data179.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosSyncErrorsResponseToJson(
  GetV1PosSyncErrorsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
