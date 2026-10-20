// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data18.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data18 _$Data18FromJson(Map<String, dynamic> json) => Data18(
  preferences: (json['preferences'] as List<dynamic>)
      .map((e) => Preferences.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data18ToJson(Data18 instance) => <String, dynamic>{
  'preferences': instance.preferences,
};
