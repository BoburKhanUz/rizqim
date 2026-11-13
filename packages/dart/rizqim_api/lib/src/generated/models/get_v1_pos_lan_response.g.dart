// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_lan_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosLanResponse _$GetV1PosLanResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosLanResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'] == null
          ? null
          : Data228.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosLanResponseToJson(
  GetV1PosLanResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
