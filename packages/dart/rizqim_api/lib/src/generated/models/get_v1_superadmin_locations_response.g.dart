// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_superadmin_locations_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1SuperadminLocationsResponse _$GetV1SuperadminLocationsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1SuperadminLocationsResponse(
  success: json['success'] as bool,
  data: Data189.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1SuperadminLocationsResponseToJson(
  GetV1SuperadminLocationsResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
