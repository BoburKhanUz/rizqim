// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_pos_banquets_uuid_staff_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1PosBanquetsUuidStaffResponse _$PutV1PosBanquetsUuidStaffResponseFromJson(
  Map<String, dynamic> json,
) => PutV1PosBanquetsUuidStaffResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data241.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1PosBanquetsUuidStaffResponseToJson(
  PutV1PosBanquetsUuidStaffResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
