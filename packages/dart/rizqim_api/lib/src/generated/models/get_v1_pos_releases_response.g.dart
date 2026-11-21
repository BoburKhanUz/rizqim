// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_releases_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosReleasesResponse _$GetV1PosReleasesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosReleasesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data339.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosReleasesResponseToJson(
  GetV1PosReleasesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
