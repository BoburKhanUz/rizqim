// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_room_tariffs_calendar_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerRoomTariffsCalendarRequestBody
_$V1OwnerRoomTariffsCalendarRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerRoomTariffsCalendarRequestBody(
      branchId: (json['branch_id'] as num).toInt(),
      weekendDays: (json['weekend_days'] as List<dynamic>)
          .map((e) => (e as num).toInt())
          .toList(),
      holidays: (json['holidays'] as List<dynamic>)
          .map((e) => DateTime.parse(e as String))
          .toList(),
    );

Map<String, dynamic> _$V1OwnerRoomTariffsCalendarRequestBodyToJson(
  V1OwnerRoomTariffsCalendarRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'weekend_days': instance.weekendDays,
  'holidays': instance.holidays.map((e) => e.toIso8601String()).toList(),
};
