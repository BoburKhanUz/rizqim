// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_uuid_warnings_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsUuidWarningsResponse
_$GetV1PosBanquetsUuidWarningsResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsUuidWarningsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data244.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsUuidWarningsResponseToJson(
  GetV1PosBanquetsUuidWarningsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
