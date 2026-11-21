// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data221.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data221 _$Data221FromJson(Map<String, dynamic> json) => Data221(
  events: (json['events'] as List<dynamic>)
      .map((e) => Events.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data221ToJson(Data221 instance) => <String, dynamic>{
  'events': instance.events,
};
