// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data220.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data220 _$Data220FromJson(Map<String, dynamic> json) => Data220(
  events: (json['events'] as List<dynamic>)
      .map((e) => Events.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data220ToJson(Data220 instance) => <String, dynamic>{
  'events': instance.events,
};
