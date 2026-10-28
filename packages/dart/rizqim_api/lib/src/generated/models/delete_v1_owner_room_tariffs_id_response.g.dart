// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_room_tariffs_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerRoomTariffsIdResponse _$DeleteV1OwnerRoomTariffsIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerRoomTariffsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data103.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeleteV1OwnerRoomTariffsIdResponseToJson(
  DeleteV1OwnerRoomTariffsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
