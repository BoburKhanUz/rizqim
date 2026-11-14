// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_halls_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsHallsResponse _$GetV1OwnerPosBanquetsHallsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosBanquetsHallsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data136.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosBanquetsHallsResponseToJson(
  GetV1OwnerPosBanquetsHallsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
