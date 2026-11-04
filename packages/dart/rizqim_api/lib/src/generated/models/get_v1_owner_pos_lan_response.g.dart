// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_lan_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosLanResponse _$GetV1OwnerPosLanResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosLanResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data129.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosLanResponseToJson(
  GetV1OwnerPosLanResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
