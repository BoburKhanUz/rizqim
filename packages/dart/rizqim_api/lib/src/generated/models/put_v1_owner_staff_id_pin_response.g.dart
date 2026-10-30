// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_staff_id_pin_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerStaffIdPinResponse _$PutV1OwnerStaffIdPinResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerStaffIdPinResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data160.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerStaffIdPinResponseToJson(
  PutV1OwnerStaffIdPinResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
