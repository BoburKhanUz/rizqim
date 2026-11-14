// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsResponse _$GetV1PosBanquetsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBanquetsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data218.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBanquetsResponseToJson(
  GetV1PosBanquetsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
