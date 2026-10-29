// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts2 _$Shifts2FromJson(Map<String, dynamic> json) => Shifts2(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$Shifts2ToJson(Shifts2 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
