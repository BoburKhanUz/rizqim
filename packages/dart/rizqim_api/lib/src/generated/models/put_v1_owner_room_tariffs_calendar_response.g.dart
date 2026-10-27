// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_room_tariffs_calendar_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerRoomTariffsCalendarResponse
_$PutV1OwnerRoomTariffsCalendarResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerRoomTariffsCalendarResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data122.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerRoomTariffsCalendarResponseToJson(
  PutV1OwnerRoomTariffsCalendarResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
