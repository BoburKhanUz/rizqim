// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data40.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data40 _$Data40FromJson(Map<String, dynamic> json) => Data40(
  preferences: (json['preferences'] as List<dynamic>)
      .map((e) => Preferences.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data40ToJson(Data40 instance) => <String, dynamic>{
  'preferences': instance.preferences,
};
