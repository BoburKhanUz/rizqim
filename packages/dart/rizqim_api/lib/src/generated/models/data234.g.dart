// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data234.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data234 _$Data234FromJson(Map<String, dynamic> json) => Data234(
  event: Event2.fromJson(json['event'] as Map<String, dynamic>),
  settlement: Settlement2.fromJson(json['settlement'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data234ToJson(Data234 instance) => <String, dynamic>{
  'event': instance.event,
  'settlement': instance.settlement,
};
