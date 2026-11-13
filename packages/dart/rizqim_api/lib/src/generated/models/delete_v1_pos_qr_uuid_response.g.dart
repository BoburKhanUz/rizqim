// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_pos_qr_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1PosQrUuidResponse _$DeleteV1PosQrUuidResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1PosQrUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data279.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeleteV1PosQrUuidResponseToJson(
  DeleteV1PosQrUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
