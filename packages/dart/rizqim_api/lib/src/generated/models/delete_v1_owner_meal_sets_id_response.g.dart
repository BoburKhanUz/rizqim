// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_meal_sets_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerMealSetsIdResponse _$DeleteV1OwnerMealSetsIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerMealSetsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerMealSetsIdResponseToJson(
  DeleteV1OwnerMealSetsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
