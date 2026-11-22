// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data223.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data223 _$Data223FromJson(Map<String, dynamic> json) => Data223(
  events: (json['events'] as List<dynamic>)
      .map((e) => Events.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data223ToJson(Data223 instance) => <String, dynamic>{
  'events': instance.events,
};
