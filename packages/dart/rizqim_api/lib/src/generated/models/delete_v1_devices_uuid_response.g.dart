// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_devices_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1DevicesUuidResponse _$DeleteV1DevicesUuidResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1DevicesUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1DevicesUuidResponseToJson(
  DeleteV1DevicesUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
