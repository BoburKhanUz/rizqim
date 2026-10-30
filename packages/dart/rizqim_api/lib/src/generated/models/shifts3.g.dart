// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts3 _$Shifts3FromJson(Map<String, dynamic> json) => Shifts3(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$Shifts3ToJson(Shifts3 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
