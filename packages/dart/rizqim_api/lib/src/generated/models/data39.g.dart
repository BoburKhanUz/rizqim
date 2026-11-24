// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data39.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data39 _$Data39FromJson(Map<String, dynamic> json) => Data39(
  writeOff: WriteOff.fromJson(json['write_off'] as Map<String, dynamic>),
  batch: Batch.fromJson(json['batch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data39ToJson(Data39 instance) => <String, dynamic>{
  'write_off': instance.writeOff,
  'batch': instance.batch,
};
