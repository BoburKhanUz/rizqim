// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data105.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data105 _$Data105FromJson(Map<String, dynamic> json) => Data105(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data105ToJson(Data105 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
