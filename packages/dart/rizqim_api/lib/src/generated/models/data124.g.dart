// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data124.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data124 _$Data124FromJson(Map<String, dynamic> json) => Data124(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data124ToJson(Data124 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
