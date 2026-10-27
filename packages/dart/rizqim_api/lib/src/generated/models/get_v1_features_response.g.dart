// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_features_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1FeaturesResponse _$GetV1FeaturesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1FeaturesResponse(
  success: json['success'] as bool,
  data: Data15.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1FeaturesResponseToJson(
  GetV1FeaturesResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
