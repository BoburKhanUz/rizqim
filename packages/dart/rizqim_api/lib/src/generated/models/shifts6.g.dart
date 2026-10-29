// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts6 _$Shifts6FromJson(Map<String, dynamic> json) => Shifts6(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$Shifts6ToJson(Shifts6 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
