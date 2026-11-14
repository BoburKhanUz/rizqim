// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data218.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data218 _$Data218FromJson(Map<String, dynamic> json) => Data218(
  events: (json['events'] as List<dynamic>)
      .map((e) => Events.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data218ToJson(Data218 instance) => <String, dynamic>{
  'events': instance.events,
};
