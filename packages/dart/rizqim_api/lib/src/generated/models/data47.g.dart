// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data47.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data47 _$Data47FromJson(Map<String, dynamic> json) => Data47(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data47ToJson(Data47 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
