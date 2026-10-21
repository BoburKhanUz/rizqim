// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_devices_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerDevicesResponse _$GetV1OwnerDevicesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerDevicesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data46.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerDevicesResponseToJson(
  GetV1OwnerDevicesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
