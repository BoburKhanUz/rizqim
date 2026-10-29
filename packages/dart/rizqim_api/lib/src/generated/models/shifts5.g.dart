// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts5 _$Shifts5FromJson(Map<String, dynamic> json) => Shifts5(
  uuid: json['uuid'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  status: json['status'] as String,
);

Map<String, dynamic> _$Shifts5ToJson(Shifts5 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'z_number': instance.zNumber,
  'status': instance.status,
};
