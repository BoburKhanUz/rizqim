// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts7 _$Shifts7FromJson(Map<String, dynamic> json) => Shifts7(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$Shifts7ToJson(Shifts7 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
