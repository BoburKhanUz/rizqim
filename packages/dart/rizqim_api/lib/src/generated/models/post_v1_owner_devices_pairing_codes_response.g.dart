// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_devices_pairing_codes_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerDevicesPairingCodesResponse
_$PostV1OwnerDevicesPairingCodesResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerDevicesPairingCodesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data59.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerDevicesPairingCodesResponseToJson(
  PostV1OwnerDevicesPairingCodesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
