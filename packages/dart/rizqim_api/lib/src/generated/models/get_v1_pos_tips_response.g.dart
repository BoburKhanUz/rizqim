// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_tips_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosTipsResponse _$GetV1PosTipsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosTipsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data249.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosTipsResponseToJson(
  GetV1PosTipsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
