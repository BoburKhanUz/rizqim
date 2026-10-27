// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_couriers_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1CouriersResponse _$GetV1CouriersResponseFromJson(
  Map<String, dynamic> json,
) => GetV1CouriersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data28.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1CouriersResponseToJson(
  GetV1CouriersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
