// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data153.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data153 _$Data153FromJson(Map<String, dynamic> json) => Data153(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data153ToJson(Data153 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
