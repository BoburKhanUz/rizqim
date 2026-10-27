// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_geocode_search_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1GeocodeSearchResponse _$GetV1GeocodeSearchResponseFromJson(
  Map<String, dynamic> json,
) => GetV1GeocodeSearchResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data17.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1GeocodeSearchResponseToJson(
  GetV1GeocodeSearchResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
