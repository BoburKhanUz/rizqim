// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_search_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminSearchResponse _$GetV1AdminSearchResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminSearchResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data15.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1AdminSearchResponseToJson(
  GetV1AdminSearchResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
