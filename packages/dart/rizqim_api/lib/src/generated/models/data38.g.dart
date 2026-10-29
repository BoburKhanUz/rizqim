// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data38.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data38 _$Data38FromJson(Map<String, dynamic> json) => Data38(
  writeOff: WriteOff.fromJson(json['write_off'] as Map<String, dynamic>),
  batch: Batch.fromJson(json['batch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data38ToJson(Data38 instance) => <String, dynamic>{
  'write_off': instance.writeOff,
  'batch': instance.batch,
};
