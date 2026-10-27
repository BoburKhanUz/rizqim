// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data125.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data125 _$Data125FromJson(Map<String, dynamic> json) => Data125(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data125ToJson(Data125 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
