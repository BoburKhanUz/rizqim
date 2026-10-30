// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_meal_sets_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerMealSetsIdResponse _$PutV1OwnerMealSetsIdResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerMealSetsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data114.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerMealSetsIdResponseToJson(
  PutV1OwnerMealSetsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
