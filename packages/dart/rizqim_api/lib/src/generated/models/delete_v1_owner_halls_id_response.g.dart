// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_halls_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerHallsIdResponse _$DeleteV1OwnerHallsIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerHallsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data101.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeleteV1OwnerHallsIdResponseToJson(
  DeleteV1OwnerHallsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
