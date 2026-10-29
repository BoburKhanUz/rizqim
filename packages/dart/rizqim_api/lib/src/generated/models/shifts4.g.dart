// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts4 _$Shifts4FromJson(Map<String, dynamic> json) => Shifts4(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$Shifts4ToJson(Shifts4 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
