// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_halls_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerHallsResponse _$GetV1OwnerHallsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerHallsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data84.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerHallsResponseToJson(
  GetV1OwnerHallsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
