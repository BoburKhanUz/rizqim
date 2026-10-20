// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_staff_id_pin_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerStaffIdPinResponse _$DeleteV1OwnerStaffIdPinResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerStaffIdPinResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerStaffIdPinResponseToJson(
  DeleteV1OwnerStaffIdPinResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
