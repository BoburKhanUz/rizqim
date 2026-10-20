// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_stop_list_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerStopListIdResponse _$DeleteV1OwnerStopListIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerStopListIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerStopListIdResponseToJson(
  DeleteV1OwnerStopListIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
