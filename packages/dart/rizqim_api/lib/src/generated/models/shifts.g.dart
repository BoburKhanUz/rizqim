// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts _$ShiftsFromJson(Map<String, dynamic> json) => Shifts(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$ShiftsToJson(Shifts instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
