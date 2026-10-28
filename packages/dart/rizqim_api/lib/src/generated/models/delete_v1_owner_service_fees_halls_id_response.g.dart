// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_service_fees_halls_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerServiceFeesHallsIdResponse
_$DeleteV1OwnerServiceFeesHallsIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerServiceFeesHallsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data107.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1OwnerServiceFeesHallsIdResponseToJson(
  DeleteV1OwnerServiceFeesHallsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
