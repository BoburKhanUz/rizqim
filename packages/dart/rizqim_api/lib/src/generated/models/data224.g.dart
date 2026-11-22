// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data224.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data224 _$Data224FromJson(Map<String, dynamic> json) => Data224(
  events: (json['events'] as List<dynamic>)
      .map((e) => Events.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data224ToJson(Data224 instance) => <String, dynamic>{
  'events': instance.events,
};
