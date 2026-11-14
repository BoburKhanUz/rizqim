// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_staff_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidStaffResponse _$GetV1PosBanquetsUuidStaffResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBanquetsUuidStaffResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data240.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBanquetsUuidStaffResponseToJson(
  GetV1PosBanquetsUuidStaffResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
