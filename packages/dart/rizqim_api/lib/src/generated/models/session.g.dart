// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'session.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Session _$SessionFromJson(Map<String, dynamic> json) => Session(
  uuid: json['uuid'] as String,
  businessDate: json['business_date'] as String,
  clockInAt: json['clock_in_at'] as String,
);

Map<String, dynamic> _$SessionToJson(Session instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'business_date': instance.businessDate,
  'clock_in_at': instance.clockInAt,
};
