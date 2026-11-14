// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_qr_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosQrUuidResponse _$GetV1PosQrUuidResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosQrUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data329.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosQrUuidResponseToJson(
  GetV1PosQrUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
