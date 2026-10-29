// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_fiscal_devices_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerFiscalDevicesUuidResponse
_$PutV1OwnerFiscalDevicesUuidResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerFiscalDevicesUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data75.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerFiscalDevicesUuidResponseToJson(
  PutV1OwnerFiscalDevicesUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
